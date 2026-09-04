import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { CameraCaptureDialog } from '@/components/ipc/camera-capture-dialog';
import { ChipToggleGroup } from '@/components/ipc/chip-toggle-group';
import { StickySaveBar } from '@/components/ipc/sticky-save-bar';
import { Toast, useToast } from '@/components/ipc/toast';
import { TwoPane } from '@/components/ipc/two-pane';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

const PHOTO_FIELDS: { key: string; label: string }[] = [
    { key: 'palletisasi', label: 'Palletisasi' },
    { key: 'color', label: 'Color' },
    { key: 'primary_coding_batch_exp', label: 'Primary Coding Batch/Exp' },
    { key: 'secondary_coding_batch_exp', label: 'Secondary Coding' },
    { key: 'tersier_coding_batch', label: 'Tersier Coding / Shipper' },
];

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
}

interface PackingCheckRevision {
    id: number;
    revision_no: number;
    finalize: boolean;
    decision: string | null;
    remarks: string | null;
    sum_weight_mb: string | null;
    created_at: string;
    user?: { name: string } | null;
}

interface PackingCheckData {
    id: number;
    completed_at: string | null;
    created_at: string;
    save_count: number;
    line_leader_name: string | null;
    coding_machine: string | null;
    revisions?: PackingCheckRevision[];
    user?: { name: string } | null;
    [key: string]: unknown;
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

interface ChecklistGroup {
    key: string;
    fields: Record<string, string>;
    options: string[];
}

const GROUP_TITLES: Record<string, string> = {
    primary: 'Primary',
    secondary: 'Secondary',
    tersier: 'Tersier',
};

const inputClass =
    'h-[46px] rounded-xl border-[1.5px] border-border bg-background px-3.5 text-[14.5px] font-semibold text-foreground placeholder:text-muted-foreground/50';
const errorBorder = 'border-destructive ring-1 ring-destructive';

export default function PackingCheckEdit({
    batch,
    packingCheck,
    isReadOnly,
    checklistGroups,
    decisions,
    photoUrls,
    standardWeightMb,
}: {
    batch: Batch;
    packingCheck: PackingCheckData | null;
    isReadOnly: boolean;
    checklistGroups: ChecklistGroup[];
    decisions: string[];
    photoUrls: Record<string, string | null>;
    standardWeightMb: string | null;
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();
    const [errorFields, setErrorFields] = useState<Set<string>>(new Set());
    const [cameraField, setCameraField] = useState<string | null>(null);

    const uploadPhoto = (field: string, file: File) => {
        router.post(`/batches/${batch.id}/packing-check/photo/${field}`, { photo: file }, { forceFormData: true, preserveScroll: true });
    };

    const inspectorName = packingCheck?.user?.name ?? props.auth.user.name;
    const revisions = [...(packingCheck?.revisions ?? [])].sort((a, b) => b.revision_no - a.revision_no);

    const initialChecklistValues = checklistGroups.reduce<Record<string, string>>((acc, group) => {
        Object.keys(group.fields).forEach((key) => {
            acc[key] = (packingCheck?.[key] as string) ?? '';
        });
        return acc;
    }, {});

    const { data, setData, put, transform, processing, errors } = useForm<Record<string, string | null>>({
        ...initialChecklistValues,
        sum_weight_mb: (packingCheck?.sum_weight_mb as string) ?? '',
        line_leader_name: packingCheck?.line_leader_name ?? '',
        coding_machine: packingCheck?.coding_machine ?? '',
        remarks: (packingCheck?.remarks as string) ?? '',
        decision: (packingCheck?.decision as string) ?? '',
    });

    // Captured once on the first round; from TH_PROGRESS 2 on the server carries them forward
    // and the form stops asking, so QC only re-enters what actually changes between rounds.
    const lineLeaderLocked = Boolean(packingCheck?.line_leader_name);
    const codingMachineLocked = Boolean(packingCheck?.coding_machine);

    const allChecklistKeys = useMemo(() => checklistGroups.flatMap((group) => Object.keys(group.fields)), [checklistGroups]);
    const answeredCount = allChecklistKeys.filter((key) => data[key]).length;

    // Used both to decide the "Parameter Packing" card's default open/collapsed state and to
    // show its "Selesai" badge — every field this screen actually requires to finalize.
    const parameterPackingComplete =
        Boolean(standardWeightMb) &&
        Boolean(data.sum_weight_mb?.toString().trim()) &&
        Boolean(data.line_leader_name?.trim()) &&
        Boolean(data.coding_machine?.trim()) &&
        PHOTO_FIELDS.every(({ key }) => Boolean(photoUrls[key])) &&
        Boolean(data.decision) &&
        Boolean(data.remarks?.trim());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty = new Set<string>();
        if (!data.remarks?.trim()) empty.add('remarks');
        if (!data.decision) empty.add('decision');
        // Only wajib on the round that actually sets them — once locked they're carried forward
        // by the server and don't need re-entry, matching SavePackingCheckRequest's server-side rule.
        if (!lineLeaderLocked && !data.line_leader_name?.trim()) empty.add('line_leader_name');
        if (!codingMachineLocked && !data.coding_machine?.trim()) empty.add('coding_machine');
        if (!data.sum_weight_mb?.toString().trim()) empty.add('sum_weight_mb');
        // Derived server-side from Start Inspection, not a form field — flagged the same way so
        // the operator notices it's missing instead of only finding out after a failed save.
        if (!standardWeightMb) empty.add('standard_weight_mb');
        PHOTO_FIELDS.forEach(({ key }) => {
            if (!photoUrls[key]) empty.add(key);
        });
        allChecklistKeys.forEach((key) => {
            if (!data[key]) empty.add(key);
        });
        if (empty.size) {
            setErrorFields(empty);
            toast(`${empty.size} field wajib belum diisi untuk Selesaikan`);
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: true }));
        put(`/batches/${batch.id}/packing-check`);
    };

    const blankRoundForm = () => ({
        ...allChecklistKeys.reduce<Record<string, string>>((acc, key) => ({ ...acc, [key]: '' }), {}),
        sum_weight_mb: '',
        // Always carried forward, never blanked: whatever was just typed either becomes the
        // locked value server-side (first non-blank round) or is still freely editable next
        // round either way, so there's no case where wiping it here is correct. Using the
        // lock flags here was the bug — they reflect props from *before* this save resolved,
        // so a round-1 save that just set the value would wipe it back to blank on screen even
        // though the server now has it locked in.
        line_leader_name: data.line_leader_name,
        coding_machine: data.coding_machine,
        remarks: '',
        decision: '',
    });

    const saveDraft = () => {
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: false }));
        put(`/batches/${batch.id}/packing-check`, { preserveState: true, onSuccess: () => setData(blankRoundForm()) });
    };

    return (
        <IpcShell
            title="Packing Check"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}`}
            headerActions={
                isReadOnly ? (
                    <span className="rounded-full bg-green-100 px-3.5 py-1.5 text-[12.5px] font-bold whitespace-nowrap text-green-800">
                        Selesai — read only
                    </span>
                ) : (
                    <span className="bg-primary/[0.08] text-primary rounded-full px-3 py-1.5 text-[12.5px] font-bold whitespace-nowrap">
                        {answeredCount}/{allChecklistKeys.length}
                    </span>
                )
            }
        >
            <Head title={`Packing Check — ${batch.no_batch}`} />
            <Toast message={message} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-2 md:px-8">
                        {/* Info header — mirrors the legacy screen's top info block and Filling Check's
                            own info header, so QC has full context without navigating away. */}
                        <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                            <InfoField label="Tanggal" value={formatDateTime(packingCheck?.created_at ?? batch.created_at)} />
                            <InfoField label="FG Code" value={batch.master_product.fg_code} />
                            <InfoField label="No. Batch" value={batch.no_batch} />
                            <InfoField label="Bulk Code" value={batch.master_product.bulk_code} />
                            <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                            <InfoField label="IPC ID" value={inspectorName} />
                            <InfoField label="TH Progress" value={String(packingCheck?.save_count ?? 0)} />
                            <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                        </div>

                        {checklistGroups.map((group) => {
                            const groupTotal = Object.keys(group.fields).length;
                            const groupAnswered = Object.keys(group.fields).filter((key) => data[key]).length;
                            return (
                                <AccordionCard
                                    key={group.key}
                                    title={GROUP_TITLES[group.key] ?? group.key}
                                    progress={`${groupAnswered}/${groupTotal} terisi`}
                                    complete={groupAnswered === groupTotal}
                                >
                                    {Object.entries(group.fields).map(([key, label]) => (
                                        <div key={key} className="flex flex-col gap-2">
                                            <Label className="text-foreground text-[13px] font-semibold">{label}</Label>
                                            <div className={errorFields.has(key) ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                                <ChipToggleGroup
                                                    name={label}
                                                    options={group.options}
                                                    value={data[key] ?? ''}
                                                    onChange={(value) => {
                                                        setData(key, value);
                                                        setErrorFields((prev) => {
                                                            const n = new Set(prev);
                                                            n.delete(key);
                                                            return n;
                                                        });
                                                    }}
                                                    disabled={isReadOnly}
                                                />
                                            </div>
                                            <InputError message={errors[key]} />
                                        </div>
                                    ))}
                                </AccordionCard>
                            );
                        })}

                        <AccordionCard title="Parameter Packing" complete={parameterPackingComplete} defaultOpen={!parameterPackingComplete}>
                            <div className="flex flex-col gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold">Standard Weight MB</Label>
                                {/* Read-only: taken from the batch's Start Inspection weight-master-box
                                    readings on save, not typed here — see SavePackingCheck::standardWeightMbFor().
                                    Nothing to type here if it's missing/red — go fill Weight Master Box
                                    samples on Start Inspection first, then come back. */}
                                <div className={`${inputClass} bg-muted/40 ${errorFields.has('standard_weight_mb') ? errorBorder : ''}`}>
                                    {standardWeightMb ?? '—'}
                                </div>
                                <InputError message={errors.standard_weight_mb} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="sum_weight_mb" className="text-muted-foreground text-xs font-semibold">
                                    Sum Weight MB
                                </Label>
                                <Input
                                    id="sum_weight_mb"
                                    type="number"
                                    step="0.0001"
                                    className={`${inputClass} ${errorFields.has('sum_weight_mb') ? errorBorder : ''}`}
                                    value={data.sum_weight_mb ?? ''}
                                    onChange={(e) => {
                                        setData('sum_weight_mb', e.target.value);
                                        setErrorFields((prev) => {
                                            const n = new Set(prev);
                                            n.delete('sum_weight_mb');
                                            return n;
                                        });
                                    }}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.sum_weight_mb} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="line_leader_name" className="text-muted-foreground text-xs font-semibold">
                                    Line Leader{lineLeaderLocked && ' (terkunci sejak TH Progress 1)'}
                                </Label>
                                <Input
                                    id="line_leader_name"
                                    className={`${inputClass} ${errorFields.has('line_leader_name') ? errorBorder : ''}`}
                                    value={data.line_leader_name ?? ''}
                                    onChange={(e) => {
                                        setData('line_leader_name', e.target.value);
                                        setErrorFields((prev) => {
                                            const n = new Set(prev);
                                            n.delete('line_leader_name');
                                            return n;
                                        });
                                    }}
                                    disabled={isReadOnly || lineLeaderLocked}
                                />
                                <InputError message={errors.line_leader_name} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="coding_machine" className="text-muted-foreground text-xs font-semibold">
                                    Coding Machine{codingMachineLocked && ' (terkunci sejak TH Progress 1)'}
                                </Label>
                                <Input
                                    id="coding_machine"
                                    className={`${inputClass} ${errorFields.has('coding_machine') ? errorBorder : ''}`}
                                    value={data.coding_machine ?? ''}
                                    onChange={(e) => {
                                        setData('coding_machine', e.target.value);
                                        setErrorFields((prev) => {
                                            const n = new Set(prev);
                                            n.delete('coding_machine');
                                            return n;
                                        });
                                    }}
                                    disabled={isReadOnly || codingMachineLocked}
                                />
                                <InputError message={errors.coding_machine} />
                            </div>
                            <div className="col-span-full grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {PHOTO_FIELDS.map(({ key, label }) => (
                                    <div key={key} className="flex flex-col gap-2">
                                        <Label className="text-muted-foreground text-xs font-semibold">{label}</Label>
                                        <button
                                            type="button"
                                            disabled={isReadOnly}
                                            onClick={() => setCameraField(key)}
                                            className={`bg-background flex h-[46px] items-center justify-center gap-2 rounded-xl border-[1.5px] px-3.5 text-[13.5px] font-bold disabled:cursor-not-allowed disabled:opacity-60 ${
                                                errorFields.has(key) ? errorBorder : 'border-border'
                                            }`}
                                        >
                                            <Camera className="size-4" strokeWidth={2.2} />
                                            {photoUrls[key] ? 'Ganti Foto' : 'Ambil Foto'}
                                        </button>
                                        {photoUrls[key] && (
                                            <img
                                                src={photoUrls[key]!}
                                                alt={`Foto ${label}`}
                                                className="border-border h-24 w-24 rounded-xl border object-cover"
                                            />
                                        )}
                                        <InputError message={errors[`photo_${key}`]} />
                                    </div>
                                ))}
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Decision</Label>
                                <div className={errorFields.has('decision') ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                    <ChipToggleGroup
                                        name="decision"
                                        options={decisions}
                                        value={data.decision ?? ''}
                                        onChange={(value) => {
                                            setData('decision', value);
                                            setErrorFields((prev) => {
                                                const n = new Set(prev);
                                                n.delete('decision');
                                                return n;
                                            });
                                        }}
                                        disabled={isReadOnly}
                                    />
                                </div>
                                <InputError message={errors.decision} />
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label htmlFor="remarks" className="text-muted-foreground text-xs font-semibold">
                                    Catatan / Remarks
                                </Label>
                                <Textarea
                                    id="remarks"
                                    rows={2}
                                    className={`border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px] ${errorFields.has('remarks') ? errorBorder : ''}`}
                                    value={data.remarks ?? ''}
                                    onChange={(e) => {
                                        setData('remarks', e.target.value);
                                        setErrorFields((prev) => {
                                            const n = new Set(prev);
                                            n.delete('remarks');
                                            return n;
                                        });
                                    }}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.remarks} />
                            </div>
                        </AccordionCard>

                        {revisions.length > 0 && (
                            <AccordionCard title="Riwayat Simpan" progress={`${revisions.length}x disimpan`} defaultOpen={false}>
                                <div className="col-span-full flex flex-col gap-2.5">
                                    {revisions.map((rev) => (
                                        <div key={rev.id} className="border-border-soft rounded-xl border p-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-[13px] font-bold">
                                                    #{rev.revision_no} {rev.finalize && '· Selesai'}
                                                </span>
                                                <span className="text-muted-foreground text-[11.5px] font-medium">
                                                    {formatDateTime(rev.created_at)} · {rev.user?.name ?? '—'}
                                                </span>
                                            </div>
                                            <div className="text-muted-foreground mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[12px]">
                                                {rev.decision && <span>Decision: {rev.decision}</span>}
                                                {rev.sum_weight_mb && <span>Sum Weight MB: {rev.sum_weight_mb}</span>}
                                            </div>
                                            {rev.remarks && <p className="text-muted-foreground mt-1 text-[12px]">Remarks: {rev.remarks}</p>}
                                        </div>
                                    ))}
                                </div>
                            </AccordionCard>
                        )}
                    </div>

                    {!isReadOnly && (
                        <StickySaveBar
                            label="Simpan & Selesaikan"
                            processing={processing}
                            secondaryLabel="Simpan"
                            onSecondaryClick={saveDraft}
                            note={`${answeredCount} dari ${allChecklistKeys.length} item checklist terisi`}
                        />
                    )}
                </form>
            </TwoPane>

            <CameraCaptureDialog
                open={cameraField !== null}
                onOpenChange={(open) => {
                    if (!open) setCameraField(null);
                }}
                onCapture={(file) => {
                    if (cameraField) uploadPhoto(cameraField, file);
                }}
                title={`Ambil Foto ${PHOTO_FIELDS.find((f) => f.key === cameraField)?.label ?? ''}`}
            />
        </IpcShell>
    );
}

function InfoField({ label, value, full }: { label: string; value: string; full?: boolean }) {
    return (
        <div className={full ? 'col-span-2 md:col-span-4' : undefined}>
            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">{label}</p>
            <p className="mt-0.5 truncate text-[13.5px] font-bold">{value}</p>
        </div>
    );
}
