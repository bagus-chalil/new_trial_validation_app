import { AccordionCard } from '@/components/ipc/accordion-card';
import {
    ChecklistRow,
    EmptyNote,
    formatDateTime,
    groupLabel,
    InfoField,
    PhotoRow,
    PrintActionCard,
    PrintPreviewButton,
    type ChecklistGroup,
    type PrintInfo,
    type TestTypeRow,
} from '@/components/ipc/approval-report';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { StatusChip } from '@/components/ipc/chip-toggle-group';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
}

interface StartupCheckData {
    id: number;
    [field: string]: unknown;
    validation_report_status: string | null;
    filling_range_min: string | null;
    filling_range_max: string | null;
    density: string | null;
    average_of_empty_bottle_weight: string | null;
    heating: string | null;
    line_leader_name: string | null;
    operator_name: string | null;
    remarks: string | null;
    created_at: string;
    user?: { name: string } | null;
}

interface StartupInspectionItemRow {
    parameter_key: string;
    status: string | null;
    remark: string | null;
}

interface StartupInspectionSampleRow {
    sample_no: number;
    volume_weight: string | null;
    weight_master_box: string | null;
}

interface StartupInspectionData {
    completed_at: string | null;
    items: StartupInspectionItemRow[];
    samples: StartupInspectionSampleRow[];
}

export default function PrintStartup({
    batch,
    startupCheck,
    startupInspection,
    photoUrls,
    printInfo,
    startupChecklistGroups,
    startupInspectionParameterKeys,
    testTypesByCategory,
}: {
    batch: Batch;
    startupCheck: StartupCheckData | null;
    startupInspection: StartupInspectionData | null;
    photoUrls: Record<string, Record<string, string | null>>;
    printInfo: PrintInfo;
    startupChecklistGroups: ChecklistGroup[];
    startupInspectionParameterKeys: string[];
    testTypesByCategory: Record<string, TestTypeRow[]>;
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const inspectionItemsByKey = Object.fromEntries((startupInspection?.items ?? []).map((i) => [i.parameter_key, i]));
    const inspectionSamplesByNo = Object.fromEntries((startupInspection?.samples ?? []).map((s) => [s.sample_no, s]));

    return (
        <IpcShell
            title="Start Up Inspection Form"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}/print`}
            headerActions={<PrintPreviewButton href={`/batches/${batch.id}/print/startup/pdf`} label="Cetak" />}
        >
            <Head title={`Print — Startup Inspection — ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-6 md:px-8">
                    <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                        <InfoField label="No. Batch" value={batch.no_batch} />
                        <InfoField label="FG Code" value={batch.master_product.fg_code} />
                        <InfoField label="Bulk Code" value={batch.master_product.bulk_code} />
                        <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                    </div>

                    <PhotoRow
                        photos={[
                            { key: 'im_number', label: 'IM Number', url: photoUrls.startup?.im_number ?? null },
                            { key: 'color', label: 'Color', url: photoUrls.startup?.color ?? null },
                            { key: 'temperature_setting', label: 'Temperature Setting', url: photoUrls.startup?.temperature_setting ?? null },
                        ]}
                    />

                    {startupCheck ? (
                        <>
                            {startupChecklistGroups.map((group) => (
                                <AccordionCard key={group.key} title={groupLabel(group.key)} defaultOpen={false}>
                                    {Object.entries(group.fields).map(([field, label]) => (
                                        <ChecklistRow key={field} label={label} value={startupCheck[field] as string | null} />
                                    ))}
                                </AccordionCard>
                            ))}

                            <AccordionCard title="Parameter Filling" defaultOpen={false}>
                                <ChecklistRow label="Validation Report" value={startupCheck.validation_report_status} />
                                <InfoField label="Filling Range Min" value={startupCheck.filling_range_min ?? '—'} />
                                <InfoField label="Filling Range Max" value={startupCheck.filling_range_max ?? '—'} />
                                <InfoField label="Density" value={startupCheck.density ?? '—'} />
                                <InfoField label="Average of Empty Bottle Weight" value={startupCheck.average_of_empty_bottle_weight ?? '—'} />
                                <InfoField label="Heating" value={startupCheck.heating ?? '—'} />
                                <InfoField label="Line Leader" value={startupCheck.line_leader_name ?? '—'} />
                                <InfoField label="Operator" value={startupCheck.operator_name ?? '—'} />
                                <InfoField label="Remarks" value={startupCheck.remarks ?? '—'} full />
                                <InfoField label="Prepared By" value={startupCheck.user?.name ?? '—'} />
                                <InfoField label="Tanggal" value={formatDateTime(startupCheck.created_at)} />
                            </AccordionCard>
                        </>
                    ) : (
                        <EmptyNote>Startup Check belum diisi.</EmptyNote>
                    )}

                    {startupInspection && (startupInspection.items.length > 0 || startupInspection.samples.length > 0) ? (
                        <>
                            <AccordionCard title="Start Inspection — Checklist" defaultOpen={false}>
                                {startupInspectionParameterKeys.map((key) => (
                                    <div
                                        key={key}
                                        className="border-border-soft col-span-full flex items-start justify-between gap-3 border-b py-2 last:border-0"
                                    >
                                        <div>
                                            <p className="text-[13px] font-bold capitalize">{key.replace(/_/g, ' ')}</p>
                                            {inspectionItemsByKey[key]?.remark && (
                                                <p className="text-muted-foreground/70 mt-0.5 text-[12px]">{inspectionItemsByKey[key].remark}</p>
                                            )}
                                        </div>
                                        <StatusChip value={inspectionItemsByKey[key]?.status ?? null} />
                                    </div>
                                ))}
                            </AccordionCard>

                            <AccordionCard title="Verifikasi Sebelum Produksi — Sample" defaultOpen={false}>
                                <div className="col-span-full overflow-x-auto">
                                    <table className="w-full text-[12.5px]">
                                        <thead>
                                            <tr className="text-muted-foreground/70 border-border-soft border-b text-left uppercase">
                                                <th className="py-1.5 pr-2 font-semibold">Sample</th>
                                                <th className="py-1.5 pr-2 font-semibold">Volume / Weight</th>
                                                <th className="py-1.5 pr-2 font-semibold">Weight M.Box</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {Array.from({ length: 30 }, (_, i) => i + 1)
                                                .filter((no) => inspectionSamplesByNo[no])
                                                .map((no) => (
                                                    <tr key={no} className="border-border-soft border-b last:border-0">
                                                        <td className="py-1.5 pr-2 font-semibold">{no}</td>
                                                        <td className="py-1.5 pr-2">{inspectionSamplesByNo[no]?.volume_weight ?? '—'}</td>
                                                        <td className="py-1.5 pr-2">{inspectionSamplesByNo[no]?.weight_master_box ?? '—'}</td>
                                                    </tr>
                                                ))}
                                            {startupInspection.samples.length === 0 && (
                                                <tr>
                                                    <td colSpan={3} className="text-muted-foreground/60 py-2 text-center italic">
                                                        Belum ada sample diisi.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </AccordionCard>

                            <AccordionCard title="Test Type" defaultOpen={false}>
                                {Object.entries(testTypesByCategory).map(([category, types]) => (
                                    <div key={category} className="col-span-full">
                                        <p className="text-muted-foreground/70 mb-1.5 text-[11.5px] font-semibold tracking-wide uppercase">
                                            {category}
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {types.map((t) => (
                                                <span
                                                    key={t.id}
                                                    className={`flex items-center gap-1.5 rounded-lg border-[1.5px] px-2.5 py-1 text-[12.5px] font-semibold ${
                                                        t.is_performed
                                                            ? 'border-primary/30 bg-primary/[0.08] text-primary'
                                                            : 'border-border text-muted-foreground'
                                                    }`}
                                                >
                                                    {t.is_performed ? (
                                                        <CheckCircle2 className="size-3.5" strokeWidth={2.4} />
                                                    ) : (
                                                        <XCircle className="size-3.5" strokeWidth={2.4} />
                                                    )}
                                                    {t.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </AccordionCard>
                        </>
                    ) : (
                        <EmptyNote>Start Inspection belum diisi (opsional).</EmptyNote>
                    )}

                    <PrintActionCard batchId={batch.id} info={printInfo} />
                </div>
            </TwoPane>
        </IpcShell>
    );
}
