import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { ChipToggleGroup } from '@/components/ipc/chip-toggle-group';
import { StickySaveBar } from '@/components/ipc/sticky-save-bar';
import { Toast, useToast } from '@/components/ipc/toast';
import { TwoPane } from '@/components/ipc/two-pane';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { limitDecimals } from '@/lib/decimal';
import { cn } from '@/lib/utils';
import { type RecentBatch, type SharedData } from '@/types';
import { type FormDataConvertible } from '@inertiajs/core';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo, useState } from 'react';

interface Batch {
    id: number;
    no_batch: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string } | null;
}

interface StartupInspectionItemData {
    parameter_key: string;
    status: string;
    remark: string | null;
}

interface StartupInspectionSampleData {
    sample_no: number;
    volume_weight: string | null;
    weight_master_box: string | null;
}

interface StartupInspectionTestResultData {
    master_test_type_id: number;
    is_performed: boolean;
}

interface StartupInspectionData {
    id: number;
    completed_at: string | null;
    items: StartupInspectionItemData[];
    samples: StartupInspectionSampleData[];
    test_results: StartupInspectionTestResultData[];
}

interface TestType {
    id: number;
    name: string;
    category: string;
}

const PARAMETER_LABELS: Record<string, string> = {
    bulk_color_texture: 'Bulk Color / Texture',
    bulk_odor: 'Bulk Odor',
    appearance_after_filling: 'Appearance After Filling',
    leakage_test: 'Leakage Test',
    functional_test: 'Functional Test',
    primer: 'Primer',
    sekunder: 'Sekunder',
    tersier: 'Tersier',
    attribute: 'Attribute',
    appearance: 'Appearance',
};

const CATEGORY_TITLES: Record<string, string> = {
    Leakage: 'Leakage Test',
    Functional: 'Function Test',
    Attribute: 'Attribute',
};

const TEST_NAME_LABELS: Record<string, string> = {
    VACCUM: 'Vaccum',
    TORSI: 'Torsi',
    PRESS_TEST: 'Press Test',
    DROP_TEST_P: 'Drop Test (Primary)',
    DROP_TEST_S: 'Drop Test (Secondary)',
    SPRAY: 'Spray',
    FLIP_TOP: 'Flip Top',
    RUB_TEST: 'Rub Test',
    SWING_TEST: 'Swing Test',
    TAPE_TEST: 'Tape Test',
    HARDESS_TEST: 'Hardess Test',
    SECURITY_SEAL: 'Security Seal',
    SHADE_LABEL: 'Shade Label',
    QR_CODE: 'QR Code',
    HOLOGRAM: 'Hologram',
};

const SAMPLE_NUMBERS = Array.from({ length: 30 }, (_, i) => i + 1);

const inputClass =
    'h-9 rounded-lg border-[1.5px] border-border bg-background px-2 text-[12.5px] font-semibold text-foreground placeholder:text-muted-foreground/50';

interface ItemFormValue {
    status: string;
    remark: string;
    [key: string]: FormDataConvertible;
}

interface SampleFormValue {
    sample_no: number;
    volume_weight: string;
    weight_master_box: string;
    [key: string]: FormDataConvertible;
}

interface TestResultFormValue {
    is_performed: boolean;
    [key: string]: FormDataConvertible;
}

interface StartupInspectionForm {
    items: Record<string, ItemFormValue>;
    samples: SampleFormValue[];
    test_results: Record<number, TestResultFormValue>;
    [key: string]: FormDataConvertible;
}

export default function StartupInspectionEdit({
    batch,
    startupInspection,
    isReadOnly,
    parameterKeys,
    statusOptions,
    testTypes,
    masterBoxOnly,
}: {
    batch: Batch;
    startupInspection: StartupInspectionData | null;
    isReadOnly: boolean;
    parameterKeys: string[];
    statusOptions: string[];
    testTypes: TestType[];
    // Start Inspection is already completed, but Weight Master Box was left empty and the batch
    // is now at Packing: everything is read-only except that one card (see
    // StartupInspectionController::masterBoxLockReason()).
    masterBoxOnly: boolean;
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();
    const [errorFields, setErrorFields] = useState<Set<string>>(new Set());

    const itemsByKey = useMemo(() => {
        const map = new Map<string, StartupInspectionItemData>();
        (startupInspection?.items ?? []).forEach((item) => map.set(item.parameter_key, item));
        return map;
    }, [startupInspection]);

    const samplesByNo = useMemo(() => {
        const map = new Map<number, StartupInspectionSampleData>();
        (startupInspection?.samples ?? []).forEach((sample) => map.set(sample.sample_no, sample));
        return map;
    }, [startupInspection]);

    const testResultsByTypeId = useMemo(() => {
        const map = new Map<number, boolean>();
        (startupInspection?.test_results ?? []).forEach((result) => map.set(result.master_test_type_id, result.is_performed));
        return map;
    }, [startupInspection]);

    const initialItems = parameterKeys.reduce<Record<string, ItemFormValue>>((acc, key) => {
        const existing = itemsByKey.get(key);
        acc[key] = { status: existing?.status ?? '', remark: existing?.remark ?? '' };
        return acc;
    }, {});

    const initialSamples: SampleFormValue[] = SAMPLE_NUMBERS.map((n) => {
        const existing = samplesByNo.get(n);
        return {
            sample_no: n,
            volume_weight: existing?.volume_weight ?? '',
            weight_master_box: existing?.weight_master_box ?? '',
        };
    });

    const initialTestResults = testTypes.reduce<Record<number, TestResultFormValue>>((acc, type) => {
        acc[type.id] = { is_performed: testResultsByTypeId.get(type.id) ?? false };
        return acc;
    }, {});

    const { data, setData, put, transform, processing, errors } = useForm<StartupInspectionForm>({
        items: initialItems,
        samples: initialSamples,
        test_results: initialTestResults,
    });

    const answeredCount = parameterKeys.filter((key) => data.items[key]?.status).length;
    const volumeWeightFilledCount = data.samples.filter((sample) => String(sample.volume_weight ?? '').trim() !== '').length;
    const volumeWeightComplete = volumeWeightFilledCount === SAMPLE_NUMBERS.length;
    const masterBoxFilledCount = data.samples.filter((sample) => String(sample.weight_master_box ?? '').trim() !== '').length;
    const masterBoxEditable = masterBoxOnly || !isReadOnly;

    const setItemField = (key: string, field: 'status' | 'remark', value: string) => {
        setData('items', { ...data.items, [key]: { ...data.items[key], [field]: value } });
        if (field === 'status') {
            setErrorFields((prev) => {
                const n = new Set(prev);
                n.delete(key);
                return n;
            });
        }
    };

    const setSampleField = (sampleNo: number, field: 'volume_weight' | 'weight_master_box', rawValue: string) => {
        // Max 2 digits after the decimal point — extra digits are dropped as they're typed.
        const value = limitDecimals(rawValue);
        setData(
            'samples',
            data.samples.map((sample) => (sample.sample_no === sampleNo ? { ...sample, [field]: value } : sample)),
        );
        if (field === 'volume_weight') {
            setErrorFields((prev) => {
                const n = new Set(prev);
                n.delete(`volume_weight.${sampleNo}`);
                return n;
            });
        }
    };

    const toggleTestResult = (typeId: number) => {
        if (isReadOnly) return;
        setData('test_results', {
            ...data.test_results,
            [typeId]: { is_performed: !data.test_results[typeId]?.is_performed },
        });
    };

    const testTypesByCategory = useMemo(() => {
        const groups = new Map<string, TestType[]>();
        testTypes.forEach((type) => {
            const list = groups.get(type.category) ?? [];
            list.push(type);
            groups.set(type.category, list);
        });
        return groups;
    }, [testTypes]);

    const submitMasterBox: FormEventHandler = (e) => {
        e.preventDefault();
        if (masterBoxFilledCount === 0) {
            toast('Isi minimal satu sample Weight Master Box');
            return;
        }
        if (!confirm(`Simpan ${masterBoxFilledCount} sample Weight Master Box? Setelah disimpan tidak bisa diubah lagi.`)) return;
        transform((current) => ({ samples: current.samples.map(({ sample_no, weight_master_box }) => ({ sample_no, weight_master_box })) }));
        put(`/batches/${batch.id}/startup-inspection/master-box`);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty = new Set<string>();
        parameterKeys.forEach((key) => {
            if (!data.items[key]?.status) empty.add(key);
        });
        const emptyChecklistCount = empty.size;
        data.samples.forEach((sample) => {
            if (String(sample.volume_weight ?? '').trim() === '') empty.add(`volume_weight.${sample.sample_no}`);
        });
        if (empty.size) {
            setErrorFields(empty);
            const emptySampleCount = empty.size - emptyChecklistCount;
            const parts = [
                emptyChecklistCount ? `${emptyChecklistCount} item checklist` : null,
                emptySampleCount ? `${emptySampleCount} sample Volume / Weight` : null,
            ].filter(Boolean);
            toast(`${parts.join(' dan ')} belum diisi`);
            return;
        }
        setErrorFields(new Set());
        put(`/batches/${batch.id}/startup-inspection`);
    };

    const listPane = <BatchNavList batches={recentBatches} activeId={batch.id} />;

    return (
        <IpcShell
            title="Start Inspection"
            subtitle={`${batch.no_batch} · ${batch.master_product.product_name}`}
            backHref={masterBoxOnly ? `/batches/${batch.id}/packing-check` : `/batches/${batch.id}/startup-check`}
            headerActions={
                masterBoxOnly ? (
                    <Link
                        href={`/batches/${batch.id}/packing-check`}
                        className="bg-primary/[0.08] text-primary flex h-9 items-center rounded-full px-3.5 text-[12.5px] font-bold whitespace-nowrap"
                    >
                        Kembali ke Packing Check
                    </Link>
                ) : isReadOnly ? (
                    <Link
                        href={`/batches/${batch.id}/startup-check`}
                        className="flex h-9 items-center gap-1.5 rounded-full bg-green-100 px-3.5 text-[12.5px] font-bold whitespace-nowrap text-green-800"
                    >
                        Selesai · Kembali ke Startup Check
                    </Link>
                ) : (
                    <span className="bg-primary/[0.08] text-primary rounded-full px-3 py-1.5 text-[12.5px] font-bold whitespace-nowrap">
                        {answeredCount}/{parameterKeys.length}
                    </span>
                )
            }
        >
            <Head title={`Start Inspection — ${batch.no_batch}`} />
            <Toast message={message} />
            <TwoPane list={listPane}>
                <form onSubmit={masterBoxOnly ? submitMasterBox : submit} className="flex flex-1 flex-col">
                    <div className="flex flex-1 flex-col gap-3.5 px-5 pt-1 pb-2 md:px-8">
                        {masterBoxOnly && (
                            <div className="border-primary/30 bg-primary/[0.06] text-foreground rounded-[20px] border px-[18px] py-3.5 text-[13px] font-medium">
                                Start Inspection sudah selesai. Saat ini hanya <b>Weight Master Box</b> yang bisa diisi, dan hanya sekali — setelah
                                disimpan tidak bisa diubah lagi.
                            </div>
                        )}

                        <AccordionCard
                            title="Checklist Inspeksi"
                            progress={`${answeredCount}/${parameterKeys.length} terisi`}
                            complete={answeredCount === parameterKeys.length}
                        >
                            {parameterKeys.map((key) => (
                                <div key={key} className="flex flex-col gap-2">
                                    <Label className="text-foreground text-[13px] font-semibold">{PARAMETER_LABELS[key] ?? key}</Label>
                                    <div className={errorFields.has(key) ? 'outline-destructive rounded-xl outline outline-2' : ''}>
                                        <ChipToggleGroup
                                            name={PARAMETER_LABELS[key] ?? key}
                                            options={statusOptions}
                                            value={data.items[key]?.status ?? ''}
                                            onChange={(value) => setItemField(key, 'status', value)}
                                            disabled={isReadOnly}
                                        />
                                    </div>
                                    <Input
                                        placeholder="Remark"
                                        className={inputClass}
                                        value={data.items[key]?.remark ?? ''}
                                        onChange={(e) => setItemField(key, 'remark', e.target.value)}
                                        disabled={isReadOnly}
                                    />
                                    <InputError message={(errors as Record<string, string>)[`items.${key}.status`]} />
                                </div>
                            ))}
                        </AccordionCard>

                        <AccordionCard
                            title="Volume / Weight"
                            progress={`${volumeWeightFilledCount}/${SAMPLE_NUMBERS.length} terisi`}
                            complete={volumeWeightComplete}
                            forceOpen={[...errorFields].some((field) => field.startsWith('volume_weight.'))}
                        >
                            <div className="col-span-full grid grid-cols-3 gap-2 sm:grid-cols-5 md:grid-cols-6">
                                {SAMPLE_NUMBERS.map((n) => {
                                    const sample = data.samples.find((s) => s.sample_no === n)!;
                                    return (
                                        <div key={n} className="flex items-center gap-1.5">
                                            <span className="text-muted-foreground w-5 shrink-0 text-[11px] font-semibold">{n}</span>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                className={cn(
                                                    inputClass,
                                                    errorFields.has(`volume_weight.${n}`) && 'border-destructive ring-destructive ring-1',
                                                )}
                                                value={sample.volume_weight}
                                                onChange={(e) => setSampleField(n, 'volume_weight', e.target.value)}
                                                disabled={isReadOnly}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                            {!masterBoxOnly && <InputError message={(errors as Record<string, string>).samples} className="col-span-full" />}
                        </AccordionCard>

                        <AccordionCard
                            title="Weight Master Box"
                            progress={
                                masterBoxOnly
                                    ? `${masterBoxFilledCount}/${SAMPLE_NUMBERS.length} terisi — sekali isi`
                                    : masterBoxFilledCount > 0
                                      ? `${masterBoxFilledCount}/${SAMPLE_NUMBERS.length} terisi`
                                      : 'Opsional — bisa diisi lagi saat Packing'
                            }
                            defaultOpen={masterBoxOnly}
                        >
                            <div className="col-span-full grid grid-cols-3 gap-2 sm:grid-cols-5 md:grid-cols-6">
                                {SAMPLE_NUMBERS.map((n) => {
                                    const sample = data.samples.find((s) => s.sample_no === n)!;
                                    return (
                                        <div key={n} className="flex items-center gap-1.5">
                                            <span className="text-muted-foreground w-5 shrink-0 text-[11px] font-semibold">{n}</span>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                className={inputClass}
                                                value={sample.weight_master_box}
                                                onChange={(e) => setSampleField(n, 'weight_master_box', e.target.value)}
                                                disabled={!masterBoxEditable}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                            {masterBoxOnly && <InputError message={(errors as Record<string, string>).samples} className="col-span-full" />}
                        </AccordionCard>

                        {['Leakage', 'Functional', 'Attribute'].map((category) => {
                            const types = testTypesByCategory.get(category) ?? [];
                            if (types.length === 0) return null;
                            return (
                                <AccordionCard key={category} title={CATEGORY_TITLES[category] ?? category} defaultOpen={false}>
                                    <div className="col-span-full grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {types.map((type) => {
                                            const active = data.test_results[type.id]?.is_performed ?? false;
                                            return (
                                                <button
                                                    key={type.id}
                                                    type="button"
                                                    disabled={isReadOnly}
                                                    onClick={() => toggleTestResult(type.id)}
                                                    className={cn(
                                                        'min-h-11 rounded-xl border-[1.5px] px-2.5 py-2 text-center text-[13px] font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                                                        active
                                                            ? 'border-primary bg-primary/[0.08] text-primary'
                                                            : 'border-border bg-background text-muted-foreground',
                                                    )}
                                                >
                                                    {TEST_NAME_LABELS[type.name] ?? type.name}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </AccordionCard>
                            );
                        })}
                    </div>

                    {masterBoxOnly && (
                        <StickySaveBar
                            label="Simpan Weight Master Box"
                            processing={processing}
                            note={`${masterBoxFilledCount} dari ${SAMPLE_NUMBERS.length} sample terisi`}
                        />
                    )}

                    {!isReadOnly && (
                        <StickySaveBar
                            label="Simpan"
                            processing={processing}
                            note={`${answeredCount} dari ${parameterKeys.length} item checklist terisi`}
                        />
                    )}
                </form>
            </TwoPane>
        </IpcShell>
    );
}
