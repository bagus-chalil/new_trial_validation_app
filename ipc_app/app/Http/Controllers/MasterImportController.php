<?php

namespace App\Http\Controllers;

use App\Exports\MasterImportIssuesExport;
use App\Http\Requests\ImportMasterFileRequest;
use App\Imports\MasterImportRunner;
use App\Jobs\CommitMasterImport;
use App\Jobs\ValidateMasterImport;
use App\Models\MasterImport;
use App\Models\MasterImportIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * JSON endpoints behind the master-data import dialog (polled by the frontend, not Inertia):
 * upload -> queued validation -> preview -> user confirms -> queued single-transaction commit.
 */
class MasterImportController extends Controller
{
    private const ISSUES_PREVIEW_LIMIT = 100;

    public function __construct(private MasterImportRunner $runner) {}

    public function store(ImportMasterFileRequest $request, string $type): JsonResponse
    {
        if ($blocking = $this->otherCommitInProgress($type)) {
            return $this->conflict($blocking);
        }

        $this->runner->pruneStaleFiles();

        // A new upload supersedes this user's own unfinished preview of the same master.
        MasterImport::where('user_id', $request->user()->id)
            ->where('type', $type)
            ->whereIn('status', [MasterImport::STATUS_QUEUED, MasterImport::STATUS_VALIDATING, MasterImport::STATUS_VALIDATED])
            ->get()
            ->each(fn (MasterImport $old) => $this->cancelImport($old, 'Digantikan oleh upload file baru.'));

        $file = $request->file('file');
        $import = MasterImport::create([
            'user_id' => $request->user()->id,
            'type' => $type,
            'status' => MasterImport::STATUS_QUEUED,
            'original_filename' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_path' => $file->store('imports', 'local'),
        ]);

        ValidateMasterImport::dispatch($import->id);

        return response()->json($this->serialize($import->fresh()), 201);
    }

    public function latest(Request $request, string $type): JsonResponse
    {
        $import = MasterImport::where('user_id', $request->user()->id)
            ->where('type', $type)
            ->where(fn ($query) => $query->whereIn('status', MasterImport::OPEN_STATUSES)
                ->orWhere('finished_at', '>=', now()->subMinutes(10)))
            ->latest('id')
            ->first();

        return response()->json($import ? $this->serialize($import) : null);
    }

    public function show(MasterImport $masterImport): JsonResponse
    {
        return response()->json($this->serialize($masterImport));
    }

    public function commit(MasterImport $masterImport): JsonResponse
    {
        if (! $this->canCommit($masterImport)) {
            return response()->json(['message' => 'Import ini tidak bisa disimpan (belum selesai divalidasi, sudah disimpan, atau tidak ada baris valid).'], 422);
        }

        if ($blocking = $this->otherCommitInProgress($masterImport->type, $masterImport->id)) {
            return $this->conflict($blocking);
        }

        MasterImportRunner::progressCache()->forget(MasterImportRunner::cancelKey($masterImport));
        $masterImport->update([
            'status' => MasterImport::STATUS_COMMIT_QUEUED,
            'cancel_requested' => false,
            'error_message' => null,
            'finished_at' => null,
            'processed_rows' => 0,
        ]);

        CommitMasterImport::dispatch($masterImport->id);

        return response()->json($this->serialize($masterImport->fresh()));
    }

    public function cancel(MasterImport $masterImport): JsonResponse
    {
        if (in_array($masterImport->status, MasterImport::FINAL_STATUSES, true) && $masterImport->status !== MasterImport::STATUS_FAILED) {
            return response()->json($this->serialize($masterImport));
        }

        $this->cancelImport($masterImport, 'Import dibatalkan oleh pengguna. Tidak ada data yang tersimpan.');

        return response()->json($this->serialize($masterImport->fresh()));
    }

    public function issues(MasterImport $masterImport): BinaryFileResponse
    {
        $name = pathinfo($masterImport->original_filename, PATHINFO_FILENAME);

        return Excel::download(new MasterImportIssuesExport($masterImport), "laporan-import-{$name}.xlsx");
    }

    /** Running jobs are told to stop (and roll back); anything not yet picked up is finished right away. */
    private function cancelImport(MasterImport $import, string $message): void
    {
        if (in_array($import->status, [MasterImport::STATUS_VALIDATING, MasterImport::STATUS_COMMITTING], true)) {
            $import->update(['cancel_requested' => true]);
            MasterImportRunner::progressCache()->put(MasterImportRunner::cancelKey($import), true, 3600);

            return;
        }

        $this->runner->finish($import, MasterImport::STATUS_CANCELLED, $message);
    }

    private function canCommit(MasterImport $import): bool
    {
        $retryAfterFailedCommit = $import->status === MasterImport::STATUS_FAILED && $import->validated_at !== null && $import->committed_at !== null;

        return ($import->status === MasterImport::STATUS_VALIDATED || $retryAfterFailedCommit) && $import->valid_rows > 0;
    }

    private function otherCommitInProgress(string $type, ?int $exceptId = null): ?MasterImport
    {
        return MasterImport::with('user:id,name')
            ->where('type', $type)
            ->whereIn('status', [MasterImport::STATUS_COMMIT_QUEUED, MasterImport::STATUS_COMMITTING])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->first();
    }

    private function conflict(MasterImport $blocking): JsonResponse
    {
        $by = $blocking->user?->name ?? 'pengguna lain';

        return response()->json(['message' => "Import lain ({$blocking->original_filename}) sedang disimpan oleh {$by}. Tunggu hingga selesai lalu coba lagi."], 409);
    }

    /** @return array<string, mixed> */
    private function serialize(MasterImport $import): array
    {
        $processedRows = $import->processed_rows;
        if ($import->status === MasterImport::STATUS_COMMITTING) {
            $processedRows = MasterImportRunner::progressCache()->get(MasterImportRunner::progressKey($import))['processed'] ?? $processedRows;
        }

        $issues = fn (string $severity) => $import->issues()
            ->where('severity', $severity)
            ->orderBy('row_number')
            ->limit(self::ISSUES_PREVIEW_LIMIT)
            ->get(['row_number', 'message', 'values']);

        $hasIssues = $import->error_rows + $import->warning_rows > 0 || $import->issues()->exists();

        return [
            'id' => $import->id,
            'type' => $import->type,
            'status' => $import->status,
            'original_filename' => $import->original_filename,
            'total_rows' => $import->total_rows,
            'processed_rows' => $processedRows,
            'valid_rows' => $import->valid_rows,
            'error_rows' => $import->error_rows,
            'warning_rows' => $import->warning_rows,
            'preview' => $import->preview,
            'result' => $import->result,
            'error_message' => $import->error_message,
            'cancel_requested' => $import->cancel_requested,
            'can_commit' => $this->canCommit($import),
            // How long a job has sat in the queue unclaimed; the dialog warns when no worker seems to be running.
            'waiting_seconds' => in_array($import->status, [MasterImport::STATUS_QUEUED, MasterImport::STATUS_COMMIT_QUEUED], true)
                ? (int) $import->updated_at->diffInSeconds(now(), true)
                : 0,
            'columns' => MasterImportRunner::processorFor($import->type)->columns(),
            'issues' => $hasIssues ? [
                'errors' => $issues(MasterImportIssue::SEVERITY_ERROR),
                'warnings' => $issues(MasterImportIssue::SEVERITY_WARNING),
                'error_count' => $import->issues()->where('severity', MasterImportIssue::SEVERITY_ERROR)->count(),
                'warning_count' => $import->issues()->where('severity', MasterImportIssue::SEVERITY_WARNING)->count(),
                'limit' => self::ISSUES_PREVIEW_LIMIT,
            ] : null,
            'issues_url' => $hasIssues ? route('master-imports.issues', $import) : null,
            'created_at' => $import->created_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ];
    }
}
