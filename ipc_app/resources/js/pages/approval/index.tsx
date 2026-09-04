import { formatDateTime, InfoField, type ApprovalData, type StageInfo } from '@/components/ipc/approval-report';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, Clock3, Droplets, Package, XCircle } from 'lucide-react';

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
}

const STAGE_META: Record<string, { href: (id: number) => string; icon: typeof Droplets; description: string }> = {
    startup: {
        href: (id) => `/batches/${id}/approval/startup`,
        icon: Droplets,
        description: 'Start Up Inspection Form + Start Inspection (checklist, sample, test type).',
    },
    filling_packing: {
        href: (id) => `/batches/${id}/approval/filling-packing`,
        icon: Package,
        description: 'In Process Control Inspection Report — Filling & Packing.',
    },
    finished: {
        href: (id) => `/batches/${id}/approval/finished`,
        icon: CheckCircle2,
        description: 'Finished Good Inspection Report — AQL sampling & disposisi.',
    },
};

function DecisionBadge({ approval }: { approval: ApprovalData | null }) {
    if (!approval?.decision) return null;
    const approved = approval.decision === 'Approved';
    return (
        <span
            className={`flex items-center gap-1 rounded-full px-3 py-1 text-[12px] font-bold ${
                approved ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'
            }`}
        >
            {approved ? <CheckCircle2 className="size-3.5" strokeWidth={2.4} /> : <XCircle className="size-3.5" strokeWidth={2.4} />}
            {approval.decision}
        </span>
    );
}

function StageCard({ batchId, stage }: { batchId: number; stage: StageInfo }) {
    const meta = STAGE_META[stage.stage];
    const Icon = meta.icon;

    return (
        <Link
            href={meta.href(batchId)}
            className="border-border-soft bg-card group flex flex-col gap-3.5 rounded-[22px] border p-5 transition-colors hover:border-primary/40"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="bg-primary/[0.08] text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                    <Icon className="size-5" strokeWidth={2.2} />
                </div>
                <DecisionBadge approval={stage.approval} />
            </div>

            <div>
                <p className="text-[15.5px] font-bold">{stage.label}</p>
                <p className="text-muted-foreground/70 mt-1 text-[12.5px] font-medium">{meta.description}</p>
            </div>

            {stage.approval && (
                <p className="text-muted-foreground/70 text-[12px] font-medium">
                    Oleh {stage.approval.approver?.name ?? '—'} · {formatDateTime(stage.approval.approved_at)}
                </p>
            )}

            <div className="mt-auto flex items-center justify-between gap-3 pt-1">
                {!stage.approval?.decision && (
                    <span
                        className={`flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold ${
                            stage.ready ? 'bg-blue-50 text-blue-700' : 'bg-muted text-muted-foreground'
                        }`}
                    >
                        {stage.ready ? <CheckCircle2 className="size-3.5" strokeWidth={2.4} /> : <Clock3 className="size-3.5" strokeWidth={2.4} />}
                        {stage.ready ? 'Siap diputuskan' : 'Menunggu tahap selesai'}
                    </span>
                )}
                <span className="text-primary ml-auto flex items-center gap-1 text-[13px] font-bold">
                    Lihat Detail
                    <ChevronRight className="size-4 transition-transform group-hover:translate-x-0.5" strokeWidth={2.4} />
                </span>
            </div>
        </Link>
    );
}

export default function ApprovalOverview({ batch, stages }: { batch: Batch; stages: StageInfo[] }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    return (
        <IpcShell title="Approval" subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`} backHref={`/batches/${batch.id}`}>
            <Head title={`Approval — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-6 md:px-8">
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <InfoField label="No. Batch" value={batch.no_batch} />
                        <InfoField label="FG Code" value={batch.master_product.fg_code} />
                        <InfoField label="Bulk Code" value={batch.master_product.bulk_code} />
                        <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                        <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                    </div>

                    <p className="text-muted-foreground/70 -mb-1 text-[12.5px] font-medium">
                        Pilih salah satu tahap untuk melihat detail lengkap, memutuskan Approve/Reject, dan preview cetak.
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
