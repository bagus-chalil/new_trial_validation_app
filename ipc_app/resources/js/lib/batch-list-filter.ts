import { type BatchFilterValues } from '@/components/ipc/batch-filter-bar';

// The filter last applied on the Batch list (stage + filter bar). Batch pages mirror it in their side
// list, so opening a batch from a filtered list does not silently drop the filter. Kept in
// sessionStorage: it survives Inertia visits and reloads of the tab, and dies with the tab.
const KEY = 'ipc.batchListFilter';

const KEYS = ['stage', 'date_from', 'date_to', 'created_by', 'master_line_id', 'flow'] as const;

export type BatchListFilter = Partial<Record<(typeof KEYS)[number], string>>;

export function readBatchListFilter(): BatchListFilter {
    try {
        const raw = JSON.parse(window.sessionStorage.getItem(KEY) ?? '{}');
        return clean(raw);
    } catch {
        return {};
    }
}

export function saveBatchListFilter(filters: BatchFilterValues): void {
    try {
        const next = clean(filters);
        if (Object.keys(next).length === 0) window.sessionStorage.removeItem(KEY);
        else window.sessionStorage.setItem(KEY, JSON.stringify(next));
    } catch {
        // storage blocked: the side list just falls back to the newest batches
    }
}

export function clearBatchListFilter(): void {
    saveBatchListFilter({});
}

function clean(source: unknown): BatchListFilter {
    const out: BatchListFilter = {};
    if (typeof source !== 'object' || source === null) return out;
    for (const key of KEYS) {
        const value = (source as Record<string, unknown>)[key];
        if (value !== undefined && value !== null && value !== '') out[key] = String(value);
    }
    return out;
}
