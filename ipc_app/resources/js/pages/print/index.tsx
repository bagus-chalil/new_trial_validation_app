import { formatDateTime, type PrintInfo } from '@/components/ipc/approval-report';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, Clock3, Droplets, Package, Printer } from 'lucide-react';

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
}

const STAGE_META: Record<string, { href: (id: number) => string; icon: typeof Droplets; description: string }> = {
    startup: {
        href: (id) => `/batches/${id}/print/startup`,
        icon: Droplets,
        description: 'Start Up Inspection Form.',
    },
    filling_packing: {
        href: (id) => `/batches/${id}/print/filling-packing`,
        icon: Package,
        description: 'In Process Control Inspection Report — Filling & Packing.',
    },
    finished: {
        href: (id) => `/batches/${id}/print/finished`,
        icon: CheckCircle2,
        description: 'Finished Good Inspection Report.',
    },
};

function PrintBadge({ stage }: { stage: PrintInfo }) {
    if (stage.printCount === 0) return null;
    return (
        <span className="flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-[12px] font-bold text-green-800">
            <CheckCircle2 className="size-3.5" strokeWidth={2.4} />
            Dicetak {stage.printCount}x
        </span>
    );
}

function StageCard({ batchId, stage }: { batchId: number; stage: PrintInfo }) {
    const meta = STAGE_META[stage.stage];
    const Icon = meta.icon;

    return (
        <Link
            href={meta.href(batchId)}
            className="border-border-soft bg-card group hover:border-primary/40 flex flex-col gap-3.5 rounded-[22px] border p-5 transition-colors"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="bg-primary/[0.08] text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                    <Icon className="size-5" strokeWidth={2.2} />
                </div>
                <PrintBadge stage={stage} />
            </div>

            <div>
                <p className="text-[15.5px] font-bold">{stage.label}</p>
                <p className="text-muted-foreground/70 mt-1 text-[12.5px] font-medium">{meta.description}</p>
            </div>

            {stage.lastPrintedAt && (
                <p className="text-muted-foreground/70 text-[12px] font-medium">
                    Terakhir oleh {stage.lastPrintedBy ?? '—'} · {formatDateTime(stage.lastPrintedAt)}
                </p>
            )}

            <div className="mt-auto flex items-center justify-between gap-3 pt-1">
                <span
                    className={`flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold ${
                        stage.printCount > 0 ? 'bg-muted text-muted-foreground' : 'bg-blue-50 text-blue-700'
                    }`}
                >
                    {stage.printCount > 0 ? <Clock3 className="size-3.5" strokeWidth={2.4} /> : <Printer className="size-3.5" strokeWidth={2.4} />}
                    {stage.printCount > 0 ? 'Bisa dicetak ulang' : 'Belum dicetak'}
                </span>
                <span className="text-primary ml-auto flex items-center gap-1 text-[13px] font-bold">
                    Lihat Detail
                    <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" strokeWidth={2.4} />
                </span>
            </div>
        </Link>
    );
}

export default function PrintOverview({ batch, stages }: { batch: Batch; stages: PrintInfo[] }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    return (
        <IpcShell title="Print" subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`} backHref={`/batches/${batch.id}`}>
            <Head title={`Print — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-6 md:px-8">
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <div>
                            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">No. Batch</p>
                            <p className="mt-0.5 text-[13.5px] font-bold">{batch.no_batch}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">FG Code</p>
                            <p className="mt-0.5 text-[13.5px] font-bold">{batch.master_product.fg_code}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">Bulk Code</p>
                            <p className="mt-0.5 text-[13.5px] font-bold">{batch.master_product.bulk_code}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">Line</p>
                            <p className="mt-0.5 text-[13.5px] font-bold">
                                {batch.master_line.name} ({batch.master_line.code})
                            </p>
                        </div>
                    </div>

                    <p className="text-muted-foreground/70 -mb-1 text-[12.5px] font-medium">
                        Pilih salah satu tahap untuk melihat laporan lengkap dan mencetak.
                    </p>

                    <div className="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        {stages.map((stage) => (
                            <StageCard key={stage.stage} batchId={batch.id} stage={stage} />
                        ))}
                    </div>
                </div>
            </TwoPane>
        </IpcShell>
    );
}
