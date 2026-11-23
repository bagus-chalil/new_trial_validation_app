import { FlowBadge } from '@/components/ipc/flow-identity';
import { Input } from '@/components/ui/input';
import { clearBatchListFilter, readBatchListFilter } from '@/lib/batch-list-filter';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { cn } from '@/lib/utils';
import { type RecentBatch } from '@/types';
import { Link } from '@inertiajs/react';
import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

// Every page renders its own BatchNavList, so each Inertia visit remounts it and its scroll box
// would jump back to the top. Keep the last position at module level (survives SPA navigation)
// and restore it on mount.
let savedListScrollTop = 0;

export function BatchNavList({ batches, activeId }: { batches: RecentBatch[]; activeId?: number }) {
    const [q, setQ] = useState('');
    // The filter the user applied on the Batch list; the side list keeps showing that subset.
    const [listFilter, setListFilter] = useState(readBatchListFilter);
    const hasFilter = Object.keys(listFilter).length > 0;
    const [results, setResults] = useState<RecentBatch[] | null>(null);
    const [loading, setLoading] = useState(false);
    const scrollRef = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => {
        const box = scrollRef.current;
        if (!box) return;
        box.scrollTop = savedListScrollTop;
        // If the active card ended up outside the visible area (e.g. opened from elsewhere), bring it in.
        const active = box.querySelector<HTMLElement>('[aria-current="page"]');
        if (active) {
            const boxRect = box.getBoundingClientRect();
            const rect = active.getBoundingClientRect();
            if (rect.top < boxRect.top || rect.bottom > boxRect.bottom) {
                box.scrollTop += rect.top - boxRect.top - (box.clientHeight - rect.height) / 2;
            }
        }
        // Only on mount: later re-renders must not fight the user's own scrolling.
    }, []);

    // `batches` is only the 20 most recent; a typed search goes to the server so older batches
    // are findable too, without ever shipping the full table to the tablet.
    useEffect(() => {
        const needle = q.trim();
        if (!needle && !hasFilter) {
            setResults(null);
            setLoading(false);
            return;
        }
        const controller = new AbortController();
        const timer = setTimeout(
            async () => {
                setLoading(true);
                try {
                    const response = await fetch(route('lookup.batches', { ...listFilter, q: needle }), {
                        signal: controller.signal,
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                    });
                    if (response.ok) setResults(await response.json());
                } catch {
                    // aborted or offline — keep the previous results
                } finally {
                    if (!controller.signal.aborted) setLoading(false);
                }
            },
            needle ? 300 : 0,
        );
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q, listFilter, hasFilter]);

    // First arrival of the filtered list: bring the open batch into view if it is in it.
    const scrolledToActive = useRef(false);
    useEffect(() => {
        if (results === null || scrolledToActive.current) return;
        scrolledToActive.current = true;
        scrollRef.current?.querySelector<HTMLElement>('[aria-current="page"]')?.scrollIntoView({ block: 'center' });
    }, [results]);

    const filtered = results ?? (hasFilter ? [] : batches);

    return (
        <>
            <div className="border-border-soft border-b p-3.5">
                <p className="text-muted-foreground mb-3 text-xs font-bold tracking-wide uppercase">Navigasi batch</p>
                <div className="relative">
                    <Search className="text-muted-foreground/60 absolute top-1/2 left-3 size-4 -translate-y-1/2" strokeWidth={2} />
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        aria-label="Cari batch"
                        placeholder="Cari batch / FG code..."
                        className="border-border-soft bg-background h-10 rounded-xl pl-9 text-[13px]"
                    />
                    {loading && <Loader2 className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin" />}
                </div>
                {hasFilter && (
                    <button
                        type="button"
                        onClick={() => {
                            clearBatchListFilter();
                            setListFilter({});
                            setResults(null);
                        }}
                        className="bg-primary/10 text-primary mt-2.5 flex w-full items-center justify-between gap-2 rounded-lg px-3 py-1.5 text-left text-xs font-semibold"
                    >
                        <span className="truncate">Filter aktif: {describeFilter(listFilter)}</span>
                        <span className="flex shrink-0 items-center gap-1">
                            Lepas <X className="size-3.5" />
                        </span>
                    </button>
                )}
            </div>

            <div
                ref={scrollRef}
                onScroll={(e) => {
                    // Search results are a different list; only remember the position of the recent list.
                    if (!q.trim()) savedListScrollTop = e.currentTarget.scrollTop;
                }}
                className="min-h-0 flex-1 overflow-y-auto px-3 py-3.5"
            >
                <div className="flex flex-col gap-2">
                    {filtered.map((batch) => {
                        const active = activeId === batch.id;
                        return (
                            <Link
                                key={batch.id}
                                href={`/batches/${batch.id}`}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'rounded-2xl border p-[13px] transition-colors',
                                    active
                                        ? 'border-primary/50 bg-primary/[0.07] shadow-sm'
                                        : 'border-border-soft bg-card hover:border-primary/30 hover:bg-primary/[0.025]',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className={cn('truncate text-[14px] font-bold', active && 'text-primary')}>{batch.no_batch}</span>
                                    <span
                                        className="shrink-0 rounded-full px-[9px] py-[3px] text-[10.5px] font-bold"
                                        style={stageBadgeStyle(batch.current_stage)}
                                    >
                                        {stageLabel(batch.current_stage)}
                                    </span>
                                </div>
                                <p className="text-foreground/80 mt-1 text-[11.5px] font-semibold tabular-nums">
                                    {batch.master_product?.fg_code ?? '—'}
                                </p>
                                <p className="text-muted-foreground truncate text-[11.5px] font-medium">
                                    {batch.master_product?.product_name ?? '—'}
                                </p>
                                <FlowBadge type={batch.flow_type} className="mt-2 text-[10px]" />
                            </Link>
                        );
                    })}

                    {filtered.length === 0 && !loading && <p className="text-muted-foreground p-4 text-center text-sm">Tidak ada batch.</p>}
                </div>
            </div>
        </>
    );
}

function describeFilter(filter: Record<string, string>): string {
    const parts: string[] = [];
    if (filter.stage) parts.push(stageLabel(filter.stage));
    if (filter.flow) parts.push(filter.flow);
    if (filter.date_from || filter.date_to) parts.push('tanggal');
    if (filter.master_line_id) parts.push('line');
    if (filter.created_by) parts.push('pembuat');
    return parts.join(', ');
}
