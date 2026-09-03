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

interface PackingCheckData {
    id: number;
    completed_at: string | null;
    created_at: string;
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
    secondary_coding_na: 'Secondary — Coding / NA',
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
}: {
    batch: Batch;
    packingCheck: PackingCheckData | null;
    isReadOnly: boolean;
    checklistGroups: ChecklistGroup[];
    decisions: string[];
    photoUrls: Record<string, string | null>;
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

    const initialChecklistValues = checklistGroups.reduce<Record<string, string>>((acc, group) => {
        Object.keys(group.fields).forEach((key) => {
            acc[key] = (packingCheck?.[key] as string) ?? '';
        });
        return acc;
    }, {});

    const { data, setData, put, processing, errors } = useForm<Record<string, string | null>>({
        ...initialChecklistValues,
        standard_weight_mb: (packingCheck?.standard_weight_mb as string) ?? '',
        sum_weight_mb: (packingCheck?.sum_weight_mb as string) ?? '',
        line_leader_name: (packingCheck?.line_leader_name as string) ?? '',
        coding_machine: (packingCheck?.coding_machine as string) ?? '',
        remarks: (packingCheck?.remarks as string) ?? '',
        decision: (packingCheck?.decision as string) ?? '',
    });

    const allChecklistKeys = useMemo(() => checklistGroups.flatMap((group) => Object.keys(group.fields)), [checklistGroups]);
    const answeredCount = allChecklistKeys.filter((key) => data[key]).length;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty = new Set<string>();
        if (!data.remarks?.trim()) empty.add('remarks');
        if (!data.decision) empty.add('decision');
        allChecklistKeys.forEach((key) => {
            if (!data[key]) empty.add(key);
        });
        if (empty.size) {
            setErrorFields(empty);
            toast(`${empty.size} field wajib belum diisi`);
            return;
        }
        setErrorFields(new Set());
        put(`/batches/${batch.id}/packing-check`);
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
                            <InfoField label="Nama Produk" value={batch.master_product.product_name} full />
                        </div>

                        {checklistGroups.map((group) => {
                            const groupAnswered = Object.keys(group.fields).filter((key) => data[key]).length;
                            return (
                                <AccordionCard
                                    key={group.key}
                                    title={GROUP_TITLES[group.key] ?? group.key}
                                    progress={`${groupAnswered}/${Object.keys(group.fields).length} terisi`}
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

                        <AccordionCard title="Parameter Packing" defaultOpen={false}>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="standard_weight_mb" className="text-muted-foreground text-xs font-semibold">
                                    Standard Weight MB
                                </Label>
                                <Input
                                    id="standard_weight_mb"
                                    type="number"
                                    step="0.0001"
                                    className={inputClass}
                                    value={data.standard_weight_mb ?? ''}
                                    onChange={(e) => setData('standard_weight_mb', e.target.value)}
                                    disabled={isReadOnly}
                                />
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
                                    className={inputClass}
                                    value={data.sum_weight_mb ?? ''}
                                    onChange={(e) => setData('sum_weight_mb', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.sum_weight_mb} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="line_leader_name" className="text-muted-foreground text-xs font-semibold">
                                    Line Leader
                                </Label>
                                <Input
                                    id="line_leader_name"
                                    className={inputClass}
                                    value={data.line_leader_name ?? ''}
                                    onChange={(e) => setData('line_leader_name', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.line_leader_name} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="coding_machine" className="text-muted-foreground text-xs font-semibold">
                                    Coding Machine
                                </Label>
                                <Input
                                    id="coding_machine"
                                    className={inputClass}
                                    value={data.coding_machine ?? ''}
                                    onChange={(e) => setData('coding_machine', e.target.value)}
                                    disabled={isReadOnly}
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
                                            className="border-border bg-background flex h-[46px] items-center justify-center gap-2 rounded-xl border-[1.5px] px-3.5 text-[13.5px] font-bold disabled:cursor-not-allowed disabled:opacity-60"
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
                    </div>

                    {!isReadOnly && (
                        <StickySaveBar
                            label="Simpan & Lanjut"
                            processing={processing}
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
