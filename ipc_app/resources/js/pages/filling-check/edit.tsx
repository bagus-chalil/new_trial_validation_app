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
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Batches', href: '/batches' },
        { title: batch.no_batch, href: `/batches/${batch.id}/filling-check` },
        { title: 'Filling Check', href: `/batches/${batch.id}/filling-check` },
    ];

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

    const renderStatusField = (key: 'sample_bulk_odor_status' | 'sample_leakage_test_status', label: string) => (
        <div className="grid gap-2">
            <Label htmlFor={key}>{label}</Label>
            <Select value={data[key]} onValueChange={(value) => setData(key, value)} disabled={isReadOnly}>
                <SelectTrigger id={key}>
                    <SelectValue placeholder="Pilih status" />
                </SelectTrigger>
                <SelectContent>
                    {CONFORM_OPTIONS.map((option) => (
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
            <Head title={`Filling Check — ${batch.no_batch}`} />
            <form onSubmit={submit} className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Filling Check</h1>
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
                        <CardTitle>Sample Check</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {renderStatusField('sample_bulk_odor_status', 'Sample Bulk & Odor (5 Sample)')}
                        {renderStatusField('sample_leakage_test_status', 'Sample Leakage Test (5 Sample)')}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Parameter Filling</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="standard_weight_and_volume">Standard Weight & Volume</Label>
                            <Input
                                id="standard_weight_and_volume"
                                value={data.standard_weight_and_volume}
                                onChange={(e) => setData('standard_weight_and_volume', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.standard_weight_and_volume} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="line_leader_name">Line Leader</Label>
                            <Input
                                id="line_leader_name"
                                value={data.line_leader_name}
                                onChange={(e) => setData('line_leader_name', e.target.value)}
                                disabled={isReadOnly}
                            />
                            <InputError message={errors.line_leader_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="decision">Decision</Label>
                            <Select value={data.decision} onValueChange={(value) => setData('decision', value)} disabled={isReadOnly}>
                                <SelectTrigger id="decision">
                                    <SelectValue placeholder="Pilih decision" />
                                </SelectTrigger>
                                <SelectContent>
                                    {decisions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.decision} />
                        </div>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="remarks">Remarks</Label>
                            <Textarea id="remarks" value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} disabled={isReadOnly} />
                            <InputError message={errors.remarks} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Weight Samples (10)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InputError message={(errors as Record<string, string>).samples} className="mb-2" />
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            {data.samples.map((row) => (
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
                                    {resultBySample.get(row.sample_no) != null && (
                                        <span className="text-muted-foreground text-xs">Result: {resultBySample.get(row.sample_no)}</span>
                                    )}
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground mt-3 text-xs">
                            Weight result (weight − average empty bottle weight ÷ density) dan average weight dihitung otomatis di server saat
                            disimpan.
                            {fillingCheck?.average_weight != null && <> Average Weight tersimpan: {fillingCheck.average_weight}.</>}
                        </p>
                    </CardContent>
                </Card>

                {!isReadOnly && (
                    <div>
                        <Button type="submit" disabled={processing}>
                            Simpan & Selesaikan Filling Check
                        </Button>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
