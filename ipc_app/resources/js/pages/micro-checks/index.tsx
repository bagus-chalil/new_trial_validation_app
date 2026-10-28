import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, FlaskConical } from 'lucide-react';

interface QueueBatch {
    id: number;
    no_batch: string;
    product_name: string | null;
    fg_code: string | null;
    line: string | null;
    filled: boolean;
    filled_at: string | null;
}

interface PaginatedBatches {
    data: QueueBatch[];
    links: { url: string | null; label: string; active: boolean }[];
    last_page: number;
    total: number;
}

export default function MicroCheckIndex({ batches }: { batches: PaginatedBatches }) {
    return (
        <IpcShell title="Micro Check" subtitle={`${batches.total} batch menunggu Micro Check`} backHref="/dashboard">
            <Head title="Micro Check" />
            <div className="flex flex-1 flex-col gap-3.5 overflow-y-auto p-5 md:p-6">
                {batches.data.map((batch) => (
                    <Link
                        key={batch.id}
                        href={`/batches/${batch.id}/micro-check`}
                        className="border-border-soft bg-card hover:border-primary/40 flex flex-col gap-3 rounded-[20px] border p-4 transition-colors"
                    >
                        <div className="flex items-start justify-between gap-2.5">
                            <div className="min-w-0">
                                <p className="text-[16.5px] font-bold tracking-tight">{batch.no_batch}</p>
                                <p className="text-muted-foreground mt-0.5 truncate text-[13px] font-medium">
                                    {batch.product_name} &middot; {batch.fg_code}
                                </p>
                            </div>
                            <ChevronRight className="text-muted-foreground/60 mt-1 size-[18px] shrink-0" strokeWidth={2} />
                        </div>
                        <p className="text-muted-foreground/70 text-[12.5px] font-medium">{batch.line ?? '—'}</p>
                        <span
                            className={`w-fit rounded-full px-3 py-1 text-[12px] font-bold ${batch.filled ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'}`}
                        >
                            {batch.filled ? 'Progress tersimpan — belum diselesaikan' : 'Belum diisi'}
                        </span>
                    </Link>
                ))}

                {batches.data.length === 0 && (
                    <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                        <FlaskConical className="text-muted-foreground/60 size-8" />
                        <p className="text-muted-foreground text-sm">Belum ada batch dengan Finished Good selesai yang menunggu Micro Check.</p>
                    </div>
                )}

                <PaginationFooter links={batches.links} lastPage={batches.last_page} />
            </div>
        </IpcShell>
    );
}
