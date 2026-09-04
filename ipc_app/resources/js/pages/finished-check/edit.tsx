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
import { FormEventHandler, useState } from 'react';

const PHOTO_FIELDS: { key: string; label: string }[] = [
    { key: 'wi_number', label: 'WI Number' },
    { key: 'exp_date', label: 'Exp Date' },
    { key: 'color', label: 'Color' },
];

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
}

interface FinishedCheckData {
    id: number;
    completed_at: string | null;
    created_at: string;
    user?: { name: string } | null;
    [key: string]: unknown;
}

interface SampleGroup {
    key: string;
    label: string;
    parameters: Record<string, string>;
}

interface SampleRow {
    ac: number | string | null;
    cd: number | string | null;
    md: number | string | null;
    mnd: number | string | null;
}

type SampleData = { ac: string; cd: string; md: string; mnd: string };

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const inputClass =
    'h-[46px] rounded-xl border-[1.5px] border-border bg-background px-3.5 text-[14.5px] font-semibold text-foreground placeholder:text-muted-foreground/50';
const errorBorder = 'border-destructive ring-1 ring-destructive';

// The header-level fields SaveFinishedCheckRequest actually requires on finalize — the 19-group
// AQL sample grid is deliberately excluded (see the request's doc comment).
const REQUIRED_FIELDS = [
    'quantity_wi',
    'masterbox',
    'no_pallet_qty',
    'quantity_sampling_aql',
    'quantity_sample_aql_cd',
    'quantity_sample_aql_md',
    'quantity_sample_aql_mnd',
    'quantity_special_inspection',
    'quantity_special_inspection_cd',
    'quantity_special_inspection_md',
    'quantity_special_inspection_mnd',
    'line_leader_name',
    'disposition',
    'remarks',
];

export default function FinishedCheckEdit({
    batch,
    finishedCheck,
    samples,
    isReadOnly,
    sampleGroups,
    dispositions,
    photoUrls,
}: {
    batch: Batch;
    finishedCheck: FinishedCheckData | null;
    samples: Record<string, SampleRow>;
    isReadOnly: boolean;
    sampleGroups: SampleGroup[];
    dispositions: string[];
    photoUrls: Record<string, string | null>;
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();
    const [cameraField, setCameraField] = useState<string | null>(null);
    const [errorFields, setErrorFields] = useState<Set<string>>(new Set());

    const uploadPhoto = (field: string, file: File) => {
        router.post(`/batches/${batch.id}/finished-check/photo/${field}`, { photo: file }, { forceFormData: true, preserveScroll: true });
    };

    const inspectorName = finishedCheck?.user?.name ?? props.auth.user.name;

    const allParameterKeys = sampleGroups.flatMap((group) => Object.keys(group.parameters));

    const initialSamples = allParameterKeys.reduce<Record<string, SampleData>>((acc, key) => {
        const row = samples[key];
        acc[key] = {
            ac: row?.ac?.toString() ?? '',
            cd: row?.cd?.toString() ?? '',
            md: row?.md?.toString() ?? '',
            mnd: row?.mnd?.toString() ?? '',
        };
        return acc;
    }, {});

    const { data, setData, put, transform, processing, errors } = useForm<Record<string, string | Record<string, SampleData> | null>>({
        quantity_wi: (finishedCheck?.quantity_wi as string) ?? '',
        masterbox: (finishedCheck?.masterbox as string) ?? '',
        no_pallet_qty: (finishedCheck?.no_pallet_qty as string) ?? '',
        quantity_sampling_aql: (finishedCheck?.quantity_sampling_aql as string) ?? '',
        quantity_sample_aql_cd: (finishedCheck?.quantity_sample_aql_cd as string) ?? '',
        quantity_sample_aql_md: (finishedCheck?.quantity_sample_aql_md as string) ?? '',
        quantity_sample_aql_mnd: (finishedCheck?.quantity_sample_aql_mnd as string) ?? '',
        quantity_special_inspection: (finishedCheck?.quantity_special_inspection as string) ?? '',
        quantity_special_inspection_cd: (finishedCheck?.quantity_special_inspection_cd as string) ?? '',
        quantity_special_inspection_md: (finishedCheck?.quantity_special_inspection_md as string) ?? '',
        quantity_special_inspection_mnd: (finishedCheck?.quantity_special_inspection_mnd as string) ?? '',
        line_leader_name: (finishedCheck?.line_leader_name as string) ?? '',
        remarks: (finishedCheck?.remarks as string) ?? '',
        disposition: (finishedCheck?.disposition as string) ?? '',
        samples: initialSamples,
    });

    const samplesData = (data.samples as Record<string, SampleData>) ?? {};
    const filledSampleCount = allParameterKeys.filter((key) => {
        const row = samplesData[key];
        return row && (row.ac || row.cd || row.md || row.mnd);
    }).length;

    const updateSample = (key: string, field: keyof SampleData, value: string) => {
        setData('samples', {
            ...samplesData,
            [key]: { ...samplesData[key], [field]: value },
        });
    };

    const setField = (key: string, value: string) => {
        setData(key, value);
        setErrorFields((prev) => {
            if (!prev.has(key)) return prev;
            const next = new Set(prev);
            next.delete(key);
            return next;
        });
    };

    const hasAnyDraftValue = () => {
        const headerHasValue = REQUIRED_FIELDS.some((key) => Boolean((data[key] as string)?.toString().trim()));
        return headerHasValue || filledSampleCount > 0;
    };

    // Reused for the Simpan/empty-progress highlight below — the AQL sample grid isn't included
    // since no individual cell there is ever "required" and the page has no per-cell error state.
    const computeEmptyRequiredFields = () => {
        const empty = new Set<string>();
        REQUIRED_FIELDS.forEach((key) => {
            if (!(data[key] as string)?.toString().trim()) empty.add(key);
        });
        return empty;
    };

    const saveDraft = () => {
        if (!hasAnyDraftValue()) {
            // Nothing at all is filled — computeEmptyRequiredFields() here is the same
            // "everything" set Selesaikan would show (minus photos), so Simpan gets the same
            // clear count + red-border highlighting.
            const empty = computeEmptyRequiredFields();
            setErrorFields(empty);
            toast(`${empty.size} bagian masih kosong — isi minimal satu untuk menyimpan progress.`);
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: false }));
        put(`/batches/${batch.id}/finished-check`, { preserveState: true });
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
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: true }));
        put(`/batches/${batch.id}/finished-check`);
    };

    return (
        <IpcShell
            title="Finished Check"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}`}
            headerActions={
                isReadOnly ? (
                    <span className="rounded-full bg-green-100 px-3.5 py-1.5 text-[12.5px] font-bold whitespace-nowrap text-green-800">
                        Selesai — read only
                    </span>
                ) : (
                    <span className="bg-primary/[0.08] text-primary rounded-full px-3 py-1.5 text-[12.5px] font-bold whitespace-nowrap">
                        {filledSampleCount}/{allParameterKeys.length} sample
                    </span>
                )
            }
        >
            <Head title={`Finished Check — ${batch.no_batch}`} />
            <Toast message={message} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-2 md:px-8">
                        {/* Info header — mirrors the legacy screen's top info block. */}
                        <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                            <InfoField label="Tanggal" value={formatDateTime(finishedCheck?.created_at ?? batch.created_at)} />
                            <InfoField label="FG Code" value={batch.master_product.fg_code} />
                            <InfoField label="No. Batch" value={batch.no_batch} />
                            <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                            <InfoField label="IPC ID" value={inspectorName} />
                            <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                        </div>

                        {/* WI_NUMBER/EXP_DATE/COLOR are camera-only in legacy — no text/date value. */}
                        <div className="border-border-soft bg-card grid grid-cols-1 gap-4 rounded-[20px] border p-[18px] sm:grid-cols-3">
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

                        <AccordionCard title="Kuantitas & Sampling">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="quantity_wi" className="text-muted-foreground text-xs font-semibold">
                                    Quantity WI
                                </Label>
                                <Input
                                    id="quantity_wi"
                                    type="number"
                                    step="0.01"
                                    className={`${inputClass} ${errorFields.has('quantity_wi') ? errorBorder : ''}`}
                                    value={(data.quantity_wi as string) ?? ''}
                                    onChange={(e) => setField('quantity_wi', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.quantity_wi} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="masterbox" className="text-muted-foreground text-xs font-semibold">
                                    Masterbox
                                </Label>
                                <Input
                                    id="masterbox"
                                    type="number"
                                    step="0.01"
                                    className={`${inputClass} ${errorFields.has('masterbox') ? errorBorder : ''}`}
                                    value={(data.masterbox as string) ?? ''}
                                    onChange={(e) => setField('masterbox', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.masterbox} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="no_pallet_qty" className="text-muted-foreground text-xs font-semibold">
                                    No. Pallet & Qty
                                </Label>
                                <Input
                                    id="no_pallet_qty"
                                    type="number"
                                    step="0.01"
                                    className={`${inputClass} ${errorFields.has('no_pallet_qty') ? errorBorder : ''}`}
                                    value={(data.no_pallet_qty as string) ?? ''}
                                    onChange={(e) => setField('no_pallet_qty', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.no_pallet_qty} />
                            </div>

                            <div className="col-span-full grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <QuantityField
                                    label="Quantity Sampling AQL"
                                    id="quantity_sampling_aql"
                                    value={(data.quantity_sampling_aql as string) ?? ''}
                                    onChange={(v) => setField('quantity_sampling_aql', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_sampling_aql}
                                    invalid={errorFields.has('quantity_sampling_aql')}
                                />
                                <QuantityField
                                    label="CD"
                                    id="quantity_sample_aql_cd"
                                    value={(data.quantity_sample_aql_cd as string) ?? ''}
                                    onChange={(v) => setField('quantity_sample_aql_cd', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_sample_aql_cd}
                                    invalid={errorFields.has('quantity_sample_aql_cd')}
                                />
                                <QuantityField
                                    label="MD"
                                    id="quantity_sample_aql_md"
                                    value={(data.quantity_sample_aql_md as string) ?? ''}
                                    onChange={(v) => setField('quantity_sample_aql_md', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_sample_aql_md}
                                    invalid={errorFields.has('quantity_sample_aql_md')}
                                />
                                <QuantityField
                                    label="mD"
                                    id="quantity_sample_aql_mnd"
                                    value={(data.quantity_sample_aql_mnd as string) ?? ''}
                                    onChange={(v) => setField('quantity_sample_aql_mnd', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_sample_aql_mnd}
                                    invalid={errorFields.has('quantity_sample_aql_mnd')}
                                />
                            </div>

                            <div className="col-span-full grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <QuantityField
                                    label="Quantity Special Inspection"
                                    id="quantity_special_inspection"
                                    value={(data.quantity_special_inspection as string) ?? ''}
                                    onChange={(v) => setField('quantity_special_inspection', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_special_inspection}
                                    invalid={errorFields.has('quantity_special_inspection')}
                                />
                                <QuantityField
                                    label="CD"
                                    id="quantity_special_inspection_cd"
                                    value={(data.quantity_special_inspection_cd as string) ?? ''}
                                    onChange={(v) => setField('quantity_special_inspection_cd', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_special_inspection_cd}
                                    invalid={errorFields.has('quantity_special_inspection_cd')}
                                />
                                <QuantityField
                                    label="MD"
                                    id="quantity_special_inspection_md"
                                    value={(data.quantity_special_inspection_md as string) ?? ''}
                                    onChange={(v) => setField('quantity_special_inspection_md', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_special_inspection_md}
                                    invalid={errorFields.has('quantity_special_inspection_md')}
                                />
                                <QuantityField
                                    label="mD"
                                    id="quantity_special_inspection_mnd"
                                    value={(data.quantity_special_inspection_mnd as string) ?? ''}
                                    onChange={(v) => setField('quantity_special_inspection_mnd', v)}
                                    disabled={isReadOnly}
                                    error={errors.quantity_special_inspection_mnd}
                                    invalid={errorFields.has('quantity_special_inspection_mnd')}
                                />
                            </div>
                        </AccordionCard>

                        {sampleGroups.map((group) => {
                            const keys = Object.keys(group.parameters);
                            const filled = keys.filter((key) => {
                                const row = samplesData[key];
                                return row && (row.ac || row.cd || row.md || row.mnd);
                            }).length;
                            return (
                                <AccordionCard
                                    key={group.key}
                                    title={`Quantity Sample ${group.label}`}
                                    progress={`${filled}/${keys.length} terisi`}
                                    complete={filled === keys.length}
                                    defaultOpen={false}
                                >
                                    {Object.entries(group.parameters).map(([key, label]) => (
                                        <div key={key} className="border-border-soft col-span-full rounded-xl border p-3">
                                            <p className="mb-2.5 text-[13px] font-bold">{label}</p>
                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                {(['ac', 'cd', 'md', 'mnd'] as const).map((field) => (
                                                    <div key={field} className="flex flex-col gap-1.5">
                                                        <Label className="text-muted-foreground/70 text-[10.5px] font-semibold uppercase">
                                                            {field === 'mnd' ? 'mD' : field.toUpperCase()}
                                                        </Label>
                                                        <Input
                                                            type="number"
                                                            step="1"
                                                            min="0"
                                                            className={inputClass}
                                                            value={samplesData[key]?.[field] ?? ''}
                                                            onChange={(e) => updateSample(key, field, e.target.value)}
                                                            disabled={isReadOnly}
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </AccordionCard>
                            );
                        })}

                        <AccordionCard title="Keputusan">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="line_leader_name" className="text-muted-foreground text-xs font-semibold">
                                    Line Leader
                                </Label>
                                <Input
                                    id="line_leader_name"
                                    className={`${inputClass} ${errorFields.has('line_leader_name') ? errorBorder : ''}`}
                                    value={(data.line_leader_name as string) ?? ''}
                                    onChange={(e) => setField('line_leader_name', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.line_leader_name} />
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Disposition</Label>
                                <div className={errorFields.has('disposition') ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                    <ChipToggleGroup
                                        name="disposition"
                                        options={dispositions}
                                        value={(data.disposition as string) ?? ''}
                                        onChange={(value) => setField('disposition', value)}
                                        disabled={isReadOnly}
                                    />
                                </div>
                                <InputError message={errors.disposition} />
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label htmlFor="remarks" className="text-muted-foreground text-xs font-semibold">
                                    Catatan / Remarks
                                </Label>
                                <Textarea
                                    id="remarks"
                                    rows={2}
                                    className={`border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px] ${errorFields.has('remarks') ? errorBorder : ''}`}
                                    value={(data.remarks as string) ?? ''}
                                    onChange={(e) => setField('remarks', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.remarks} />
                            </div>
                        </AccordionCard>
                    </div>

                    {!isReadOnly && (
                        <StickySaveBar
                            label="Simpan & Selesaikan"
                            processing={processing}
                            secondaryLabel="Simpan"
                            onSecondaryClick={saveDraft}
                            note={`${filledSampleCount} dari ${allParameterKeys.length} sample terisi`}
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

function QuantityField({
    label,
    id,
    value,
    onChange,
    disabled,
    error,
    invalid,
}: {
    label: string;
    id: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    error?: string;
    invalid?: boolean;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id} className="text-muted-foreground text-xs font-semibold">
                {label}
            </Label>
            <Input
                id={id}
                type="number"
                step="1"
                min="0"
                className={`${inputClass} ${invalid ? errorBorder : ''}`}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                disabled={disabled}
            />
            <InputError message={error} />
        </div>
    );
}
