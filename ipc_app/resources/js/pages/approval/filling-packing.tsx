import { AccordionCard } from '@/components/ipc/accordion-card';
import {
    ApprovalActionCard,
    ChecklistRow,
    EmptyNote,
    groupLabel,
    InfoField,
    PhotoRow,
    PrintPreviewButton,
    RevisionHistoryCard,
    type ChecklistGroup,
    type RevisionRow,
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

interface FillingSampleRow {
    sample_no: number;
    weight_value: string | null;
    weight_result: string | null;
}

interface FillingCheckRevision extends RevisionRow {
    sample_bulk_odor_status: string | null;
    sample_leakage_test_status: string | null;
    remarks: string | null;
    decision: string | null;
    average_weight: string | null;
}

interface FillingCheckData {
    sample_bulk_odor_status: string | null;
    sample_leakage_test_status: string | null;
    average_weight: string | null;
    remarks: string | null;
    decision: string | null;
    samples: FillingSampleRow[];
    revisions?: FillingCheckRevision[];
    user?: { name: string } | null;
}

interface PackingCheckRevision extends RevisionRow {
    decision: string | null;
    remarks: string | null;
    sum_weight_mb: string | null;
}

interface PackingCheckData {
    [field: string]: unknown;
    standard_weight_mb: string | null;
    sum_weight_mb: string | null;
    line_leader_name: string | null;
    coding_machine: string | null;
    remarks: string | null;
    decision: string | null;
    revisions?: PackingCheckRevision[];
    user?: { name: string } | null;
}

interface StartupCheckSlice {
    line_leader_name: string | null;
    density: string | null;
}

export default function ApprovalFillingPacking({
    batch,
    startupCheck,
    fillingCheck,
    packingCheck,
    photoUrls,
    stage,
    decisions,
    packingChecklistGroups,
}: {
    batch: Batch;
    startupCheck: StartupCheckSlice | null;
    fillingCheck: FillingCheckData | null;
    packingCheck: PackingCheckData | null;
    photoUrls: Record<string, Record<string, string | null>>;
    stage: StageInfo;
    decisions: string[];
    packingChecklistGroups: ChecklistGroup[];
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const fillingSamplesByNo = Object.fromEntries((fillingCheck?.samples ?? []).map((s) => [s.sample_no, s]));

    return (
        <IpcShell
            title="Filling & Packing Report"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}/approval`}
            headerActions={<PrintPreviewButton href={`/batches/${batch.id}/approval/filling-packing/print`} />}
        >
            <Head title={`Filling & Packing Report — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-6 md:px-8">
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <InfoField label="No. Batch" value={batch.no_batch} />
                        <InfoField label="FG Code" value={batch.master_product.fg_code} />
                        <InfoField label="Bulk Code" value={batch.master_product.bulk_code} />
                        <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                    </div>

                    {fillingCheck ? (
                        <AccordionCard title="A. Filling Inspection" defaultOpen={false}>
                            <InfoField label="QC Inspector" value={fillingCheck.user?.name ?? '—'} />
                            <InfoField label="Line Leader" value={startupCheck?.line_leader_name ?? '—'} />
                            <InfoField label="Density" value={startupCheck?.density ?? '—'} />
                            <InfoField label="Average Weight (Result)" value={fillingCheck.average_weight ?? '—'} />
                            <ChecklistRow label="Kebersihan Bulk & Odor" value={fillingCheck.sample_bulk_odor_status} />
                            <ChecklistRow label="Uji Kebocoran (Vaccum / Press)" value={fillingCheck.sample_leakage_test_status} />
                            <div className="col-span-full overflow-x-auto">
                                <table className="w-full text-[12.5px]">
                                    <thead>
                                        <tr className="text-muted-foreground/70 border-border-soft border-b text-left uppercase">
                                            <th className="py-1.5 pr-2 font-semibold">Sample</th>
                                            <th className="py-1.5 pr-2 font-semibold">Weight Value</th>
                                            <th className="py-1.5 pr-2 font-semibold">Weight Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {Array.from({ length: 10 }, (_, i) => i + 1).map((no) => (
                                            <tr key={no} className="border-border-soft border-b last:border-0">
                                                <td className="py-1.5 pr-2 font-semibold">{no}</td>
                                                <td className="py-1.5 pr-2">{fillingSamplesByNo[no]?.weight_value ?? '—'}</td>
                                                <td className="py-1.5 pr-2">{fillingSamplesByNo[no]?.weight_result ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <ChecklistRow label="Decision" value={fillingCheck.decision} />
                            <InfoField label="Remarks" value={fillingCheck.remarks ?? '—'} full />
                            <PhotoRow photos={[{ key: 'color', label: 'Color', url: photoUrls.filling?.color ?? null }]} />
                        </AccordionCard>
                    ) : (
                        <EmptyNote>Filling Check belum diisi.</EmptyNote>
                    )}

                    <RevisionHistoryCard
                        title="Riwayat Simpan — Filling Check"
                        revisions={fillingCheck?.revisions ?? []}
                        renderSummary={(rev) => (
                            <>
                                {rev.decision && <span>Decision: {rev.decision}</span>}
                                {rev.average_weight && <span>Avg Weight: {rev.average_weight}</span>}
                            </>
                        )}
                        renderRemarks={(rev) => rev.remarks}
                    />

                    {packingCheck ? (
                        <AccordionCard title="B. Packing Inspection" defaultOpen={false}>
                            <InfoField label="QC" value={packingCheck.user?.name ?? '—'} />
                            <InfoField label="Line Leader" value={packingCheck.line_leader_name ?? '—'} />
                            <InfoField label="Machines Coding" value={packingCheck.coding_machine ?? '—'} />
                            <InfoField label="Standard Weight MB" value={packingCheck.standard_weight_mb ?? '—'} />
                            <InfoField label="Sum Weight MB" value={packingCheck.sum_weight_mb ?? '—'} />

                            {packingChecklistGroups.map((group) => (
                                <div key={group.key} className="col-span-full">
                                    <p className="text-muted-foreground/70 mb-1.5 text-[11.5px] font-semibold tracking-wide uppercase">
                                        {groupLabel(group.key)} Packing
                                    </p>
                                    {Object.entries(group.fields).map(([field, label]) => (
                                        <ChecklistRow key={field} label={label} value={packingCheck[field] as string | null} />
                                    ))}
                                </div>
                            ))}

                            <PhotoRow
                                photos={[
                                    { key: 'palletisasi', label: 'Palletisasi', url: photoUrls.packing?.palletisasi ?? null },
                                    { key: 'color', label: 'Color', url: photoUrls.packing?.color ?? null },
                                    {
                                        key: 'primary_coding_batch_exp',
                                        label: 'Primary Coding Batch/EXP',
                                        url: photoUrls.packing?.primary_coding_batch_exp ?? null,
                                    },
                                    {
                                        key: 'secondary_coding_batch_exp',
                                        label: 'Secondary Coding',
                                        url: photoUrls.packing?.secondary_coding_batch_exp ?? null,
                                    },
                                    {
                                        key: 'tersier_coding_batch',
                                        label: 'Tersier Coding / Shipper',
                                        url: photoUrls.packing?.tersier_coding_batch ?? null,
                                    },
                                ]}
                            />

                            <ChecklistRow label="Decision" value={packingCheck.decision} />
                            <InfoField label="Remarks / Notes" value={packingCheck.remarks ?? '—'} full />
                        </AccordionCard>
                    ) : (
                        <EmptyNote>Packing Check belum diisi.</EmptyNote>
                    )}

                    <RevisionHistoryCard
                        title="Riwayat Simpan — Packing Check"
                        revisions={packingCheck?.revisions ?? []}
                        renderSummary={(rev) => (
                            <>
                                {rev.decision && <span>Decision: {rev.decision}</span>}
                                {rev.sum_weight_mb && <span>Sum Weight MB: {rev.sum_weight_mb}</span>}
                            </>
                        )}
                        renderRemarks={(rev) => rev.remarks}
                    />

                    <ApprovalActionCard batchId={batch.id} decisions={decisions} stage={stage} />
                </div>
            </TwoPane>
        </IpcShell>
    );
}
