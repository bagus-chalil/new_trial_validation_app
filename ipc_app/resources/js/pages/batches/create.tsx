import InputError from '@/components/input-error';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { Toast, useToast } from '@/components/ipc/toast';
import { TwoPane } from '@/components/ipc/two-pane';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { IpcShell } from '@/layouts/ipc-shell';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useMemo } from 'react';

interface BulkCodeOption {
    id: number;
    bulk_code: string;
    no_batch: string;
}

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
    bulk_codes: BulkCodeOption[];
}

interface MasterLine {
    id: number;
    category: string;
    area: string;
    code: string;
    name: string;
}

const errorBorder = 'border-destructive ring-1 ring-destructive';

export default function BatchesCreate({ products, lines }: { products: MasterProduct[]; lines: MasterLine[] }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();

    const { data, setData, post, processing, errors, reset } = useForm({
        master_product_id: '',
        master_product_bulk_code_id: '',
        master_line_id: '',
    });

    const selectedProduct = useMemo(
        () => products.find((product) => String(product.id) === data.master_product_id),
        [products, data.master_product_id],
    );

    const bulkCodeOptions = selectedProduct?.bulk_codes ?? [];

    const selectedBulkCode = useMemo(
        () => bulkCodeOptions.find((bulkCode) => String(bulkCode.id) === data.master_product_bulk_code_id),
        [bulkCodeOptions, data.master_product_bulk_code_id],
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty: string[] = [];
        if (!data.master_product_id) empty.push('FG Code / Produk');
        if (!data.master_product_bulk_code_id) empty.push('Bulk Code');
        if (!data.master_line_id) empty.push('Line');
        if (empty.length) {
            toast(`Field berikut wajib diisi: ${empty.join(', ')}`);
            return;
        }
        post('/batches', { onSuccess: () => reset() });
    };

    return (
        <IpcShell title="Batch Baru" backHref="/batches">
            <Head title="Batch Baru" />
            <Toast message={message} />
            <TwoPane list={<BatchNavList batches={recentBatches} />}>
                <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                    <Card className="max-w-xl">
                        <CardHeader>
                            <CardTitle>Batch Baru</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="master_product_id">FG Code</Label>
                                    <Select
                                        value={data.master_product_id}
                                        onValueChange={(value) => {
                                            setData((prev) => ({ ...prev, master_product_id: value, master_product_bulk_code_id: '' }));
                                        }}
                                    >
                                        <SelectTrigger
                                            id="master_product_id"
                                            className={`min-h-11 ${!data.master_product_id && message ? errorBorder : ''}`}
                                        >
                                            <SelectValue placeholder="Pilih FG Code" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {products.map((product) => (
                                                <SelectItem key={product.id} value={String(product.id)}>
                                                    {product.fg_code} — {product.product_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.master_product_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="product_name">Nama Produk</Label>
                                    <Input id="product_name" className="min-h-11" value={selectedProduct?.product_name ?? ''} disabled readOnly />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="master_product_bulk_code_id">Bulk Code</Label>
                                    <Select
                                        value={data.master_product_bulk_code_id}
                                        onValueChange={(value) => setData('master_product_bulk_code_id', value)}
                                        disabled={!selectedProduct}
                                    >
                                        <SelectTrigger
                                            id="master_product_bulk_code_id"
                                            className={`min-h-11 ${!data.master_product_bulk_code_id && message ? errorBorder : ''}`}
                                        >
                                            <SelectValue placeholder={selectedProduct ? 'Pilih bulk code' : 'Pilih FG Code dahulu'} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {bulkCodeOptions.map((bulkCode) => (
                                                <SelectItem key={bulkCode.id} value={String(bulkCode.id)}>
                                                    {bulkCode.bulk_code}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {selectedProduct && bulkCodeOptions.length === 0 && (
                                        <p className="text-muted-foreground text-xs">Belum ada bulk code aktif untuk produk ini.</p>
                                    )}
                                    <InputError message={errors.master_product_bulk_code_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="no_batch">No Batch</Label>
                                    <Input id="no_batch" className="min-h-11" value={selectedBulkCode?.no_batch ?? ''} disabled readOnly />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="master_line_id">Line</Label>
                                    <Select value={data.master_line_id} onValueChange={(value) => setData('master_line_id', value)}>
                                        <SelectTrigger
                                            id="master_line_id"
                                            className={`min-h-11 ${!data.master_line_id && message ? errorBorder : ''}`}
                                        >
                                            <SelectValue placeholder="Pilih line" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {lines.map((line) => (
                                                <SelectItem key={line.id} value={String(line.id)}>
                                                    {line.code} — {line.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.master_line_id} />
                                </div>

                                <Button type="submit" disabled={processing} size="lg" className="w-full sm:w-auto">
                                    Buat Batch & Lanjut ke Startup Check
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </TwoPane>
        </IpcShell>
    );
}
