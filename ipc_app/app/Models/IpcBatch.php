<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpcBatch extends Model
{
    use SoftDeletes;

    public const STAGE_STARTUP = 'startup';

    public const STAGE_FILLING = 'filling';

    public const STAGE_PACKING = 'packing';

    public const STAGE_FINISHED = 'finished';

    public const STAGE_APPROVAL = 'approval';

    public const STAGE_PRINT = 'print';

    public const STAGE_COMPLETED = 'completed';

    public const STAGES = [
        self::STAGE_STARTUP,
        self::STAGE_FILLING,
        self::STAGE_PACKING,
        self::STAGE_FINISHED,
        self::STAGE_APPROVAL,
        self::STAGE_PRINT,
        self::STAGE_COMPLETED,
    ];

    protected $fillable = [
        'master_product_id',
        'master_product_bulk_code_id',
        'no_batch',
        'mixing_date',
        'exp_date',
        'bulk_code',
        'master_line_id',
        'created_by',
        'current_stage',
    ];

    protected $casts = [
        'mixing_date' => 'date',
        'exp_date' => 'date',
    ];

    public function masterProduct()
    {
        return $this->belongsTo(MasterProduct::class);
    }

    public function bulkCode()
    {
        return $this->belongsTo(MasterProductBulkCode::class, 'master_product_bulk_code_id');
    }

    /** Every bulk code this batch was filled from (`bulkCode()` is only the first one). */
    public function bulkCodes()
    {
        return $this->hasMany(IpcBatchBulkCode::class);
    }

    public function masterLine()
    {
        return $this->belongsTo(MasterLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function startupCheck()
    {
        return $this->hasOne(StartupCheck::class);
    }

    public function startupInspection()
    {
        return $this->hasOne(StartupInspection::class);
    }

    public function fillingCheck()
    {
        return $this->hasOne(FillingCheck::class);
    }

    public function packingCheck()
    {
        return $this->hasOne(PackingCheck::class);
    }

    public function finishedCheck()
    {
        return $this->hasOne(FinishedCheck::class);
    }

    public function approvals()
    {
        return $this->hasMany(IpcApproval::class);
    }

    public function printLogs()
    {
        return $this->hasMany(IpcPrintLog::class);
    }

    public function attachments()
    {
        return $this->hasMany(IpcAttachment::class);
    }

    /**
     * Batch search: no_batch prefix OR product-name contains, built so it stays index-driven
     * at ~1M batches. A single `WHERE no_batch LIKE .. OR master_product_id IN (..)` defeats
     * every index — a search with no hits scanned the whole table (2.6s measured) — so each
     * side runs as its own indexed query and only the ids are merged.
     *
     * @return array{0: Builder, 1: Builder} [by no_batch, by product name], unordered
     */
    public static function searchBranches(string $q, ?string $stage = null): array
    {
        $base = fn () => static::query()->when($stage, fn ($query) => $query->where('current_stage', $stage));

        return [
            $base()->where('no_batch', 'like', static::escapeLike($q).'%'),
            $base()->whereIn('master_product_id', static::productIdsMatching($q)),
        ];
    }

    private static function productIdsMatching(string $q): Builder
    {
        return MasterProduct::withTrashed()->select('id')->where('product_name', 'like', '%'.static::escapeLike($q).'%');
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** Ids of the newest matching batches, newest first: `$take` rows after skipping `$skip`. */
    public static function searchIds(string $q, ?string $stage, int $take, int $skip = 0): array
    {
        [$byNo, $byProduct] = static::searchBranches($q, $stage);
        $n = $skip + $take;

        return $byNo->select('id')->orderByDesc('id')->limit($n)
            // toBase() so the soft-delete scope is applied to this branch too — union() on its
            // own takes the raw query and silently drops Eloquent global scopes.
            ->union($byProduct->select('id')->orderByDesc('id')->limit($n)->toBase())
            ->orderByDesc('id')
            ->offset($skip)
            ->limit($take)
            ->pluck('id')
            ->all();
    }

    /** Exact match count as |A| + |B| - |A∩B| — three index-only counts, no big dedupe. */
    public static function searchCount(string $q, ?string $stage): int
    {
        [$byNo, $byProduct] = static::searchBranches($q, $stage);

        return (clone $byNo)->count()
            + $byProduct->count()
            - $byNo->whereIn('master_product_id', static::productIdsMatching($q))->count();
    }

    /**
     * SQL twin of the Approval Queue's rule (Finished Check done + IpcApproval::pendingStagesFor()
     * non-empty), so the queue/dashboard can filter, count and paginate in the database instead
     * of loading every finished batch into PHP.
     *
     * Candidate narrowing (keeps it index-driven at ~1M batches): a batch only reaches
     * print/completed once all three stages are Approved (SaveApproval), so a print/completed
     * batch can be pending again only if one of its approvals was later re-decided to
     * something other than Approved. Everything else is still in active work.
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        $approvedFor = fn (string $stage) => fn ($q) => $q->where('stage', $stage)->where('decision', IpcApproval::DECISION_APPROVED);
        $completed = fn ($q) => $q->whereNotNull('completed_at');

        return $query
            ->where(function ($query) {
                $query->whereNotIn('current_stage', [self::STAGE_PRINT, self::STAGE_COMPLETED])
                    ->orWhereIn('id', IpcApproval::query()
                        ->select('ipc_batch_id')
                        ->where(fn ($q) => $q->where('decision', '!=', IpcApproval::DECISION_APPROVED)->orWhereNull('decision')));
            })
            ->whereHas('finishedCheck', $completed)
            ->where(function ($query) use ($approvedFor, $completed) {
                $query
                    ->where(fn ($q) => $q->whereHas('startupCheck', $completed)
                        ->whereDoesntHave('approvals', $approvedFor(IpcApproval::STAGE_STARTUP)))
                    ->orWhere(fn ($q) => $q->whereHas('fillingCheck', $completed)
                        ->whereHas('packingCheck', $completed)
                        ->whereDoesntHave('approvals', $approvedFor(IpcApproval::STAGE_FILLING_PACKING)))
                    ->orWhereDoesntHave('approvals', $approvedFor(IpcApproval::STAGE_FINISHED));
            });
    }
}
