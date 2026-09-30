import InputError from '@/components/input-error';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { ProductSearchSelect, type ProductOption } from '@/components/ipc/product-search-select';
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
import { FormEventHandler, useState } from 'react';

const errorBorder = 'border-destructive ring-1 ring-destructive';

export default function BatchesCreate() {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();

    const { data, setData, post, processing, errors, reset } = useForm({
        master_product_id: '',
        master_product_bulk_code_id: '',
        no_batch: '',
    });

    // The picker searches the server, so the chosen product (incl. its bulk codes) is kept
    // locally rather than looked up from a full product list shipped with the page.
    const [selectedProduct, setSelectedProduct] = useState<ProductOption | null>(null);

    const bulkCodeOptions = selectedProduct?.bulk_codes ?? [];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty: string[] = [];
        if (!data.master_product_id) empty.push('FG Code / Produk');
        if (!data.master_product_bulk_code_id) empty.push('Bulk Code');
        if (!data.no_batch.trim()) empty.push('No Batch FG');
        if (empty.length) {
            toast(`Field berikut wajib diisi: ${empty.join(', ')}`);
            return;
        }
        post('/batches', {
            onSuccess: () => {
                reset();
                setSelectedProduct(null);
            },
        });
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
                                    <ProductSearchSelect
                                        id="master_product_id"
                                        value={selectedProduct}
                                        onChange={(product) => {
                                            setSelectedProduct(product);
                                            setData((prev) => ({
                                                ...prev,
                                                master_product_id: product ? String(product.id) : '',
                                                master_product_bulk_code_id: '',
                                            }));
                                        }}
                                        className={!data.master_product_id && message ? errorBorder : ''}
                                    />
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
                                    <Label htmlFor="no_batch">No Batch FG</Label>
                                    <Input
                                        id="no_batch"
                                        className={`min-h-11 ${!data.no_batch.trim() && message ? errorBorder : ''}`}
                                        value={data.no_batch}
                                        onChange={(e) => setData('no_batch', e.target.value.toUpperCase())}
                                        placeholder="Masukkan no batch FG"
                                    />
                                    <InputError message={errors.no_batch} />
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
