<?php

namespace App\Console\Commands;

use App\Models\IpcDataFlag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan ipc:audit-number-formats [--show=10] [--flag]` — data-quality check.
 *
 * Without --flag it is strictly read-only. With --flag it ALSO records each non-conforming value in
 * the side table ipc_data_flags (idempotent) so a training/analysis export can skip it; the audited
 * tables themselves are never changed.
 *
 * Numbers must be stored with a dot as decimal separator (clean data for reports and later AI
 * training). Real decimal columns can't hold anything else, so only the free-text columns that
 * carry numbers are checked, each against the exact format its request validates.
 * Never writes. Exit code 1 when something non-conforming is found (usable in CI / cron).
 */
class AuditNumberFormats extends Command
{
    protected $signature = 'ipc:audit-number-formats {--show=10 : Jumlah contoh baris per temuan} {--flag : Catat nilai tidak sesuai ke ipc_data_flags agar tidak ikut training}';

    protected $description = 'Periksa (baca saja) kolom teks berisi angka: harus pakai titik desimal dan format yang valid';

    /** table => [column => [regex that valid values must match, human description]] */
    private const CHECKS = [
        'packing_checks' => ['standard_weight_mb' => ['/^\d+(\.\d+)?(-\d+(\.\d+)?)?$/', 'angka atau rentang, titik desimal']],
        'packing_check_revisions' => ['standard_weight_mb' => ['/^\d+(\.\d+)?(-\d+(\.\d+)?)?$/', 'angka atau rentang, titik desimal']],
        'finished_checks' => [
            'masterbox' => ['/^\d+(,\d+)*$/', 'bilangan bulat dipisah koma'],
            'no_pallet_qty' => ['/^\d+\/\d+(,\d+\/\d+)*$/', 'pallet/qty dipisah koma'],
        ],
        'finished_check_revisions' => [
            'masterbox' => ['/^\d+(,\d+)*$/', 'bilangan bulat dipisah koma'],
            'no_pallet_qty' => ['/^\d+\/\d+(,\d+\/\d+)*$/', 'pallet/qty dipisah koma'],
        ],
    ];

    public function handle(): int
    {
        $show = max(0, (int) $this->option('show'));
        $bad = 0;
        $flagging = (bool) $this->option('flag');

        foreach (self::CHECKS as $table => $columns) {
            foreach ($columns as $column => [$pattern, $description]) {
                $total = 0;
                $invalid = [];

                DB::table($table)->whereNotNull($column)->select(['id', $column])->orderBy('id')
                    ->each(function ($row) use ($column, $pattern, &$total, &$invalid) {
                        $total++;
                        if (! preg_match($pattern, (string) $row->{$column})) {
                            $invalid[] = ['id' => $row->id, 'value' => $row->{$column}];
                        }
                    });

                $count = count($invalid);
                $bad += $count;
                $this->line(sprintf('%-26s %-20s %5d dicek, %s tidak sesuai (%s)', $table, $column, $total, $count, $description));

                if ($count > 0 && $show > 0) {
                    $this->table(['id', 'nilai'], array_slice($invalid, 0, $show));
                }

                if ($flagging) {
                    foreach ($invalid as $row) {
                        IpcDataFlag::firstOrCreate(
                            ['source_table' => $table, 'source_id' => (string) $row['id'], 'field' => $column, 'flag' => IpcDataFlag::BAD_NUMBER_FORMAT],
                            ['reason' => "Format tidak sesuai ({$description}): {$row['value']}", 'detected_by' => 'rule'],
                        );
                    }
                }
            }
        }

        $this->newLine();
        if ($bad === 0) {
            $this->info('Semua nilai sesuai format.');
        } else {
            $note = $flagging
                ? 'Sudah dicatat di ipc_data_flags (tabel asli tidak diubah).'
                : 'Perintah ini tidak mengubah data (pakai --flag untuk menandai).';
            $this->warn("{$bad} nilai tidak sesuai format. {$note}");
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
