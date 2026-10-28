import { ApprovalActionCard, InfoField, PrintPreviewButton, type StageInfo } from '@/components/ipc/approval-report';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { lineLabel } from '@/lib/batch-line';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface Batch {
    id: number;
    no_batch: string;
    bulk_code: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string } | null;
}

/**
 * Flow Lengkap: one Approve/Reject decision for the combined Fillpack + FG form (FR.QAC.193.00),
 * saved onto the Filling, Packing and Finished approvals alike. The PDF is the document being
 * decided on, so the preview button sits in the header.
 */
export default function ApprovalFillpack({ batch, stage, decisions }: { batch: Batch; stage: StageInfo; decisions: string[] }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    return (
        <IpcShell
            title={stage.label}
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}/approval`}
            headerActions={<PrintPreviewButton href={`/batches/${batch.id}/approval/filling_packing_fg/print`} />}
        >
            <Head title={`${stage.label} — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-6 md:px-8">
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <InfoField label="No. Batch" value={batch.no_batch} />
                        <InfoField label="FG Code" value={batch.master_product.fg_code} />
                        <InfoField label="Bulk Code" value={batch.bulk_code} />
                        <InfoField label="Line" value={lineLabel(batch.master_line)} />
                        <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                    </div>

                    <p className="text-muted-foreground/70 text-[12.5px] font-medium">
                        Form gabungan FR.QAC.193.00 ({stage.label}). Buka Preview Cetak di kanan atas untuk memeriksa dokumennya, lalu putuskan di
                        bawah — satu keputusan berlaku untuk semua tahap di dalamnya.
                    </p>

                    <ApprovalActionCard batchId={batch.id} decisions={decisions} stage={stage} />
                </div>
            </TwoPane>
        </IpcShell>
    );
}
