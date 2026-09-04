import { AccordionCard } from '@/components/ipc/accordion-card';
import {
    ApprovalActionCard,
    ChecklistRow,
    EmptyNote,
    InfoField,
    PhotoRow,
    PrintPreviewButton,
    type SampleGroup,
    type StageInfo,
} from '@/components/ipc/approval-report';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
}

interface FinishedSampleRow {
    ac: number | null;
    cd: number | null;
    md: number | null;
    mnd: number | null;
}

interface FinishedCheckData {
    quantity_wi: string | null;
    masterbox: string | null;
    no_pallet_qty: string | null;
    quantity_sampling_aql: string | null;
    quantity_sample_aql_cd: string | null;
    quantity_sample_aql_md: string | null;
    quantity_sample_aql_mnd: string | null;
    quantity_special_inspection: string | null;
    quantity_special_inspection_cd: string | null;
    quantity_special_inspection_md: string | null;
    quantity_special_inspection_mnd: string | null;
    line_leader_name: string | null;
    disposition: string | null;
    remarks: string | null;
    samples: (FinishedSampleRow & { parameter_key: string })[];
    user?: { name: string } | null;
}

export default function ApprovalFinished({
    batch,
    finishedCheck,
    photoUrls,
    stage,
    decisions,
    finishedSampleGroups,
}: {
    batch: Batch;
    finishedCheck: FinishedCheckData | null;
    photoUrls: Record<string, Record<string, string | null>>;
    stage: StageInfo;
    decisions: string[];
    finishedSampleGroups: SampleGroup[];
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const finishedSamplesByKey = Object.fromEntries((finishedCheck?.samples ?? []).map((s) => [s.parameter_key, s]));

    return (
        <IpcShell
            title="Finished Good Inspection Report"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}/approval`}
            headerActions={<PrintPreviewButton href={`/batches/${batch.id}/approval/finished/print`} />}
        >
            <Head title={`Finished Good Report — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-6 md:px-8">
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <InfoField label="No. Batch" value={batch.no_batch} />
                        <InfoField label="FG Code" value={batch.master_product.fg_code} />
                        <InfoField label="Bulk Code" value={batch.master_product.bulk_code} />
                        <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                    </div>

                    {finishedCheck ? (
                        <>
                            <AccordionCard title="Identity of Product" defaultOpen={false}>
                                <PhotoRow
                                    photos={[
                                        { key: 'wi_number', label: 'WI Number', url: photoUrls.finished?.wi_number ?? null },
                                        { key: 'exp_date', label: 'Exp Date', url: photoUrls.finished?.exp_date ?? null },
                                        { key: 'color', label: 'Color Test', url: photoUrls.finished?.color ?? null },
                                    ]}
                                />
                                <InfoField label="Quantity WI" value={finishedCheck.quantity_wi ?? '—'} />
                                <InfoField label="Masterbox" value={finishedCheck.masterbox ?? '—'} />
                                <InfoField label="No. Pallet & Qty" value={finishedCheck.no_pallet_qty ?? '—'} />
                                <InfoField
                                    label="Qty Sampling AQL"
                                    value={`${finishedCheck.quantity_sampling_aql ?? '—'} (CD ${finishedCheck.quantity_sample_aql_cd ?? '—'} / MD ${finishedCheck.quantity_sample_aql_md ?? '—'} / mD ${finishedCheck.quantity_sample_aql_mnd ?? '—'})`}
                                    full
                                />
                                <InfoField
                                    label="Qty Special Inspection"
                                    value={`${finishedCheck.quantity_special_inspection ?? '—'} (CD ${finishedCheck.quantity_special_inspection_cd ?? '—'} / MD ${finishedCheck.quantity_special_inspection_md ?? '—'} / mD ${finishedCheck.quantity_special_inspection_mnd ?? '—'})`}
                                    full
                                />
                            </AccordionCard>

                            {finishedSampleGroups.map((group) => (
                                <AccordionCard key={group.key} title={`Quantity Sample ${group.label}`} defaultOpen={false}>
                                    <div className="col-span-full overflow-x-auto">
                                        <table className="w-full text-[12.5px]">
                                            <thead>
                                                <tr className="text-muted-foreground/70 border-border-soft border-b text-left uppercase">
                                                    <th className="py-1.5 pr-2 font-semibold">Parameter</th>
                                                    <th className="py-1.5 pr-2 font-semibold">AC</th>
                                                    <th className="py-1.5 pr-2 font-semibold">CD</th>
                                                    <th className="py-1.5 pr-2 font-semibold">MD</th>
                                                    <th className="py-1.5 pr-2 font-semibold">mD</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {Object.entries(group.parameters).map(([key, label]) => {
                                                    const row = finishedSamplesByKey[key];
                                                    return (
                                                        <tr key={key} className="border-border-soft border-b last:border-0">
                                                            <td className="py-1.5 pr-2 font-semibold">{label}</td>
                                                            <td className="py-1.5 pr-2">{row?.ac ?? '—'}</td>
                                                            <td className="py-1.5 pr-2">{row?.cd ?? '—'}</td>
                                                            <td className="py-1.5 pr-2">{row?.md ?? '—'}</td>
                                                            <td className="py-1.5 pr-2">{row?.mnd ?? '—'}</td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </AccordionCard>
                            ))}

                            <AccordionCard title="Disposisi" defaultOpen={false}>
                                <InfoField label="Line Leader" value={finishedCheck.line_leader_name ?? '—'} />
                                <InfoField label="QC FG Inspector" value={finishedCheck.user?.name ?? '—'} />
                                <ChecklistRow label="Disposition" value={finishedCheck.disposition} />
                                <InfoField label="Remarks" value={finishedCheck.remarks ?? '—'} full />
                            </AccordionCard>
                        </>
                    ) : (
                        <EmptyNote>Finished Check belum diisi.</EmptyNote>
                    )}

                    <ApprovalActionCard batchId={batch.id} decisions={decisions} stage={stage} />
                </div>
            </TwoPane>
        </IpcShell>
    );
}
