import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { ChipToggleGroup } from '@/components/ipc/chip-toggle-group';
import { Toast, useToast } from '@/components/ipc/toast';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { IpcShell } from '@/layouts/ipc-shell';
import { type FormDataConvertible } from '@inertiajs/core';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

interface ChecklistGroup {
    key: string;
    fields: Record<string, string>;
    options: string[];
}

interface SampleGroup {
    key: string;
    label: string;
    parameters: Record<string, string>;
}

interface Props {
    batch: { id: number; no_batch: string; master_product: { product_name: string } };
    correction: { id: number; revision_no: number; request_note: string | null; requested_at: string | null; requester?: { name: string } | null };
    stage: 'filling' | 'packing' | 'finished';
    stageLabel: string;
    values: Record<string, string | number | null>;
    samples: Record<string, string | number | null>[];
    checklistGroups: ChecklistGroup[];
    sampleGroups: SampleGroup[];
    decisions: string[];
    dispositions: string[];
}

const GROUP_TITLES: Record<string, string> = { primary: 'Primary', secondary: 'Secondary', tersier: 'Tersier' };
const AQL_COLUMNS = ['ac', 'cd', 'md', 'mnd'] as const;
const text = (value: string | number | null | undefined) => (value === null || value === undefined ? '' : String(value));

/**
 * QC fixes the TH Progress round an approver flagged. The saved round is corrected in place and
 * the before/after is kept in the audit (see CorrectCheckRound) — so a reason is mandatory.
 */
export default function RoundCorrectionEdit({
    batch,
    correction,
    stage,
    stageLabel,
    values,
    samples,
    checklistGroups,
    sampleGroups,
    decisions,
    dispositions,
}: Props) {
    const { message, toast } = useToast();

    const sampleSlots = Math.max(10, ...samples.map((row) => Number(row.sample_no ?? 0)));
    const initialFillingSamples = Array.from({ length: sampleSlots }, (_, i) => {
        const found = samples.find((row) => Number(row.sample_no) === i + 1);
        return { sample_no: i + 1, weight_value: text(found?.weight_value) };
    });
    const initialAqlSamples = Object.fromEntries(
        sampleGroups.flatMap((group) =>
            Object.keys(group.parameters).map((key) => {
                const found = samples.find((row) => row.parameter_key === key);
                return [key, { ac: text(found?.ac), cd: text(found?.cd), md: text(found?.md), mnd: text(found?.mnd), remark: text(found?.remark) }];
            }),
        ),
    );

    const { data, setData, put, processing, errors } = useForm<Record<string, FormDataConvertible>>({
        correction_reason: '',
        sample_bulk_odor_status: text(values.sample_bulk_odor_status),
        sample_leakage_test_status: text(values.sample_leakage_test_status),
        remarks: text(values.remarks),
        decision: text(values.decision),
        samples: stage === 'filling' ? initialFillingSamples : initialAqlSamples,
        sum_weight_mb: text(values.sum_weight_mb),
        ...Object.fromEntries(checklistGroups.flatMap((group) => Object.keys(group.fields).map((key) => [key, text(values[key])]))),
        quantity_wi: text(values.quantity_wi),
        masterbox: text(values.masterbox),
        no_pallet_qty: text(values.no_pallet_qty),
        disposition: text(values.disposition),
    });

    const err = errors as Record<string, string>;
    const str = (key: string) => String(data[key] ?? '');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!str('correction_reason').trim()) {
            toast('Tuliskan alasan koreksi dulu.');
            document.getElementById('correction_reason')?.focus();
            return;
        }
        put(`/batches/${batch.id}/corrections/${correction.id}`, {
            preserveScroll: true,
            onError: (serverErrors) => toast(Object.values(serverErrors)[0] ?? 'Koreksi belum bisa disimpan.'),
        });
    };

    const field = (id: string, label: string, props?: { inputMode?: 'numeric' | 'text'; hint?: string }) => (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} inputMode={props?.inputMode} value={str(id)} onChange={(e) => setData(id, e.target.value)} disabled={processing} />
            {props?.hint && <p className="text-muted-foreground text-xs">{props.hint}</p>}
            <InputError message={err[id]} />
        </div>
    );

    const decisionBlock = (label: string, key: 'decision' | 'disposition', options: string[]) => (
        <div className="flex flex-col gap-1.5">
            <Label>{label}</Label>
            <ChipToggleGroup name={label} options={options} value={str(key)} disabled={processing} onChange={(value) => setData(key, value)} />
            <InputError message={err[key]} />
        </div>
    );

    const remarksBlock = (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor="remarks">Remarks</Label>
            <Textarea id="remarks" rows={3} value={str('remarks')} onChange={(e) => setData('remarks', e.target.value)} disabled={processing} />
            <InputError message={err.remarks} />
        </div>
    );

    const fillingSamples = (data.samples as { sample_no: number; weight_value: string }[]) ?? [];
    const aqlSamples = data.samples as Record<string, Record<(typeof AQL_COLUMNS)[number] | 'remark', string>>;

    return (
        <IpcShell
            title={`Koreksi ${stageLabel} TH #${correction.revision_no}`}
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={`/batches/${batch.id}`}
        >
            <Head title={`Koreksi ${stageLabel} TH #${correction.revision_no} — ${batch.no_batch}`} />
            <Toast message={message} />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-4xl flex-col gap-4 px-5 py-5 md:px-8">
                <div className="rounded-2xl border border-orange-300 bg-orange-50 p-4 text-sm text-orange-900">
                    <p className="font-bold">
                        Approver {correction.requester?.name ?? ''} meminta {stageLabel} TH Progress #{correction.revision_no} diperbaiki.
                    </p>
                    {correction.request_note && <p className="mt-1 font-medium">Catatan: {correction.request_note}</p>}
                    <p className="mt-1 text-xs">
                        Data TH #{correction.revision_no} diperbaiki langsung di posisinya. Nilai sebelum dan sesudah, siapa yang mengoreksi, dan
                        alasannya tercatat permanen di audit. Foto tidak diubah di sini.
                    </p>
                </div>

                {stage === 'filling' && (
                    <>
                        <AccordionCard title="Sample Check">
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <Label>Sample Bulk &amp; Odor (5 Sample)</Label>
                                    <ChipToggleGroup
                                        name="Bulk & Odor"
                                        options={['Conform', 'Not Conform']}
                                        value={str('sample_bulk_odor_status')}
                                        disabled={processing}
                                        onChange={(v) => setData('sample_bulk_odor_status', v)}
                                    />
                                    <InputError message={err.sample_bulk_odor_status} />
                                </div>
                                <div className="flex flex-col gap-1.5">
                                    <Label>Sample Leakage Test</Label>
                                    <ChipToggleGroup
                                        name="Leakage"
                                        options={['Conform', 'Not Conform', 'N/A']}
                                        value={str('sample_leakage_test_status')}
                                        disabled={processing}
                                        onChange={(v) => setData('sample_leakage_test_status', v)}
                                    />
                                    <InputError message={err.sample_leakage_test_status} />
                                </div>
                            </div>
                        </AccordionCard>
                        <AccordionCard title="Berat Sampel">
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                {fillingSamples.map((row, i) => (
                                    <div key={row.sample_no} className="flex flex-col gap-1">
                                        <Label htmlFor={`sample-${row.sample_no}`}>#{row.sample_no}</Label>
                                        <Input
                                            id={`sample-${row.sample_no}`}
                                            inputMode="decimal"
                                            value={row.weight_value}
                                            disabled={processing}
                                            onChange={(e) =>
                                                setData(
                                                    'samples',
                                                    fillingSamples.map((item, idx) => (idx === i ? { ...item, weight_value: e.target.value } : item)),
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                            <InputError message={err.samples} />
                        </AccordionCard>
                        <AccordionCard title="Keputusan">
                            <div className="flex flex-col gap-4">
                                {decisionBlock('Decision', 'decision', decisions)}
                                {remarksBlock}
                            </div>
                        </AccordionCard>
                    </>
                )}

                {stage === 'packing' && (
                    <>
                        {checklistGroups.map((group) => (
                            <AccordionCard key={group.key} title={GROUP_TITLES[group.key] ?? group.key}>
                                <div className="flex flex-col gap-4">
                                    {Object.entries(group.fields).map(([key, label]) => (
                                        <div key={key} className="flex flex-col gap-1.5">
                                            <Label>{label}</Label>
                                            <ChipToggleGroup
                                                name={label}
                                                options={group.options}
                                                value={str(key)}
                                                disabled={processing}
                                                onChange={(v) => setData(key, v)}
                                            />
                                            <InputError message={err[key]} />
                                        </div>
                                    ))}
                                </div>
                            </AccordionCard>
                        ))}
                        <AccordionCard title="Weight of MB &amp; Keputusan">
                            <div className="flex flex-col gap-4">
                                {field('sum_weight_mb', 'Weight of MB (gram, bilangan bulat)', { inputMode: 'numeric' })}
                                {decisionBlock('Decision', 'decision', decisions)}
                                {remarksBlock}
                            </div>
                        </AccordionCard>
                    </>
                )}

                {stage === 'finished' && (
                    <>
                        <AccordionCard title="Kuantitas">
                            <div className="grid gap-4 md:grid-cols-3">
                                {field('quantity_wi', 'Quantity WI (pcs)', {
                                    inputMode: 'numeric',
                                    hint: 'Sampling AQL ikut dihitung ulang dari Quantity WI.',
                                })}
                                {field('masterbox', 'Masterbox', { hint: 'Contoh: 4,17,55,78' })}
                                {field('no_pallet_qty', 'No. Pallet & Qty', { hint: 'Contoh: 1/2016' })}
                            </div>
                        </AccordionCard>
                        {sampleGroups.map((group) => (
                            <AccordionCard key={group.key} title={`Quantity Sample ${group.label}`} defaultOpen={false}>
                                <div className="flex flex-col gap-3">
                                    {Object.entries(group.parameters).map(([key, label]) => (
                                        <div key={key} className="border-border-soft rounded-xl border p-3">
                                            <p className="mb-2 text-[13px] font-bold">{label}</p>
                                            <div className="grid grid-cols-4 gap-2">
                                                {AQL_COLUMNS.map((col) => (
                                                    <div key={col} className="flex flex-col gap-1">
                                                        <Label className="text-xs uppercase">{col}</Label>
                                                        <Input
                                                            inputMode="numeric"
                                                            value={aqlSamples[key]?.[col] ?? ''}
                                                            disabled={processing}
                                                            onChange={(e) =>
                                                                setData('samples', {
                                                                    ...aqlSamples,
                                                                    [key]: { ...aqlSamples[key], [col]: e.target.value },
                                                                })
                                                            }
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                            <InputError
                                                message={
                                                    err[`samples.${key}.ac`] ??
                                                    err[`samples.${key}.cd`] ??
                                                    err[`samples.${key}.md`] ??
                                                    err[`samples.${key}.mnd`]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </AccordionCard>
                        ))}
                        <AccordionCard title="Keputusan">
                            <div className="flex flex-col gap-4">
                                {decisionBlock('Disposition', 'disposition', dispositions)}
                                {remarksBlock}
                            </div>
                        </AccordionCard>
                    </>
                )}

                <div className="border-primary/30 bg-card flex flex-col gap-2 rounded-2xl border p-4">
                    <Label htmlFor="correction_reason">Alasan koreksi (wajib, masuk audit)</Label>
                    <Textarea
                        id="correction_reason"
                        rows={3}
                        placeholder="Contoh: berat sampel 3 salah ketik, seharusnya 7321."
                        value={str('correction_reason')}
                        onChange={(e) => setData('correction_reason', e.target.value)}
                        disabled={processing}
                    />
                    <InputError message={err.correction_reason} />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="bg-primary flex h-12 items-center justify-center rounded-xl px-6 text-[14px] font-bold text-white disabled:opacity-60 sm:self-end"
                >
                    {processing && <LoaderCircle className="mr-2 size-4 animate-spin" />}
                    {processing ? 'Menyimpan...' : `Simpan Koreksi TH #${correction.revision_no}`}
                </button>
            </form>
        </IpcShell>
    );
}
