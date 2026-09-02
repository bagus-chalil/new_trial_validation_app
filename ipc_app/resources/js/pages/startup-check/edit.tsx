import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { ChipToggleGroup } from '@/components/ipc/chip-toggle-group';
import { StickySaveBar } from '@/components/ipc/sticky-save-bar';
import { TwoPane } from '@/components/ipc/two-pane';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

interface Batch {
    id: number;
    no_batch: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string };
}

interface BottleWeight {
    sample_no: number;
    weight_value: string | null;
}

interface StartupCheckData {
    id: number;
    completed_at: string | null;
    bottle_weights: BottleWeight[];
    [key: string]: unknown;
}

interface ChecklistGroup {
    key: string;
    fields: Record<string, string>;
    options: string[];
}

const DEFAULT_SAMPLE_COUNT = 30;

const GROUP_TITLES: Record<string, string> = {
    availability: 'Ketersediaan',
    conform: 'Conform / Not Conform',
    pm_bom_match: 'PM / BOM Match',
    bulk_status: 'Status Bulk',
    identity_line_board: 'Identity Line Board',
};

const inputClass =
    'h-[46px] rounded-xl border-[1.5px] border-border bg-background px-3.5 text-[14.5px] font-semibold text-foreground placeholder:text-muted-foreground/50';

export default function StartupCheckEdit({
    batch,
    startupCheck,
    isReadOnly,
    checklistGroups,
}: {
    batch: Batch;
    startupCheck: StartupCheckData | null;
    isReadOnly: boolean;
    checklistGroups: ChecklistGroup[];
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const initialWeights = (): BottleWeight[] => {
        const bySample = new Map((startupCheck?.bottle_weights ?? []).map((row) => [row.sample_no, row.weight_value]));
        const maxSample = Math.max(DEFAULT_SAMPLE_COUNT, ...(startupCheck?.bottle_weights.map((r) => r.sample_no) ?? [0]));
        return Array.from({ length: maxSample }, (_, i) => ({
            sample_no: i + 1,
            weight_value: bySample.get(i + 1) ?? null,
        }));
    };

    const [weights, setWeights] = useState<BottleWeight[]>(initialWeights);

    const initialChecklistValues = checklistGroups.reduce<Record<string, string>>((acc, group) => {
        Object.keys(group.fields).forEach((key) => {
            acc[key] = (startupCheck?.[key] as string) ?? '';
        });
        return acc;
    }, {});

    const { data, setData, put, transform, processing, errors } = useForm<Record<string, string | null>>({
        ...initialChecklistValues,
        validation_report_status: (startupCheck?.validation_report_status as string) ?? '',
        filling_range_min: (startupCheck?.filling_range_min as string) ?? '',
        filling_range_max: (startupCheck?.filling_range_max as string) ?? '',
        density: (startupCheck?.density as string) ?? '',
        heating: (startupCheck?.heating as string) ?? '',
        line_leader_name: (startupCheck?.line_leader_name as string) ?? '',
        operator_name: (startupCheck?.operator_name as string) ?? '',
        remarks: (startupCheck?.remarks as string) ?? '',
    });

    const allChecklistKeys = useMemo(() => checklistGroups.flatMap((group) => Object.keys(group.fields)), [checklistGroups]);
    const answeredCount = allChecklistKeys.filter((key) => data[key]).length;
    const progressPct = allChecklistKeys.length > 0 ? Math.round((answeredCount / allChecklistKeys.length) * 100) : 0;

    const setWeight = (sampleNo: number, value: string) => {
        setWeights((prev) => prev.map((row) => (row.sample_no === sampleNo ? { ...row, weight_value: value === '' ? null : value } : row)));
    };

    const addSample = () => {
        setWeights((prev) => [...prev, { sample_no: prev.length === 0 ? 1 : Math.max(...prev.map((r) => r.sample_no)) + 1, weight_value: null }]);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        transform((data) => ({ ...data, bottle_weights: weights }));
        put(`/batches/${batch.id}/startup-check`);
    };

    const listPane = <BatchNavList batches={recentBatches} activeId={batch.id} />;

    return (
        <IpcShell
            title="Startup Check"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}`}
            progressPct={isReadOnly ? undefined : progressPct}
            headerActions={
                isReadOnly ? (
                    <Link
                        href={`/batches/${batch.id}/filling-check`}
                        className="flex h-10 items-center gap-1.5 rounded-full bg-green-100 px-3.5 text-[12.5px] font-bold text-green-800"
                    >
                        Selesai · Lanjut ke Filling
                    </Link>
                ) : (
                    <span className="bg-primary/[0.08] text-primary rounded-full px-3 py-1.5 text-[12.5px] font-bold whitespace-nowrap">
                        {answeredCount}/{allChecklistKeys.length}
                    </span>
                )
            }
        >
            <Head title={`Startup Check — ${batch.no_batch}`} />
            <TwoPane list={listPane}>
                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-2 md:px-8">
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
                                            <ChipToggleGroup
                                                name={label}
                                                options={group.options}
                                                value={data[key] ?? ''}
                                                onChange={(value) => setData(key, value)}
                                                disabled={isReadOnly}
                                            />
                                            <InputError message={errors[key]} />
                                        </div>
                                    ))}
                                </AccordionCard>
                            );
                        })}

                        <AccordionCard title="Parameter Filling" defaultOpen={false}>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="filling_range_min" className="text-muted-foreground text-xs font-semibold">
                                    Filling Range Min
                                </Label>
                                <Input
                                    id="filling_range_min"
                                    type="number"
                                    step="0.01"
                                    className={inputClass}
                                    value={data.filling_range_min ?? ''}
                                    onChange={(e) => setData('filling_range_min', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.filling_range_min} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="filling_range_max" className="text-muted-foreground text-xs font-semibold">
                                    Filling Range Max
                                </Label>
                                <Input
                                    id="filling_range_max"
                                    type="number"
                                    step="0.01"
                                    className={inputClass}
                                    value={data.filling_range_max ?? ''}
                                    onChange={(e) => setData('filling_range_max', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.filling_range_max} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="density" className="text-muted-foreground text-xs font-semibold">
                                    Density
                                </Label>
                                <Input
                                    id="density"
                                    type="number"
                                    step="0.0001"
                                    className={inputClass}
                                    value={data.density ?? ''}
                                    onChange={(e) => setData('density', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.density} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="heating" className="text-muted-foreground text-xs font-semibold">
                                    Heating
                                </Label>
                                <Input
                                    id="heating"
                                    className={inputClass}
                                    value={data.heating ?? ''}
                                    onChange={(e) => setData('heating', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.heating} />
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
                                <Label htmlFor="operator_name" className="text-muted-foreground text-xs font-semibold">
                                    Operator
                                </Label>
                                <Input
                                    id="operator_name"
                                    className={inputClass}
                                    value={data.operator_name ?? ''}
                                    onChange={(e) => setData('operator_name', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.operator_name} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="validation_report_status" className="text-muted-foreground text-xs font-semibold">
                                    Validation Report
                                </Label>
                                <Input
                                    id="validation_report_status"
                                    className={inputClass}
                                    value={data.validation_report_status ?? ''}
                                    onChange={(e) => setData('validation_report_status', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.validation_report_status} />
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label htmlFor="remarks" className="text-muted-foreground text-xs font-semibold">
                                    Catatan / Remarks
                                </Label>
                                <Textarea
                                    id="remarks"
                                    rows={2}
                                    className="border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px]"
                                    value={data.remarks ?? ''}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.remarks} />
                            </div>
                        </AccordionCard>

                        <AccordionCard title="Sample Berat Botol" progress={`${weights.length} sample`} defaultOpen={false}>
                            <div className="col-span-full">
                                <InputError message={(errors as Record<string, string>).bottle_weights} className="mb-2" />
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-6 md:grid-cols-10">
                                    {weights.map((row) => (
                                        <div key={row.sample_no} className="flex flex-col gap-1">
                                            <span className="text-muted-foreground/70 text-center text-[10.5px] font-semibold">#{row.sample_no}</span>
                                            <Input
                                                id={`weight-${row.sample_no}`}
                                                type="number"
                                                step="0.0001"
                                                className="border-border h-11 rounded-[11px] border-[1.5px] px-1 text-center text-[13px] font-semibold"
                                                value={row.weight_value ?? ''}
                                                onChange={(e) => setWeight(row.sample_no, e.target.value)}
                                                disabled={isReadOnly}
                                            />
                                        </div>
                                    ))}
                                </div>
                                {!isReadOnly && (
                                    <button type="button" onClick={addSample} className="text-primary mt-3 text-[12.5px] font-bold">
                                        + Tambah Sample
                                    </button>
                                )}
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
        </IpcShell>
    );
}
