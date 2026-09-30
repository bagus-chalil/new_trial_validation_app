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
import { withReturnTo } from '@/lib/return-to';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const errorBorder = 'border-destructive ring-1 ring-destructive';

export default function BatchesCreate({ initialProduct = null }: { initialProduct?: ProductOption | null }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();

    const { data, setData, post, processing, errors, reset } = useForm({
        master_product_id: initialProduct ? String(initialProduct.id) : '',
        master_product_bulk_code_id: initialProduct?.bulk_codes.length === 1 ? String(initialProduct.bulk_codes[0].id) : '',
        no_batch: '',
    });

    // The picker searches the server, so the chosen product (incl. its bulk codes) is kept
    // locally rather than looked up from a full product list shipped with the page.
    const [selectedProduct, setSelectedProduct] = useState<ProductOption | null>(initialProduct);

    const bulkCodeOptions = selectedProduct?.bulk_codes ?? [];
    const missingBulkCode = selectedProduct !== null && bulkCodeOptions.length === 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty: string[] = [];
        if (missingBulkCode) {
            toast('Produk ini belum punya bulk code aktif. Tambahkan bulk code di Master Produk dahulu.');
            return;
        }
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
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="master_product_id">FG Code</Label>
                                    <ProductSearchSelect
                                        id="master_product_id"
                                        flagMissingBulkCode
                                        value={selectedProduct}
                                        onChange={(product) => {
                                            setSelectedProduct(product);
                                            // Pre-select when there's only one option, which is the common case.
                                            const onlyBulkCode = product?.bulk_codes.length === 1 ? String(product.bulk_codes[0].id) : '';
                                            setData((prev) => ({
                                                ...prev,
                                                master_product_id: product ? String(product.id) : '',
                                                master_product_bulk_code_id: onlyBulkCode,
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
                                        disabled={!selectedProduct || missingBulkCode}
                                    >
                                        <SelectTrigger
                                            id="master_product_bulk_code_id"
                                            className={`min-h-11 ${!data.master_product_bulk_code_id && message ? errorBorder : ''}`}
                                        >
                                            <SelectValue
                                                placeholder={
                                                    !selectedProduct
                                                        ? 'Pilih FG Code dahulu'
                                                        : missingBulkCode
                                                          ? 'Tidak ada bulk code'
                                                          : 'Pilih bulk code'
                                                }
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {bulkCodeOptions.map((bulkCode) => (
                                                <SelectItem key={bulkCode.id} value={String(bulkCode.id)}>
                                                    {bulkCode.bulk_code}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {missingBulkCode && (
                                        <div
                                            role="alert"
                                            className="flex items-start gap-2.5 rounded-xl border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                                        >
                                            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                            <div className="text-[13px] leading-snug">
                                                <p className="font-semibold">Produk ini belum punya bulk code aktif.</p>
                                                <p className="mt-0.5">
                                                    Batch tidak bisa dibuat sebelum bulk code ditambahkan di Master Produk.
                                                    {!props.canManageMaster && ' Hubungi admin untuk menambahkannya.'}
                                                </p>
                                                {props.canManageMaster && (
                                                    <Link
                                                        href={withReturnTo(
                                                            route('master-products.index', { q: selectedProduct.fg_code }),
                                                            `/batches/create?product=${selectedProduct.id}`,
                                                        )}
                                                        className="mt-1.5 inline-block font-semibold underline underline-offset-2"
                                                    >
                                                        Buka Master Produk &rarr;
                                                    </Link>
                                                )}
                                            </div>
                                        </div>
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
