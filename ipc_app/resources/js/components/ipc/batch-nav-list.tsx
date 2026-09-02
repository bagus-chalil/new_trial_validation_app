import { Input } from '@/components/ui/input';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { cn } from '@/lib/utils';
import { type RecentBatch } from '@/types';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

export function BatchNavList({ batches, activeId }: { batches: RecentBatch[]; activeId?: number }) {
    const [q, setQ] = useState('');

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return batches;
        return batches.filter(
            (batch) => batch.no_batch.toLowerCase().includes(needle) || batch.master_product?.product_name.toLowerCase().includes(needle),
        );
    }, [batches, q]);

    return (
        <>
            <div className="p-3.5 pb-3">
                <div className="relative">
                    <Search className="text-muted-foreground/60 absolute top-1/2 left-3 size-4 -translate-y-1/2" strokeWidth={2} />
                    <Input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Cari batch..."
                        className="border-border-soft bg-background h-10 rounded-xl pl-9 text-[13px]"
                    />
                </div>
            </div>

            <div className="flex-1 overflow-y-auto px-3 pb-3.5">
                <div className="flex flex-col gap-2">
                    {filtered.map((batch) => {
                        const active = activeId === batch.id;
                        return (
                            <Link
                                key={batch.id}
                                href={`/batches/${batch.id}`}
                                className={cn(
                                    'rounded-2xl border p-[13px] transition-colors',
                                    active ? 'border-primary bg-primary/[0.05]' : 'border-border-soft bg-card hover:border-border',
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
                                <p className="text-muted-foreground mt-1 truncate text-[11.5px] font-medium">
                                    {batch.master_product?.product_name ?? '—'}
                                </p>
                            </Link>
                        );
                    })}

                    {filtered.length === 0 && <p className="text-muted-foreground p-4 text-center text-sm">Tidak ada batch.</p>}
                </div>
            </div>
        </>
    );
}
