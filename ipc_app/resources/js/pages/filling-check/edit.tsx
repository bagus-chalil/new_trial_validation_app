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
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Batch {
    id: number;
    no_batch: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string };
}

interface FillingCheckSample {
    sample_no: number;
    weight_value: string | null;
    weight_result?: string | null;
}

interface FillingCheckData {
    id: number;
    completed_at: string | null;
    samples: FillingCheckSample[];
    sample_bulk_odor_status: string | null;
    sample_leakage_test_status: string | null;
    standard_weight_and_volume: string | null;
    line_leader_name: string | null;
    remarks: string | null;
    decision: string | null;
    average_weight: string | null;
}

const SAMPLE_COUNT = 10;
const CONFORM_OPTIONS = ['Conform', 'Not Conform'];

const inputClass =
    'h-[46px] rounded-xl border-[1.5px] border-border bg-background px-3.5 text-[14.5px] font-semibold text-foreground placeholder:text-muted-foreground/50';

export default function FillingCheckEdit({
    batch,
    fillingCheck,
    isReadOnly,
    decisions,
}: {
    batch: Batch;
    fillingCheck: FillingCheckData | null;
    isReadOnly: boolean;
    decisions: string[];
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const resultBySample = new Map((fillingCheck?.samples ?? []).map((row) => [row.sample_no, row.weight_result ?? null]));

    const initialSamples = (): FillingCheckSample[] => {
        const bySample = new Map((fillingCheck?.samples ?? []).map((row) => [row.sample_no, row.weight_value]));
        return Array.from({ length: SAMPLE_COUNT }, (_, i) => ({
            sample_no: i + 1,
            weight_value: bySample.get(i + 1) ?? null,
        }));
    };

    const { data, setData, put, processing, errors } = useForm<{
        sample_bulk_odor_status: string;
        sample_leakage_test_status: string;
        standard_weight_and_volume: string;
        line_leader_name: string;
        remarks: string;
        decision: string;
        samples: FillingCheckSample[];
    }>({
        sample_bulk_odor_status: fillingCheck?.sample_bulk_odor_status ?? '',
        sample_leakage_test_status: fillingCheck?.sample_leakage_test_status ?? '',
        standard_weight_and_volume: fillingCheck?.standard_weight_and_volume ?? '',
        line_leader_name: fillingCheck?.line_leader_name ?? '',
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

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/batches/${batch.id}/filling-check`);
    };

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
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <form onSubmit={submit} className="flex flex-1 flex-col">
                    <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-2 md:px-8">
                        <AccordionCard title="Sample Check">
                            <div className="flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Sample Bulk & Odor (5 Sample)</Label>
                                <ChipToggleGroup
                                    name="sample_bulk_odor_status"
                                    options={CONFORM_OPTIONS}
                                    value={data.sample_bulk_odor_status}
                                    onChange={(value) => setData('sample_bulk_odor_status', value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.sample_bulk_odor_status} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Sample Leakage Test (5 Sample)</Label>
                                <ChipToggleGroup
                                    name="sample_leakage_test_status"
                                    options={CONFORM_OPTIONS}
                                    value={data.sample_leakage_test_status}
                                    onChange={(value) => setData('sample_leakage_test_status', value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.sample_leakage_test_status} />
                            </div>
                        </AccordionCard>

                        <AccordionCard title="Parameter Filling">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="standard_weight_and_volume" className="text-muted-foreground text-xs font-semibold">
                                    Standard Weight & Volume
                                </Label>
                                <Input
                                    id="standard_weight_and_volume"
                                    className={inputClass}
                                    value={data.standard_weight_and_volume}
                                    onChange={(e) => setData('standard_weight_and_volume', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.standard_weight_and_volume} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="line_leader_name" className="text-muted-foreground text-xs font-semibold">
                                    Line Leader
                                </Label>
                                <Input
                                    id="line_leader_name"
                                    className={inputClass}
                                    value={data.line_leader_name}
                                    onChange={(e) => setData('line_leader_name', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.line_leader_name} />
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label className="text-foreground text-[13px] font-semibold">Decision</Label>
                                <ChipToggleGroup
                                    name="decision"
                                    options={decisions}
                                    value={data.decision}
                                    onChange={(value) => setData('decision', value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.decision} />
                            </div>
                            <div className="col-span-full flex flex-col gap-2">
                                <Label htmlFor="remarks" className="text-muted-foreground text-xs font-semibold">
                                    Remarks
                                </Label>
                                <Textarea
                                    id="remarks"
                                    className="border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px]"
                                    value={data.remarks}
                                    onChange={(e) => setData('remarks', e.target.value)}
                                    disabled={isReadOnly}
                                />
                                <InputError message={errors.remarks} />
                            </div>
                        </AccordionCard>

                        <AccordionCard title="Weight Samples" progress="10 sample">
                            <div className="col-span-full">
                                <InputError message={(errors as Record<string, string>).samples} className="mb-2" />
                                <div className="grid grid-cols-4 gap-2 sm:grid-cols-5">
                                    {data.samples.map((row) => (
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
                                            {resultBySample.get(row.sample_no) != null && (
                                                <span className="text-muted-foreground text-center text-[10px]">
                                                    {resultBySample.get(row.sample_no)}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <p className="text-muted-foreground mt-3 text-xs">
                                    Weight result dan average weight dihitung otomatis di server saat disimpan.
                                    {fillingCheck?.average_weight != null && <> Average Weight tersimpan: {fillingCheck.average_weight}.</>}
                                </p>
                            </div>
                        </AccordionCard>
                    </div>

                    {!isReadOnly && <StickySaveBar label="Simpan & Selesaikan" processing={processing} />}
                </form>
            </TwoPane>
        </IpcShell>
    );
}
