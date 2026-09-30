<?php

namespace App\Imports;

use App\Models\MasterImport;
use App\Models\MasterImportIssue;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Drives both phases of a master-data import (see the master_imports migration).
 *
 * Commit progress and cancel requests go through the *file* cache store, not the DB: the commit
 * runs inside one long transaction, so progress written to master_imports on the same connection
 * would stay invisible to the polling request until the very end, and a cancel flag updated by
 * another request would be hidden by the transaction's snapshot.
 */
class MasterImportRunner
{
    public const MAX_ROWS = 20000;

    /** @return array<string, class-string<MasterImportProcessor>> */
    public static function processors(): array
    {
        return [
            MasterImport::TYPE_PRODUCTS => MasterProductsImportProcessor::class,
            MasterImport::TYPE_LINES => MasterLinesImportProcessor::class,
        ];
    }

    public static function processorFor(string $type): MasterImportProcessor
    {
        $class = self::processors()[$type] ?? throw new InvalidArgumentException("Unknown import type [{$type}].");

        return app($class);
    }

    public static function progressCache(): CacheRepository
    {
        return Cache::store('file');
    }

    public static function progressKey(MasterImport $import): string
    {
        return "master-import:{$import->id}:progress";
    }

    public static function cancelKey(MasterImport $import): string
    {
        return "master-import:{$import->id}:cancel";
    }

    public function validate(MasterImport $import): void
    {
        if ($import->status !== MasterImport::STATUS_QUEUED) {
            return;
        }
        if ($import->cancel_requested) {
            $this->finish($import, MasterImport::STATUS_CANCELLED, 'Import dibatalkan sebelum diproses.');

            return;
        }

        $import->update(['status' => MasterImport::STATUS_VALIDATING, 'started_at' => now(), 'processed_rows' => 0]);
        $processor = self::processorFor($import->type);

        try {
            $rows = $this->readRows($import, $processor);
            $import->update(['total_rows' => count($rows)]);

            $plan = $processor->plan($rows, function (int $processed) use ($import) {
                if ($import->newQuery()->whereKey($import->id)->value('cancel_requested')) {
                    throw new ImportCancelledException;
                }
                $import->newQuery()->whereKey($import->id)->update(['processed_rows' => $processed]);
            });
        } catch (ImportCancelledException) {
            $this->finish($import, MasterImport::STATUS_CANCELLED, 'Validasi dibatalkan oleh pengguna.');

            return;
        } catch (ImportFileException $e) {
            $this->finish($import, MasterImport::STATUS_FAILED, $e->getMessage());

            return;
        }

        DB::transaction(function () use ($import, $plan) {
            $import->issues()->delete();
            foreach (array_chunk($plan->issues, 500) as $chunk) {
                MasterImportIssue::insert(array_map(fn (array $issue) => [
                    'master_import_id' => $import->id,
                    'row_number' => $issue['row_number'],
                    'severity' => $issue['severity'],
                    'message' => mb_substr($issue['message'], 0, 500),
                    'values' => json_encode($issue['values'], JSON_UNESCAPED_UNICODE),
                ], $chunk));
            }

            $import->update([
                'status' => MasterImport::STATUS_VALIDATED,
                'processed_rows' => $plan->totalRows,
                'total_rows' => $plan->totalRows,
                'valid_rows' => $plan->validRows,
                'error_rows' => $plan->errorRows(),
                'warning_rows' => $plan->warningRows(),
                'preview' => $plan->preview,
                'validated_at' => now(),
            ]);
        });
    }

    public function commit(MasterImport $import): void
    {
        if ($import->status !== MasterImport::STATUS_COMMIT_QUEUED) {
            return;
        }

        $cache = self::progressCache();
        if ($import->cancel_requested || $cache->get(self::cancelKey($import))) {
            $this->finish($import, MasterImport::STATUS_CANCELLED, 'Penyimpanan dibatalkan sebelum dimulai. Tidak ada data yang diubah.');

            return;
        }

        $import->update(['status' => MasterImport::STATUS_COMMITTING, 'committed_at' => now(), 'processed_rows' => 0]);
        $processor = self::processorFor($import->type);

        try {
            // Re-plan against the DB as it is *now*: something may have changed since validation.
            $plan = $processor->plan($this->readRows($import, $processor), fn () => null);
            $total = count($plan->operations);
            $import->update(['total_rows' => $total]);

            $result = DB::transaction(fn () => $processor->apply($plan, function (int $processed) use ($import, $cache, $total) {
                if ($cache->get(self::cancelKey($import))) {
                    throw new ImportCancelledException;
                }
                $cache->put(self::progressKey($import), ['processed' => $processed, 'total' => $total], 3600);
            }));
        } catch (ImportCancelledException) {
            $this->finish($import, MasterImport::STATUS_CANCELLED, 'Penyimpanan dibatalkan oleh pengguna. Semua perubahan dibatalkan (rollback), tidak ada data yang tersimpan.');

            return;
        } catch (Throwable $e) {
            report($e);
            $this->finish($import, MasterImport::STATUS_FAILED, 'Penyimpanan gagal dan dibatalkan seluruhnya (rollback), jadi tidak ada data yang tersimpan. Silakan coba lagi. Detail: '.mb_substr($e->getMessage(), 0, 300));

            return;
        }

        $import->update(['processed_rows' => $total, 'result' => $result]);
        $this->finish($import, MasterImport::STATUS_COMPLETED);
    }

    public function finish(MasterImport $import, string $status, ?string $message = null): void
    {
        $import->update([
            'status' => $status,
            'error_message' => $message,
            'finished_at' => now(),
        ]);

        self::progressCache()->forget(self::progressKey($import));
        self::progressCache()->forget(self::cancelKey($import));

        if ($import->file_path !== '' && in_array($status, [MasterImport::STATUS_COMPLETED, MasterImport::STATUS_CANCELLED], true)) {
            Storage::disk('local')->delete($import->file_path);
        }
    }

    /**
     * Abandoned previews and failed imports keep their uploaded file (a failed commit can be
     * retried). Called on every new upload rather than from the scheduler, since no cron runs
     * schedule:run on the server.
     */
    public function pruneStaleFiles(): void
    {
        MasterImport::query()
            ->whereNotIn('status', MasterImport::RUNNING_STATUSES)
            ->where('updated_at', '<', now()->subDay())
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->each(function (MasterImport $import) {
                Storage::disk('local')->delete($import->file_path);
                if ($import->status === MasterImport::STATUS_VALIDATED) {
                    $this->finish($import, MasterImport::STATUS_CANCELLED, 'Kedaluwarsa: tidak disimpan dalam 24 jam. Silakan upload ulang.');
                }
                $import->forceFill(['file_path' => ''])->saveQuietly();
            });
    }

    /** @return list<array{row: int, data: array<string, string>}> */
    private function readRows(MasterImport $import, MasterImportProcessor $processor): array
    {
        if ($import->file_path === '' || ! Storage::disk('local')->exists($import->file_path)) {
            throw new ImportFileException('File upload tidak ditemukan lagi di server. Silakan upload ulang.');
        }

        try {
            ['headings' => $headings, 'rows' => $rows] = SpreadsheetRows::read($import->file_path);
        } catch (Throwable $e) {
            report($e);
            throw new ImportFileException('File tidak bisa dibaca. Pastikan file berformat .xlsx/.xls/.csv yang valid dan tidak diproteksi password.');
        }

        $missing = array_diff_key($processor->requiredHeadings(), array_flip($headings));
        if ($missing !== []) {
            throw new ImportFileException(
                'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Pastikan baris pertama berisi judul kolom sesuai template (unduh template terbaru).',
            );
        }

        if ($rows === []) {
            throw new ImportFileException('File tidak berisi data (hanya judul kolom).');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new ImportFileException('File berisi '.number_format(count($rows), 0, ',', '.').' baris. Maksimal '.number_format(self::MAX_ROWS, 0, ',', '.').' baris per upload, jadi pecah file menjadi beberapa bagian.');
        }

        return $rows;
    }
}
