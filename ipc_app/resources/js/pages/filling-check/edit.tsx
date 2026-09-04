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
import { type FormDataConvertible } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Batch {
    id: number;
    no_batch: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
    startup_check: {
        filling_range_min: string | null;
        filling_range_max: string | null;
        density: string | null;
        average_of_empty_bottle_weight: string | null;
        line_leader_name: string | null;
    } | null;
}

interface FillingCheckSample {
    sample_no: number;
    weight_value: string | null;
    weight_result: string | null;
}

interface FillingCheckSampleInput {
    sample_no: number;
    weight_value: string | null;
    [key: string]: FormDataConvertible;
}

interface FillingCheckRevisionSample {
    sample_no: number;
    weight_value: string | null;
    weight_result: string | null;
}

interface FillingCheckRevision {
    id: number;
    revision_no: number;
    finalize: boolean;
    sample_bulk_odor_status: string | null;
    sample_leakage_test_status: string | null;
    remarks: string | null;
    decision: string | null;
    average_weight: string | null;
    created_at: string;
    user: { name: string } | null;
    samples: FillingCheckRevisionSample[];
}

interface FillingCheckData {
    id: number;
    completed_at: string | null;
    created_at: string;
    save_count: number;
    samples: FillingCheckSample[];
    revisions: FillingCheckRevision[];
    sample_bulk_odor_status: string | null;
    sample_leakage_test_status: string | null;
    remarks: string | null;
    decision: string | null;
    average_weight: string | null;
    user?: { name: string } | null;
}

const SAMPLE_COUNT = 10;
const CONFORM_OPTIONS = ['Conform', 'Not Conform'];
const errorBorder = 'border-destructive ring-1 ring-destructive';

const inputClass =
    'h-[46px] rounded-xl border-[1.5px] border-border bg-background px-3.5 text-[14.5px] font-semibold text-foreground placeholder:text-muted-foreground/50';

const readOnlyFieldClass = `${inputClass} flex items-center bg-muted/40`;

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function toNumber(value: string | null | undefined): number | null {
    if (value === null || value === undefined || value === '') return null;
    const parsed = parseFloat(value);
    return Number.isNaN(parsed) ? null : parsed;
}

export default function FillingCheckEdit({
    batch,
    fillingCheck,
    isReadOnly,
    decisions,
    colorPhotoUrl,
}: {
    batch: Batch;
    fillingCheck: FillingCheckData | null;
    isReadOnly: boolean;
    decisions: string[];
    colorPhotoUrl: string | null;
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const [cameraOpen, setCameraOpen] = useState(false);
    const { message, toast } = useToast();
    const [errorFields, setErrorFields] = useState<Set<string>>(new Set());

    const resultBySample = new Map((fillingCheck?.samples ?? []).map((row) => [row.sample_no, row.weight_result ?? null]));

    const initialSamples = (): FillingCheckSampleInput[] => {
        const bySample = new Map((fillingCheck?.samples ?? []).map((row) => [row.sample_no, row.weight_value]));
        return Array.from({ length: SAMPLE_COUNT }, (_, i) => ({
            sample_no: i + 1,
            weight_value: bySample.get(i + 1) ?? null,
        }));
    };

    const { data, setData, put, transform, processing, errors } = useForm<{
        sample_bulk_odor_status: string;
        sample_leakage_test_status: string;
        remarks: string;
        decision: string;
        samples: FillingCheckSampleInput[];
    }>({
        sample_bulk_odor_status: fillingCheck?.sample_bulk_odor_status ?? '',
        sample_leakage_test_status: fillingCheck?.sample_leakage_test_status ?? '',
        remarks: fillingCheck?.remarks ?? '',
        decision: fillingCheck?.decision ?? '',
        samples: initialSamples(),
    });

    const setWeight = (sampleNo: number, value: string) => {
        setData(
            'samples',
            data.samples.map((row) => (row.sample_no === sampleNo ? { ...row, weight_value: value === '' ? null : value } : row)),
        );
    };

    // Shared by both submit paths so the "what's empty" highlighting always matches the message:
    // Selesaikan needs every one of these non-blank; Simpan only needs the resulting set to not
    // be literally everything (see hasAnyDraftValue below).
    const computeEmptyFields = () => {
        const empty = new Set<string>();
        if (!data.sample_bulk_odor_status) empty.add('sample_bulk_odor_status');
        if (!data.sample_leakage_test_status) empty.add('sample_leakage_test_status');
        if (!data.remarks.trim()) empty.add('remarks');
        if (!data.decision) empty.add('decision');
        if (data.samples.some((row) => !row.weight_value)) empty.add('samples');
        return empty;
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty = computeEmptyFields();
        if (empty.size) {
            setErrorFields(empty);
            toast(`${empty.size} field wajib belum diisi untuk Selesaikan`);
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: true }));
        put(`/batches/${batch.id}/filling-check`);
    };

    const blankForm = () => ({
        sample_bulk_odor_status: '',
        sample_leakage_test_status: '',
        remarks: '',
        decision: '',
        samples: Array.from({ length: SAMPLE_COUNT }, (_, i) => ({ sample_no: i + 1, weight_value: null })),
    });

    const hasAnyDraftValue = () =>
        Boolean(data.sample_bulk_odor_status) ||
        Boolean(data.sample_leakage_test_status) ||
        Boolean(data.remarks.trim()) ||
        Boolean(data.decision) ||
        data.samples.some((row) => Boolean(row.weight_value));

    // A Decision or Sample Check call recorded with zero weight samples behind it isn't real
    // progress — it's an assessment with nothing measured. Same intent as hasAnyDraftValue()
    // above, one level stricter, mirroring SaveFillingCheckRequest::validateDraft().
    const hasAssessmentWithoutSamples = () =>
        (Boolean(data.decision) || Boolean(data.sample_bulk_odor_status) || Boolean(data.sample_leakage_test_status)) &&
        !data.samples.some((row) => Boolean(row.weight_value));

    const saveDraft = () => {
        if (!hasAnyDraftValue()) {
            // Nothing at all is filled — computeEmptyFields() here is the same "everything" set
            // Selesaikan would show, so Simpan gets the same clear count + red-border highlighting.
            const empty = computeEmptyFields();
            setErrorFields(empty);
            toast(`${empty.size} bagian masih kosong — isi minimal satu untuk menyimpan progress.`);
            return;
        }
        if (hasAssessmentWithoutSamples()) {
            setErrorFields(new Set(['samples']));
            toast('Isi minimal satu sample berat sebelum mencatat Decision/Sample Check.');
            return;
        }
        setErrorFields(new Set());
        transform((current) => ({ ...current, finalize: false }));
        put(`/batches/${batch.id}/filling-check`, { preserveState: true, onSuccess: () => setData(blankForm()) });
    };

    const uploadColorPhoto = (file: File) => {
        router.post(`/batches/${batch.id}/filling-check/color-photo`, { photo: file }, { forceFormData: true, preserveScroll: true });
    };

    const inspectorName = fillingCheck?.user?.name ?? props.auth.user.name;

    // Live per-sample result, mirroring the real legacy formula (Controls/625.json, Label5.Text):
    // (Value(WEIGHT_SAMPLE_N.Text) - Start.AVERAGE_OF_EMPTY_BOTTLE_WEIGHT) / Start.DENSITY —
    // recalculated as the operator types, same as legacy's own reactive label, instead of only
    // after a save. Power Apps' Value() coerces a blank text input to 0 rather than blank, so an
    // empty sample shows a real (if nonsensical) negative number instead of a dash — direct user
    // feedback 2026-09-04 confirmed this is legacy behavior they want mirrored exactly, not a
    // bug to fix, so an unfilled weight is treated as 0 here too. This is purely a live on-screen
    // preview: SaveFillingCheck's persisted weight_result/average_weight still only ever consider
    // samples that actually have a value (see that action's `->filter(fn ($row) => filled(...))`)
    // — replicating legacy's blank-reads-as-0 quirk in stored data would corrupt the average.
    const density = toNumber(batch.startup_check?.density);
    const avgBottleWeight = toNumber(batch.startup_check?.average_of_empty_bottle_weight);

    const liveResult = (weightValue: string | null): string | null => {
        if (density === null || density === 0 || avgBottleWeight === null) return null;
        const value = toNumber(weightValue) ?? 0;
        return ((value - avgBottleWeight) / density).toFixed(4);
    };

    // Live Average Weight, mirroring the exact legacy formula (Controls/625.json, Label3_1.Text):
    // Text((Label5.Text + Label5_1.Text + ... + Label5_9.Text) / 10, "#,##0.00") — sum of all 10
    // per-sample result labels above (blank ones included, via liveResult's same 0-coercion) then
    // always divided by 10, recalculated on every keystroke exactly like the reactive legacy
    // label. Direct user feedback 2026-09-04: don't invent a different (e.g. filled-only) average
    // for the live view, and don't wait for a save round-trip — mirror legacy's own live formula.
    const liveAverageWeight = (): string | null => {
        if (density === null || density === 0 || avgBottleWeight === null) return null;
        const sum = data.samples.reduce((total, row) => total + parseFloat(liveResult(row.weight_value) ?? '0'), 0);
        return (sum / SAMPLE_COUNT).toFixed(2);
    };

    const revisions = [...(fillingCheck?.revisions ?? [])].sort((a, b) => b.revision_no - a.revision_no);

    return (
        <IpcShell
            title="Filling Check"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}`}
            headerActions={
                isReadOnly ? (
                    <span className="rounded-full bg-green-100 px-3.5 py-1.5 text-[12.5px] font-bold whitespace-nowrap text-green-800">
                        Selesai — read only
                    </span>
                ) : undefined
            }
        >
            <Head title={`Filling Check — ${batch.no_batch}`} />
            <Toast message={message} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-2 md:px-8">
                        {/* Info header — mirrors the legacy screen's top info block so QC has full
                            context (product/batch/line/progress) without navigating away. */}
                        <div className="border-border-soft bg-card grid grid-cols-2 gap-3 rounded-[20px] border p-[18px] md:grid-cols-4 md:gap-4">
                            <InfoField label="Tanggal" value={formatDateTime(fillingCheck?.created_at ?? batch.created_at)} />
                            <InfoField label="FG Code" value={batch.master_product.fg_code} />
                            <InfoField label="No. Batch" value={batch.no_batch} />
                            <InfoField label="Bulk Code" value={batch.master_product.bulk_code} />
                            <InfoField label="Line" value={`${batch.master_line.name} (${batch.master_line.code})`} />
                            <InfoField label="IPC ID" value={inspectorName} />
                            <InfoField label="TH Progress" value={String(fillingCheck?.save_count ?? 0)} />
                            <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                        </div>

                        <AccordionCard title="Sample Check">
                            <div className="flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Sample Bulk & Odor (5 Sample)</Label>
                                <div className={errorFields.has('sample_bulk_odor_status') ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                    <ChipToggleGroup
                                        name="sample_bulk_odor_status"
                                        options={CONFORM_OPTIONS}
                                        value={data.sample_bulk_odor_status}
                                        onChange={(value) => {
                                            setData('sample_bulk_odor_status', value);
                                            setErrorFields((prev) => {
                                                const n = new Set(prev);
                                                n.delete('sample_bulk_odor_status');
                                                return n;
                                            });
                                        }}
                                        disabled={isReadOnly}
                                    />
                                </div>
                                <InputError message={errors.sample_bulk_odor_status} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Sample Leakage Test (5 Sample)</Label>
                                <div
                                    className={
                                        errorFields.has('sample_leakage_test_status') ? 'outline-destructive rounded-xl outline outline-2' : ''
                                    }
                                >
                                    <ChipToggleGroup
                                        name="sample_leakage_test_status"
                                        options={CONFORM_OPTIONS}
                                        value={data.sample_leakage_test_status}
                                        onChange={(value) => {
                                            setData('sample_leakage_test_status', value);
                                            setErrorFields((prev) => {
                                                const n = new Set(prev);
                                                n.delete('sample_leakage_test_status');
                                                return n;
                                            });
                                        }}
                                        disabled={isReadOnly}
                                    />
                                </div>
                                <InputError message={errors.sample_leakage_test_status} />
                            </div>
                        </AccordionCard>

                        <AccordionCard title="Parameter Filling">
                            <div className="flex flex-col gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold">Min Volume (dari Startup Check)</Label>
                                <div className={readOnlyFieldClass}>{batch.startup_check?.filling_range_min ?? '—'}</div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold">Max Weight (dari Startup Check)</Label>
                                <div className={readOnlyFieldClass}>{batch.startup_check?.filling_range_max ?? '—'}</div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold">Line Leader (dari Startup Check)</Label>
                                <div className={readOnlyFieldClass}>{batch.startup_check?.line_leader_name ?? '—'}</div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label className="text-muted-foreground text-xs font-semibold">Color</Label>
                                <button
                                    type="button"
                                    disabled={isReadOnly}
                                    onClick={() => setCameraOpen(true)}
                                    className="border-border bg-background flex h-[46px] items-center justify-center gap-2 rounded-xl border-[1.5px] px-3.5 text-[13.5px] font-bold disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <Camera className="size-4" strokeWidth={2.2} />
                                    {colorPhotoUrl ? 'Ganti Foto' : 'Ambil Foto'}
                                </button>
                                {colorPhotoUrl && (
                                    <img src={colorPhotoUrl} alt="Foto warna" className="border-border h-24 w-24 rounded-xl border object-cover" />
                                )}
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Decision</Label>
                                <div className={errorFields.has('decision') ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                    <ChipToggleGroup
                                        name="decision"
                                        options={decisions}
                                        value={data.decision}
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
                                    Remarks
                                </Label>
                                <Textarea
                                    id="remarks"
                                    className={`border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px] ${errorFields.has('remarks') ? errorBorder : ''}`}
                                    value={data.remarks}
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

                        <AccordionCard title="Weight Samples" progress="10 sample">
                            <div className="col-span-full">
                                <InputError message={(errors as Record<string, string>).samples} className="mb-2" />
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                    {data.samples.map((row) => {
                                        const result = liveResult(row.weight_value) ?? resultBySample.get(row.sample_no) ?? null;
                                        return (
                                            <div key={row.sample_no} className="flex flex-col gap-1">
                                                <span className="text-muted-foreground/70 text-center text-[10.5px] font-semibold">
                                                    #{row.sample_no}
                                                </span>
                                                <Input
                                                    id={`weight-${row.sample_no}`}
                                                    type="number"
                                                    step="0.0001"
                                                    className={`h-11 rounded-[11px] border-[1.5px] px-1 text-center text-[13px] font-semibold ${errorFields.has('samples') && !row.weight_value ? 'border-destructive ring-destructive ring-1' : 'border-border'}`}
                                                    value={row.weight_value ?? ''}
                                                    onChange={(e) => setWeight(row.sample_no, e.target.value)}
                                                    disabled={isReadOnly}
                                                />
                                                <span className="text-muted-foreground text-center text-[10px]">{result ?? '—'}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                                <div className="border-border-soft bg-muted/30 mt-3.5 flex items-center justify-between rounded-xl border px-3.5 py-2.5">
                                    <span className="text-muted-foreground text-xs font-semibold">Average Weight</span>
                                    <span className="text-[15px] font-bold">{liveAverageWeight() ?? fillingCheck?.average_weight ?? '—'}</span>
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Result per sample dan Average Weight dihitung langsung real-time dari Density & Average of Empty Bottle Weight
                                    milik Startup Check, sama seperti kalkulasi live di aplikasi lama — tidak perlu disimpan dulu.
                                </p>
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
                                                {rev.average_weight && <span>Avg Weight: {rev.average_weight}</span>}
                                            </div>
                                            {rev.remarks && <p className="text-muted-foreground mt-1 text-[12px]">Remarks: {rev.remarks}</p>}
                                        </div>
                                    ))}
                                </div>
                            </AccordionCard>
                        )}
                    </div>

                    {!isReadOnly && (
                        <StickySaveBar label="Simpan & Selesaikan" processing={processing} secondaryLabel="Simpan" onSecondaryClick={saveDraft} />
                    )}
                </form>
            </TwoPane>

            <CameraCaptureDialog open={cameraOpen} onOpenChange={setCameraOpen} onCapture={uploadColorPhoto} title="Ambil Foto Warna" />
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
