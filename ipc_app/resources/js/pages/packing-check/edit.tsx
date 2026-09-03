import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { ChipToggleGroup } from '@/components/ipc/chip-toggle-group';
import { StickySaveBar } from '@/components/ipc/sticky-save-bar';
import { Toast, useToast } from '@/components/ipc/toast';
import { TwoPane } from '@/components/ipc/two-pane';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

interface Batch {
    id: number;
    no_batch: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string };
}

interface PackingCheckData {
    id: number;
    completed_at: string | null;
    [key: string]: unknown;
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
}: {
    batch: Batch;
    packingCheck: PackingCheckData | null;
    isReadOnly: boolean;
    checklistGroups: ChecklistGroup[];
    decisions: string[];
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();
    const [errorFields, setErrorFields] = useState<Set<string>>(new Set());

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
        allChecklistKeys.forEach((key) => { if (!data[key]) empty.add(key); });
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
                                            <div className={errorFields.has(key) ? 'rounded-xl outline outline-2 outline-destructive' : ''}>
                                                <ChipToggleGroup
                                                    name={label}
                                                    options={group.options}
                                                    value={data[key] ?? ''}
                                                    onChange={(value) => { setData(key, value); setErrorFields((prev) => { const n = new Set(prev); n.delete(key); return n; }); }}
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
                            <div className="col-span-full flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Decision</Label>
                                <div className={errorFields.has('decision') ? 'rounded-xl outline outline-2 outline-destructive' : ''}>
                                    <ChipToggleGroup
                                        name="decision"
                                        options={decisions}
                                        value={data.decision ?? ''}
                                        onChange={(value) => { setData('decision', value); setErrorFields((prev) => { const n = new Set(prev); n.delete('decision'); return n; }); }}
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
                                    onChange={(e) => { setData('remarks', e.target.value); setErrorFields((prev) => { const n = new Set(prev); n.delete('remarks'); return n; }); }}
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
        </IpcShell>
    );
}
