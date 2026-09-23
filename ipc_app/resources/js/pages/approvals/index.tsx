import { IpcShell } from '@/layouts/ipc-shell';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, ClipboardCheck } from 'lucide-react';

interface QueueBatch {
    id: number;
    no_batch: string;
    bulk_code: string | null;
    master_product: { product_name: string; fg_code: string } | null;
    master_line: { name: string } | null;
}

interface QueueEntry {
    batch: QueueBatch;
    pendingStages: string[];
}

export default function ApprovalQueueIndex({ queue }: { queue: QueueEntry[] }) {
    return (
        <IpcShell title="Approval Queue" subtitle={`${queue.length} batch menunggu keputusan`} backHref="/dashboard">
            <Head title="Approval Queue" />
            <div className="flex flex-1 flex-col gap-3.5 overflow-y-auto p-5 md:p-6">
                {queue.map((entry) => (
                    <Link
                        key={entry.batch.id}
                        href={`/batches/${entry.batch.id}/approval`}
                        className="border-border-soft bg-card hover:border-primary/40 flex flex-col gap-3 rounded-[20px] border p-4 transition-colors"
                    >
                        <div className="flex items-start justify-between gap-2.5">
                            <div className="min-w-0">
                                <p className="text-[16.5px] font-bold tracking-tight">{entry.batch.no_batch}</p>
                                <p className="text-muted-foreground mt-0.5 truncate text-[13px] font-medium">
                                    {entry.batch.master_product?.product_name} &middot; {entry.batch.master_product?.fg_code}
                                </p>
                            </div>
                            <ChevronRight className="text-muted-foreground/60 mt-1 size-[18px] shrink-0" strokeWidth={2} />
                        </div>

                        <p className="text-muted-foreground/70 text-[12.5px] font-medium">{entry.batch.master_line?.name ?? '—'}</p>

                        <div className="flex flex-wrap gap-1.5">
                            {entry.pendingStages.map((label) => (
                                <span
                                    key={label}
                                    className="flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-[12px] font-bold text-blue-700"
                                >
                                    <ClipboardCheck className="size-3.5" strokeWidth={2.4} />
                                    {label}
                                </span>
                            ))}
                        </div>

                        <span className="text-primary mt-1 flex items-center gap-1 text-[13px] font-bold">Tinjau</span>
                    </Link>
                ))}

                {queue.length === 0 && (
                    <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                        <ClipboardCheck className="text-muted-foreground/60 size-8" />
                        <p className="text-muted-foreground text-sm">Tidak ada batch yang menunggu approval.</p>
                    </div>
                )}
            </div>
        </IpcShell>
    );
}
