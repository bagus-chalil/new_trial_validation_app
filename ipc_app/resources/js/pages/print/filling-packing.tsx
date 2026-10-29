import { AccordionCard } from '@/components/ipc/accordion-card';
import {
    ChecklistRow,
    EmptyNote,
    groupLabel,
    InfoField,
    PhotoRow,
    PrintActionCard,
    PrintPreviewButton,
    RevisionHistoryCard,
    type ChecklistGroup,
    type PrintInfo,
    type RevisionRow,
} from '@/components/ipc/approval-report';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { TwoPane } from '@/components/ipc/two-pane';
import { WorkflowIntro } from '@/components/ipc/workflow-form';
import { IpcShell } from '@/layouts/ipc-shell';
import { lineLabel } from '@/lib/batch-line';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';

interface Batch {
    id: number;
    no_batch: string;
    bulk_code: string;
    created_at: string;
    flow_type: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string } | null;
}

interface FillingSampleRow {
    sample_no: number;
    weight_value: string | null;
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
    master_line?: { name: string; code: string } | null;
    standard_weight_mb: string | null;
    sum_weight_mb: string | null;
    line_leader_name: string | null;
    coding_machine: string | null;
    weighing_data: string | null;
    remarks: string | null;
    decision: string | null;
    revisions?: PackingCheckRevision[];
    user?: { name: string } | null;
}

interface StartupCheckSlice {
    line_leader_name: string | null;
    density: string | null;
    density_not_applicable?: boolean;
    [key: string]: unknown;
}

export default function PrintFillingPacking({
    batch,
    side,
    startupCheck,
    fillingCheck,
    packingCheck,
    photoUrls,
    printInfo,
    packingChecklistGroups,
}: {
    batch: Batch;
    side: 'filling' | 'packing';
    startupCheck: StartupCheckSlice | null;
    fillingCheck: FillingCheckData | null;
    packingCheck: PackingCheckData | null;
    photoUrls: Record<string, Record<string, string | null>>;
    printInfo: PrintInfo;
    packingChecklistGroups: ChecklistGroup[];
}) {
    const { props } = usePage<SharedData>();
    const sideLabel = side === 'filling' ? 'Filling' : 'Packing';
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const fillingSamplesByNo = Object.fromEntries((fillingCheck?.samples ?? []).map((s) => [s.sample_no, s]));

    return (
        <IpcShell
            title={`${sideLabel} Report`}
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}/print`}
            headerActions={<PrintPreviewButton href={`/batches/${batch.id}/print/${side}/pdf`} label="Cetak" />}
        >
            <Head title={`Print — ${sideLabel} Report — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="workflow-review flex flex-1 flex-col gap-5 px-5 pt-5 pb-6 md:px-8 md:pt-7">
                    <WorkflowIntro
                        title="Detail laporan pemeriksaan"
                        description="Buka bagian laporan untuk meninjau data. Gunakan preview cetak untuk memeriksa dokumen sebelum mencetak."
                    />
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <InfoField label="No. Batch" value={batch.no_batch} />
                        <InfoField label="FG Code" value={batch.master_product.fg_code} />
                        <InfoField label="Bulk Code" value={batch.bulk_code} />
                        <InfoField label="Line" value={lineLabel(batch.master_line)} />
                    </div>

                    {side === 'filling' &&
                        (fillingCheck ? (
                            <AccordionCard title="Filling Inspection" defaultOpen={false}>
                                <InfoField label="QC Inspector" value={fillingCheck.user?.name ?? '—'} />
                                <InfoField label="Line Leader" value={startupCheck?.line_leader_name ?? '—'} />
                                <InfoField label="Density" value={startupCheck?.density_not_applicable ? 'N/A' : (startupCheck?.density ?? '—')} />
                                <InfoField label="Average Weight" value={fillingCheck.average_weight ?? '—'} />
                                <ChecklistRow label="Kebersihan Bulk & Odor" value={fillingCheck.sample_bulk_odor_status} />
                                <ChecklistRow label="Uji Kebocoran (Vaccum / Press)" value={fillingCheck.sample_leakage_test_status} />
                                <div className="col-span-full overflow-x-auto">
                                    <table className="w-full text-[12.5px]">
                                        <thead>
                                            <tr className="text-muted-foreground/70 border-border-soft border-b text-left uppercase">
                                                <th className="py-1.5 pr-2 font-semibold">Sample</th>
                                                <th className="py-1.5 pr-2 font-semibold">Weight Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {Array.from({ length: 10 }, (_, i) => i + 1).map((no) => (
                                                <tr key={no} className="border-border-soft border-b last:border-0">
                                                    <td className="py-1.5 pr-2 font-semibold">{no}</td>
                                                    <td className="py-1.5 pr-2">{fillingSamplesByNo[no]?.weight_value ?? '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <ChecklistRow label="Decision" value={fillingCheck.decision} />
                                <InfoField label="Remarks" value={fillingCheck.remarks ?? '—'} full />
                                <PhotoRow
                                    photos={[
                                        { key: 'color', label: 'Color', url: photoUrls.filling?.color ?? null },
                                        { key: 'wo_image', label: 'WI Image', url: photoUrls.filling?.wo_image ?? null },
                                        { key: 'date_bulk', label: 'Identity Bulk', url: photoUrls.filling?.date_bulk ?? null },
                                        { key: 'image_tube', label: 'Appearance', url: photoUrls.filling?.image_tube ?? null },
                                    ]}
                                />
                            </AccordionCard>
                        ) : (
                            <EmptyNote>Filling Check belum diisi.</EmptyNote>
                        ))}

                    {side === 'filling' && (
                        <RevisionHistoryCard
                            title="Riwayat Simpan — Filling Check"
                            revisions={fillingCheck?.revisions ?? []}
                            renderSummary={(rev) => (
                                <>
                                    {rev.decision && <span>Decision: {rev.decision}</span>}
                                    {rev.average_weight != null && Number(rev.average_weight) !== 0 && <span>Avg Weight: {rev.average_weight}</span>}
                                </>
                            )}
                            renderRemarks={(rev) => rev.remarks}
                        />
                    )}

                    {side === 'packing' && startupCheck && (
                        <AccordionCard title="Pemeriksaan Format Coding" defaultOpen={false}>
                            {(['primary', 'secondary', 'tersier'] as const).map((tier) => {
                                const title = { primary: 'Primer', secondary: 'Sekunder', tersier: 'Tersier' }[tier];
                                const format = startupCheck[`coding_format_${tier}`] as string | null | undefined;
                                const notApplicable = Boolean(startupCheck[`coding_operator_na_${tier}`]);

                                return (
                                    <div key={tier} className="border-border col-span-full grid gap-3 rounded-xl border p-3 md:grid-cols-2">
                                        <p className="text-sm font-bold md:col-span-2">{title}</p>
                                        <div>
                                            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">
                                                Format Penulisan Coding dan Penjelasan
                                            </p>
                                            <p className="mt-0.5 text-[13.5px] font-bold whitespace-pre-line">{format || '—'}</p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground/70 mb-1 text-[10.5px] font-semibold tracking-wide uppercase">
                                                Operator (Actual Coding)
                                            </p>
                                            {notApplicable ? (
                                                <p className="text-[13.5px] font-bold">N/A</p>
                                            ) : (
                                                <PhotoRow
                                                    photos={[
                                                        {
                                                            key: `coding_${tier}`,
                                                            label: `Coding ${title}`,
                                                            url: photoUrls.startup?.[`coding_${tier}`] ?? null,
                                                        },
                                                    ]}
                                                />
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </AccordionCard>
                    )}

                    {side === 'packing' &&
                        (packingCheck ? (
                            <AccordionCard title="Packing Inspection" defaultOpen={false}>
                                <InfoField label="QC" value={packingCheck.user?.name ?? '—'} />
                                <InfoField label="Line Packing" value={lineLabel(packingCheck.master_line ?? batch.master_line)} />
                                <InfoField label="Line Leader" value={packingCheck.line_leader_name ?? '—'} />
                                <InfoField label="Machines Coding" value={packingCheck.coding_machine ?? '—'} />
                                <InfoField label="Std Bruto MB" value={packingCheck.standard_weight_mb ?? '—'} />
                                <InfoField label="Data Timbang" value={packingCheck.weighing_data ?? '—'} />

                                {packingChecklistGroups.map((group) => (
                                    <div key={group.key} className="col-span-full">
                                        <p className="text-muted-foreground/70 mb-1.5 text-[11.5px] font-semibold tracking-wide uppercase">
                                            {groupLabel(group.key)} Packaging
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
                        ))}

                    {side === 'packing' && (
                        <RevisionHistoryCard
                            title="Riwayat Simpan — Packing Check"
                            revisions={packingCheck?.revisions ?? []}
                            renderSummary={(rev) => (
                                <>
                                    {rev.decision && <span>Decision: {rev.decision}</span>}
                                    {rev.sum_weight_mb && <span>Weight of MB: {rev.sum_weight_mb}</span>}
                                </>
                            )}
                            renderRemarks={(rev) => rev.remarks}
                        />
                    )}

                    <PrintActionCard batchId={batch.id} info={printInfo} />
                </div>
            </TwoPane>
        </IpcShell>
    );
}
