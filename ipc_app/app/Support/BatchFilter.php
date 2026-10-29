<?php

namespace App\Support;

use App\Models\IpcBatch;
use App\Models\MasterLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The shared "filter" bar of the batch-shaped lists (Batch, Micro Check, Approval Queue):
 * created-date range, creator, line and flow. Every value is optional and sanitized, so a
 * hand-edited query string can never reach the query as anything but a plain scalar.
 */
class BatchFilter
{
    public function __construct(
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?int $createdBy = null,
        public readonly ?int $lineId = null,
        public readonly ?string $flow = null,
    ) {}

    /** @param  list<string>  $allowedFlows  flows this list can actually contain */
    public static function fromRequest(Request $request, array $allowedFlows = IpcBatch::FLOW_TYPES): self
    {
        $from = self::date($request->query('date_from'));
        $to = self::date($request->query('date_to'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $flow = $request->query('flow');

        return new self(
            dateFrom: $from,
            dateTo: $to,
            createdBy: self::id($request->query('created_by')),
            lineId: self::id($request->query('master_line_id')),
            flow: is_string($flow) && in_array($flow, $allowedFlows, true) ? $flow : null,
        );
    }

    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->dateFrom, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($this->dateTo, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($this->createdBy, fn ($q, $id) => $q->where('created_by', $id))
            ->when($this->lineId, fn ($q, $id) => $q->where('master_line_id', $id))
            ->when($this->flow, fn ($q, $flow) => $q->where('flow_type', $flow));
    }

    /** What the page echoes back so the bar can show the current values (only non-empty keys). */
    public function toArray(): array
    {
        return array_filter([
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'created_by' => $this->createdBy,
            'master_line_id' => $this->lineId,
            'flow' => $this->flow,
        ], fn ($value) => $value !== null);
    }

    /**
     * Dropdown choices for the bar. Creators are the people who can create batches (Staff/Admin),
     * not every user — and not DISTINCT created_by over the batches table, which is ~1M rows.
     *
     * @param  list<string>  $flows
     */
    public static function options(array $flows = IpcBatch::FLOW_TYPES): array
    {
        return [
            'lines' => MasterLine::query()->orderBy('name')->get(['id', 'name', 'code']),
            'creators' => User::query()
                ->whereHas('roleRecord', fn ($q) => $q->whereIn('code', [User::ROLE_STAFF, User::ROLE_ADMIN]))
                ->orderBy('name')
                ->get(['id', 'name']),
            'flows' => collect($flows)
                ->map(fn (string $flow) => ['value' => $flow, 'label' => IpcBatch::FLOW_SHORT_LABELS[$flow]])
                ->values(),
        ];
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::canBeCreatedFromFormat($value, 'Y-m-d') ? $value : null;
    }

    private static function id(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && $value !== '0' && strlen($value) < 10 ? (int) $value : null;
    }
}
