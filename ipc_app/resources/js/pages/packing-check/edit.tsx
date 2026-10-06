import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { CameraCaptureDialog } from '@/components/ipc/camera-capture-dialog';
import { ChipToggleGroup } from '@/components/ipc/chip-toggle-group';
import { PhotoLightbox } from '@/components/ipc/photo-lightbox';
import { StickySaveBar } from '@/components/ipc/sticky-save-bar';
import { Toast, useToast } from '@/components/ipc/toast';
import { TwoPane } from '@/components/ipc/two-pane';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { IpcShell } from '@/layouts/ipc-shell';
import { lineLabel } from '@/lib/batch-line';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    bulk_code: string;
    created_at: string;
    master_line_id: number | null;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string } | null;
}

interface LineOption {
    id: number;
    code: string;
    name: string;
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
    master_line_id: number | null;
    master_line?: { name: string; code: string } | null;
    standard_weight_mb: string | null;
    line_leader_name: string | null;
    coding_machine: string | null;
    weighing_data: string | null;
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
    weighingDataOptions,
    photoUrls,
    lines,
    canFillMasterBox,
    maxThProgress,
    previousStageCompleted,
}: {
    batch: Batch;
    packingCheck: PackingCheckData | null;
    isReadOnly: boolean;
    checklistGroups: ChecklistGroup[];
    decisions: string[];
    weighingDataOptions: string[];
    photoUrls: Record<string, string | null>;
    lines: LineOption[];
    canFillMasterBox: boolean;
    maxThProgress: number;
    previousStageCompleted: boolean;
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
        // Packing line defaults to the batch's (Filling) line; QC can switch it on any round.
        master_line_id: String(packingCheck?.master_line_id ?? batch.master_line_id ?? ''),
        standard_weight_mb: packingCheck?.standard_weight_mb ?? '',
        line_leader_name: packingCheck?.line_leader_name ?? '',
        coding_machine: packingCheck?.coding_machine ?? '',
        weighing_data: packingCheck?.weighing_data ?? '',
        remarks: (packingCheck?.remarks as string) ?? '',
        decision: (packingCheck?.decision as string) ?? '',
    });

    // Captured once on the first round; from TH_PROGRESS 2 on the server carries them forward
    // and the form stops asking, so QC only re-enters what actually changes between rounds.
    const standardWeightMbLocked = packingCheck?.standard_weight_mb != null;
    const lineLeaderLocked = Boolean(packingCheck?.line_leader_name);
    const codingMachineLocked = Boolean(packingCheck?.coding_machine);
    const weighingDataLocked = Boolean(packingCheck?.weighing_data);

    const allChecklistKeys = useMemo(() => checklistGroups.flatMap((group) => Object.keys(group.fields)), [checklistGroups]);
    const answeredCount = allChecklistKeys.filter((key) => data[key]).length;

    // Used both to decide the "Parameter Packing" card's default open/collapsed state and to
    // show its "Selesai" badge — every field this screen actually requires to finalize.
    const parameterPackingComplete =
        Boolean(data.master_line_id) &&
        Boolean(data.sum_weight_mb?.toString().trim()) &&
        Boolean(data.standard_weight_mb?.toString().trim()) &&
        Boolean(data.line_leader_name?.trim()) &&
        Boolean(data.coding_machine?.trim()) &&
        Boolean(data.weighing_data) &&
        PHOTO_FIELDS.every(({ key }) => Boolean(photoUrls[key])) &&
        Boolean(data.decision) &&
        Boolean(data.remarks?.trim());

    // Everything Selesaikan actually requires, minus the photos (those upload through their own
    // endpoint and aren't part of this form's data — see computeEmptyDraftFields below, which
    // reuses this same field list without the photo half for the Simpan/empty-progress check).
    const computeEmptyRequiredFields = () => {
        const empty = new Set<string>();
        if (!data.remarks?.trim()) empty.add('remarks');
        if (!data.decision) empty.add('decision');
        if (!data.master_line_id) empty.add('master_line_id');
        // Only wajib on the round that actually sets them — once locked they're carried forward
        // by the server and don't need re-entry, matching SavePackingCheckRequest's server-side rule.
        if (!standardWeightMbLocked && !data.standard_weight_mb?.toString().trim()) empty.add('standard_weight_mb');
        if (!lineLeaderLocked && !data.line_leader_name?.trim()) empty.add('line_leader_name');
        if (!codingMachineLocked && !data.coding_machine?.trim()) empty.add('coding_machine');
        if (!weighingDataLocked && !data.weighing_data) empty.add('weighing_data');
        if (!data.sum_weight_mb?.toString().trim()) empty.add('sum_weight_mb');
        allChecklistKeys.forEach((key) => {
            if (!data[key]) empty.add(key);
        });
        return empty;
    };

    // Long form, quantity/decision fields well below the checklist — a validation failure while
    // scrolled down otherwise only shows a toast + off-screen red borders, which reads as "the
    // save button does nothing." Scroll the first empty/errored field into view. "Parameter
    // Packing" defaults open while incomplete (see its defaultOpen prop above) so no extra delay
    // is needed here, unlike Finished Check's collapsed-by-default Quantity Sample groups.
    const scrollToFirstError = (empty: Set<string>) => {
        const firstKey = empty.values().next().value;
        if (!firstKey) return;
        document.getElementById(firstKey)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty = computeEmptyRequiredFields();
        PHOTO_FIELDS.forEach(({ key }) => {
            if (!photoUrls[key]) empty.add(key);
        });
        if (empty.size) {
            setErrorFields(empty);
            toast(`${empty.size} field wajib belum diisi untuk Selesaikan`);
            scrollToFirstError(empty);
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: true }));
        put(`/batches/${batch.id}/packing-check`, { onError: showProgressError });
    };

    const blankRoundForm = () => ({
        ...allChecklistKeys.reduce<Record<string, string>>((acc, key) => ({ ...acc, [key]: '' }), {}),
        sum_weight_mb: '',
        master_line_id: data.master_line_id,
        // Always carried forward, never blanked: whatever was just typed either becomes the
        // locked value server-side (first non-blank round) or is still freely editable next
        // round either way, so there's no case where wiping it here is correct. Using the
        // lock flags here was the bug — they reflect props from *before* this save resolved,
        // so a round-1 save that just set the value would wipe it back to blank on screen even
        // though the server now has it locked in.
        standard_weight_mb: data.standard_weight_mb,
        line_leader_name: data.line_leader_name,
        coding_machine: data.coding_machine,
        weighing_data: data.weighing_data,
        remarks: '',
        decision: '',
    });

    const hasAnyDraftValue = () =>
        Boolean(data.sum_weight_mb?.toString().trim()) ||
        Boolean(data.standard_weight_mb?.toString().trim()) ||
        Boolean(data.line_leader_name?.trim()) ||
        Boolean(data.coding_machine?.trim()) ||
        Boolean(data.weighing_data) ||
        Boolean(data.remarks?.trim()) ||
        Boolean(data.decision) ||
        allChecklistKeys.some((key) => Boolean(data[key]));

    // TH Progress rule errors (max rounds / Filling not finalized) come back under `progress`.
    const showProgressError = (serverErrors: Record<string, string>) => {
        if (serverErrors.progress) toast(serverErrors.progress);
    };

    // The last allowed round can only be "Selesaikan" — SavePackingCheckRequest rejects a draft.
    const isLastRound = (packingCheck?.save_count ?? 0) + 1 >= maxThProgress;

    const saveDraft = () => {
        // Weight of MB is weighed every TH Progress round, so even a draft save needs it.
        if (hasAnyDraftValue() && !data.sum_weight_mb?.toString().trim()) {
            const empty = new Set(['sum_weight_mb']);
            setErrorFields(empty);
            toast('Weight of MB wajib diisi setiap TH Progress');
            scrollToFirstError(empty);
            return;
        }
        if (!hasAnyDraftValue()) {
            // Nothing at all is filled — computeEmptyRequiredFields() here is the same
            // "everything" set Selesaikan would show (minus photos, which aren't part of this
            // check), so Simpan gets the same clear count + red-border highlighting.
            const empty = computeEmptyRequiredFields();
            setErrorFields(empty);
            toast(`${empty.size} bagian masih kosong — isi minimal satu untuk menyimpan progress.`);
            scrollToFirstError(empty);
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: false }));
        put(`/batches/${batch.id}/packing-check`, { preserveState: true, onSuccess: () => setData(blankRoundForm()), onError: showProgressError });
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
                            <InfoField label="Bulk Code" value={batch.bulk_code} />
                            <InfoField label="Line Filling" value={lineLabel(batch.master_line)} />
                            <InfoField label="IPC ID" value={inspectorName} />
                            <InfoField label="TH Progress" value={`${packingCheck?.save_count ?? 0} / ${maxThProgress}`} />
                            <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                        </div>

                        {canFillMasterBox && (
                            <div className="border-primary/30 bg-primary/[0.06] flex flex-col gap-2.5 rounded-[20px] border px-[18px] py-3.5 md:flex-row md:items-center md:justify-between">
                                <p className="text-foreground text-[13px] font-medium">
                                    <b>Weight Master Box</b> belum diisi di Start Inspection. Isi sekarang — hanya bisa diisi sekali.
                                </p>
                                <Link
                                    href={`/batches/${batch.id}/startup-inspection`}
                                    className="bg-primary flex h-9 shrink-0 items-center justify-center rounded-full px-4 text-[12.5px] font-bold whitespace-nowrap text-white"
                                >
                                    Isi Weight Master Box
                                </Link>
                            </div>
                        )}

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
                                        <div key={key} id={key} className="flex flex-col gap-2">
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
                                <Label htmlFor="master_line_id" className="text-muted-foreground text-xs font-semibold">
                                    Line Packing
                                </Label>
                                <Select
                                    value={data.master_line_id ?? ''}
                                    onValueChange={(value) => {
                                        setData('master_line_id', value);
                                        setErrorFields((prev) => {
                                            const n = new Set(prev);
                                            n.delete('master_line_id');
                                            return n;
                                        });
                                    }}
                                    disabled={isReadOnly}
                                >
                                    <SelectTrigger
                                        id="master_line_id"
                                        className={`${inputClass} ${errorFields.has('master_line_id') ? errorBorder : ''}`}
                                    >
                                        <SelectValue placeholder="Pilih line packing" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {lines.map((line) => (
                                            <SelectItem key={line.id} value={String(line.id)}>
                                                {line.code} — {line.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.master_line_id} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="standard_weight_mb" className="text-muted-foreground text-xs font-semibold">
                                    Std Bruto MB{standardWeightMbLocked && ' (terkunci sejak TH Progress 1)'}
                                </Label>
                                <Input
                                    id="standard_weight_mb"
                                    type="text"
                                    inputMode="decimal"
                                    placeholder="Contoh: 1920-2000"
                                    className={`${inputClass} ${errorFields.has('standard_weight_mb') ? errorBorder : ''}`}
                                    value={data.standard_weight_mb ?? ''}
                                    onChange={(e) => {
                                        setData('standard_weight_mb', e.target.value);
                                        setErrorFields((prev) => {
                                            const n = new Set(prev);
                                            n.delete('standard_weight_mb');
                                            return n;
                                        });
                                    }}
                                    disabled={isReadOnly || standardWeightMbLocked}
                                />
                                <InputError message={errors.standard_weight_mb} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="sum_weight_mb" className="text-muted-foreground text-xs font-semibold">
                                    Weight of MB
                                </Label>
                                <Input
                                    id="sum_weight_mb"
                                    type="number"
                                    step="1"
                                    inputMode="numeric"
                                    className={`${inputClass} ${errorFields.has('sum_weight_mb') ? errorBorder : ''}`}
                                    value={data.sum_weight_mb ?? ''}
                                    onChange={(e) => {
                                        // Whole numbers only — the decimal part is dropped as it's typed.
                                        setData('sum_weight_mb', e.target.value.split(/[.,]/)[0]);
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
                                    Machines Coding{codingMachineLocked && ' (terkunci sejak TH Progress 1)'}
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
                            <div id="weighing_data" className="flex flex-col gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold">
                                    Data Timbang{weighingDataLocked && ' (terkunci sejak TH Progress 1)'}
                                </Label>
                                <div className={errorFields.has('weighing_data') ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                    <ChipToggleGroup
                                        name="weighing_data"
                                        options={weighingDataOptions}
                                        value={data.weighing_data ?? ''}
                                        onChange={(value) => {
                                            setData('weighing_data', value);
                                            setErrorFields((prev) => {
                                                const n = new Set(prev);
                                                n.delete('weighing_data');
                                                return n;
                                            });
                                        }}
                                        disabled={isReadOnly || weighingDataLocked}
                                    />
                                </div>
                                <InputError message={errors.weighing_data} />
                            </div>
                            <div className="col-span-full grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {PHOTO_FIELDS.map(({ key, label }) => (
                                    <div key={key} id={key} className="flex flex-col gap-2">
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
                                            <PhotoLightbox
                                                src={photoUrls[key]!}
                                                alt={`Foto ${label}`}
                                                triggerClassName="h-24 w-24"
                                                imageClassName="border-border h-full w-full rounded-xl border object-cover"
                                            />
                                        )}
                                        <InputError message={errors[`photo_${key}`]} />
                                    </div>
                                ))}
                            </div>
                            <div id="decision" className="col-span-full flex flex-col gap-2">
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
                                                {rev.sum_weight_mb && <span>Weight of MB: {rev.sum_weight_mb}</span>}
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
                            secondaryLabel={isLastRound ? undefined : 'Simpan'}
                            onSecondaryClick={saveDraft}
                            note={[
                                `${answeredCount} dari ${allChecklistKeys.length} item checklist terisi`,
                                isLastRound && `TH Progress ke-${maxThProgress} (terakhir) — wajib Selesaikan`,
                                !previousStageCompleted && 'Selesaikan butuh Filling Check selesai dulu',
                            ]
                                .filter(Boolean)
                                .join(' · ')}
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
