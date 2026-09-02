import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

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

const DEFAULT_SAMPLE_COUNT = 30;

export default function StartupCheckEdit({
    batch,
    startupCheck,
    isReadOnly,
    availabilityFields,
    conformFields,
    statusOptions,
}: {
    batch: Batch;
    startupCheck: StartupCheckData | null;
    isReadOnly: boolean;
    availabilityFields: Record<string, string>;
    conformFields: Record<string, string>;
    statusOptions: { availability: string[]; conform: string[] };
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Batches', href: '/batches' },
        { title: batch.no_batch, href: `/batches/${batch.id}/startup-check` },
        { title: 'Startup Check', href: `/batches/${batch.id}/startup-check` },
    ];

    const initialWeights = (): BottleWeight[] => {
        const bySample = new Map((startupCheck?.bottle_weights ?? []).map((row) => [row.sample_no, row.weight_value]));
        const maxSample = Math.max(DEFAULT_SAMPLE_COUNT, ...(startupCheck?.bottle_weights.map((r) => r.sample_no) ?? [0]));
        return Array.from({ length: maxSample }, (_, i) => ({
            sample_no: i + 1,
            weight_value: bySample.get(i + 1) ?? null,
        }));
    };

    const [weights, setWeights] = useState<BottleWeight[]>(initialWeights);

    const { data, setData, put, transform, processing, errors } = useForm<Record<string, string | null>>({
        product_standard_status: (startupCheck?.product_standard_status as string) ?? '',
        sample_challenge_test_status: (startupCheck?.sample_challenge_test_status as string) ?? '',
        wi_im_match_status: (startupCheck?.wi_im_match_status as string) ?? '',
        pm_bom_match_status: (startupCheck?.pm_bom_match_status as string) ?? '',
        bulk_status_status: (startupCheck?.bulk_status_status as string) ?? '',
        validation_report_status: (startupCheck?.validation_report_status as string) ?? '',
        identity_line_board_status: (startupCheck?.identity_line_board_status as string) ?? '',
        machine_vision_status: (startupCheck?.machine_vision_status as string) ?? '',
        machine_weigher_status: (startupCheck?.machine_weigher_status as string) ?? '',
        machine_roller_status: (startupCheck?.machine_roller_status as string) ?? '',
        machine_load_cell_status: (startupCheck?.machine_load_cell_status as string) ?? '',
        machine_balance_status: (startupCheck?.machine_balance_status as string) ?? '',
        filling_range_min: (startupCheck?.filling_range_min as string) ?? '',
        filling_range_max: (startupCheck?.filling_range_max as string) ?? '',
        density: (startupCheck?.density as string) ?? '',
        heating: (startupCheck?.heating as string) ?? '',
        line_leader_name: (startupCheck?.line_leader_name as string) ?? '',
        operator_name: (startupCheck?.operator_name as string) ?? '',
        im_number: (startupCheck?.im_number as string) ?? '',
        color: (startupCheck?.color as string) ?? '',
        coding: (startupCheck?.coding as string) ?? '',
        temperature_setting: (startupCheck?.temperature_setting as string) ?? '',
        remarks: (startupCheck?.remarks as string) ?? '',
    });

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

    const renderStatusField = (key: string, label: string, options: string[]) => (
        <div key={key} className="grid gap-2">
            <Label htmlFor={key}>{label}</Label>
            <Select value={data[key] ?? ''} onValueChange={(value) => setData(key, value)} disabled={isReadOnly}>
                <SelectTrigger id={key}>
                    <SelectValue placeholder="Pilih status" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option} value={option}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Startup Check — ${batch.no_batch}`} />
            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Startup Check</h1>
                        <p className="text-muted-foreground text-sm">
                            {batch.no_batch} — {batch.master_product.product_name} ({batch.master_line.name})
                        </p>
                    </div>
                    {isReadOnly && (
                        <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-800 dark:bg-green-950 dark:text-green-300">
                            Selesai — read only
                        </span>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Ketersediaan</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {Object.entries(availabilityFields).map(([key, label]) => renderStatusField(key, label, statusOptions.availability))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Kondisi Mesin</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {Object.entries(conformFields).map(([key, label]) => renderStatusField(key, label, statusOptions.conform))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Parameter Filling</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="grid gap-2">
                            <Label htmlFor="filling_range_min">Filling Range Min</Label>
                            <Input
                                id="filling_range_min"
                                type="number"
                                step="0.01"
                                value={data.filling_range_min ?? ''}
                                onChange={(e) => setData('filling_range_min', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.filling_range_min} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="filling_range_max">Filling Range Max</Label>
                            <Input
                                id="filling_range_max"
                                type="number"
                                step="0.01"
                                value={data.filling_range_max ?? ''}
                                onChange={(e) => setData('filling_range_max', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.filling_range_max} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="density">Density</Label>
                            <Input
                                id="density"
                                type="number"
                                step="0.0001"
                                value={data.density ?? ''}
                                onChange={(e) => setData('density', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.density} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="heating">Heating</Label>
                            <Input
                                id="heating"
                                value={data.heating ?? ''}
                                onChange={(e) => setData('heating', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.heating} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="line_leader_name">Line Leader</Label>
                            <Input
                                id="line_leader_name"
                                value={data.line_leader_name ?? ''}
                                onChange={(e) => setData('line_leader_name', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.line_leader_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="operator_name">Operator</Label>
                            <Input
                                id="operator_name"
                                value={data.operator_name ?? ''}
                                onChange={(e) => setData('operator_name', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.operator_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="im_number">IM Number</Label>
                            <Input
                                id="im_number"
                                value={data.im_number ?? ''}
                                onChange={(e) => setData('im_number', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.im_number} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="color">Color</Label>
                            <Input id="color" value={data.color ?? ''} onChange={(e) => setData('color', e.target.value)} disabled={isReadOnly} />
                            <InputError message={errors.color} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="coding">Coding</Label>
                            <Input id="coding" value={data.coding ?? ''} onChange={(e) => setData('coding', e.target.value)} disabled={isReadOnly} />
                            <InputError message={errors.coding} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="temperature_setting">Temperature Setting</Label>
                            <Input
                                id="temperature_setting"
                                value={data.temperature_setting ?? ''}
                                onChange={(e) => setData('temperature_setting', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.temperature_setting} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2 lg:col-span-4">
                            <Label htmlFor="remarks">Remarks</Label>
                            <Textarea
                                id="remarks"
                                value={data.remarks ?? ''}
                                onChange={(e) => setData('remarks', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.remarks} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Bottle Weight Samples</CardTitle>
                        {!isReadOnly && (
                            <Button type="button" size="sm" variant="outline" onClick={addSample}>
                                Tambah Sample
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        <InputError message={(errors as Record<string, string>).bottle_weights} className="mb-2" />
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6">
                            {weights.map((row) => (
                                <div key={row.sample_no} className="grid gap-1">
                                    <Label htmlFor={`weight-${row.sample_no}`} className="text-muted-foreground text-xs">
                                        #{row.sample_no}
                                    </Label>
                                    <Input
                                        id={`weight-${row.sample_no}`}
                                        type="number"
                                        step="0.0001"
                                        value={row.weight_value ?? ''}
                                        onChange={(e) => setWeight(row.sample_no, e.target.value)}
                                        disabled={isReadOnly}
                                    />
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {!isReadOnly && (
                    <div>
                        <Button type="submit" disabled={processing}>
                            Simpan & Selesaikan Startup Check
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
