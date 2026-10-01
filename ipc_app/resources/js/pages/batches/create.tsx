import InputError from '@/components/input-error';
import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { ProductSearchSelect, type ProductOption } from '@/components/ipc/product-search-select';
import { Toast, useToast } from '@/components/ipc/toast';
import { TwoPane } from '@/components/ipc/two-pane';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { withReturnTo } from '@/lib/return-to';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const errorBorder = 'border-destructive ring-1 ring-destructive';

// Every active bulk code is ticked by default — most FG codes have one, and multi-bulk
// batches usually use all of them; the user unticks whatever this batch doesn't use.
const allBulkCodeIds = (product: ProductOption | null) => product?.bulk_codes.map((bulkCode) => String(bulkCode.id)) ?? [];

export default function BatchesCreate({ initialProduct = null }: { initialProduct?: ProductOption | null }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];
    const { message, toast } = useToast();

    const { data, setData, post, processing, errors, reset } = useForm({
        master_product_id: initialProduct ? String(initialProduct.id) : '',
        master_product_bulk_code_ids: allBulkCodeIds(initialProduct),
        no_batch: '',
    });

    // The picker searches the server, so the chosen product (incl. its bulk codes) is kept
    // locally rather than looked up from a full product list shipped with the page.
    const [selectedProduct, setSelectedProduct] = useState<ProductOption | null>(initialProduct);

    const bulkCodeOptions = selectedProduct?.bulk_codes ?? [];
    const missingBulkCode = selectedProduct !== null && bulkCodeOptions.length === 0;
    const selectedCount = data.master_product_bulk_code_ids.length;
    const allSelected = bulkCodeOptions.length > 0 && selectedCount === bulkCodeOptions.length;
    const bulkCodeError =
        errors.master_product_bulk_code_ids ?? Object.entries(errors).find(([key]) => key.startsWith('master_product_bulk_code_ids.'))?.[1];

    const toggleBulkCode = (id: string, checked: boolean) =>
        setData(
            'master_product_bulk_code_ids',
            checked ? [...data.master_product_bulk_code_ids, id] : data.master_product_bulk_code_ids.filter((selected) => selected !== id),
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const empty: string[] = [];
        if (missingBulkCode) {
            toast('Produk ini belum punya bulk code aktif. Tambahkan bulk code di Master Produk dahulu.');
            return;
        }
        if (!data.master_product_id) empty.push('FG Code / Produk');
        if (selectedProduct && !selectedCount) empty.push('Bulk Code (minimal 1)');
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
                                            setData((prev) => ({
                                                ...prev,
                                                master_product_id: product ? String(product.id) : '',
                                                master_product_bulk_code_ids: allBulkCodeIds(product),
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
                                    <div className="flex items-baseline justify-between gap-2">
                                        <Label>Bulk Code</Label>
                                        {bulkCodeOptions.length > 0 && (
                                            <span className="text-muted-foreground text-[12.5px]">
                                                Tersedia <span className="text-foreground font-semibold">{bulkCodeOptions.length}</span> bulk ·
                                                terpilih <span className="text-foreground font-semibold">{selectedCount}</span>
                                            </span>
                                        )}
                                    </div>
                                    {!selectedProduct ? (
                                        <div className="border-input text-muted-foreground flex min-h-11 items-center rounded-md border px-3 text-sm">
                                            Pilih FG Code dahulu
                                        </div>
                                    ) : (
                                        bulkCodeOptions.length > 0 && (
                                            <div
                                                className={`border-input overflow-hidden rounded-md border ${!selectedCount && message ? errorBorder : ''}`}
                                            >
                                                {bulkCodeOptions.length > 1 && (
                                                    <div className="bg-muted/40 flex items-center justify-end border-b px-3 py-1.5">
                                                        <button
                                                            type="button"
                                                            className="text-primary text-[12.5px] font-semibold"
                                                            onClick={() =>
                                                                setData(
                                                                    'master_product_bulk_code_ids',
                                                                    allSelected ? [] : allBulkCodeIds(selectedProduct),
                                                                )
                                                            }
                                                        >
                                                            {allSelected ? 'Kosongkan' : 'Pilih semua'}
                                                        </button>
                                                    </div>
                                                )}
                                                <div className="max-h-60 divide-y overflow-y-auto">
                                                    {bulkCodeOptions.map((bulkCode) => {
                                                        const id = String(bulkCode.id);
                                                        return (
                                                            <label
                                                                key={bulkCode.id}
                                                                htmlFor={`bulk-code-${id}`}
                                                                className="hover:bg-muted/40 flex min-h-11 cursor-pointer items-center gap-3 px-3 text-sm"
                                                            >
                                                                <Checkbox
                                                                    id={`bulk-code-${id}`}
                                                                    checked={data.master_product_bulk_code_ids.includes(id)}
                                                                    onCheckedChange={(checked) => toggleBulkCode(id, checked === true)}
                                                                />
                                                                {bulkCode.bulk_code}
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )
                                    )}
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
                                    <InputError message={bulkCodeError} />
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
