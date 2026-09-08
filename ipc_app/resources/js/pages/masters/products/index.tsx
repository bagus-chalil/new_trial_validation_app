import InputError from '@/components/input-error';
import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, router, useForm } from '@inertiajs/react';
import { Package, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface BulkCode {
    id: number;
    bulk_code: string;
    no_batch: string | null;
    is_active: boolean;
}

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
    is_active: boolean;
    bulk_codes_count: number;
    bulk_codes: BulkCode[];
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const emptyProductForm = { id: null as number | null, fg_code: '', product_name: '', is_active: true };
const emptyBulkCodeForm = { id: null as number | null, bulk_code: '', no_batch: '', is_active: true };

function BulkCodeManager({ product, open, onOpenChange }: { product: MasterProduct | null; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm(emptyBulkCodeForm);

    const startAdd = () => {
        clearErrors();
        reset();
    };

    const startEdit = (bulkCode: BulkCode) => {
        clearErrors();
        setData({ id: bulkCode.id, bulk_code: bulkCode.bulk_code, no_batch: bulkCode.no_batch ?? '', is_active: bulkCode.is_active });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!product) return;
        post(route('master-product-bulk-codes.store', product.id), { onSuccess: () => startAdd() });
    };

    const destroy = (bulkCode: BulkCode) => {
        if (!product) return;
        if (!confirm(`Hapus bulk code "${bulkCode.bulk_code}"?`)) return;
        router.delete(route('master-product-bulk-codes.destroy', [product.id, bulkCode.id]));
    };

    if (!product) return null;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) startAdd();
                onOpenChange(next);
            }}
        >
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Bulk Code &mdash; {product.product_name}</DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-3">
                    {product.bulk_codes.map((bulkCode) => (
                        <div key={bulkCode.id} className="border-border-soft bg-card flex items-center justify-between gap-3 rounded-2xl border p-3">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-[14px] font-bold">{bulkCode.bulk_code}</p>
                                    <Badge variant={bulkCode.is_active ? 'default' : 'secondary'} className="text-[10.5px]">
                                        {bulkCode.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-0.5 text-[12.5px]">No Batch: {bulkCode.no_batch ?? '-'}</p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <Button type="button" variant="outline" size="icon" className="size-9" onClick={() => startEdit(bulkCode)}>
                                    <Pencil className="size-3.5" />
                                </Button>
                                <Button type="button" variant="outline" size="icon" className="size-9" onClick={() => destroy(bulkCode)}>
                                    <Trash2 className="text-destructive size-3.5" />
                                </Button>
                            </div>
                        </div>
                    ))}

                    {product.bulk_codes.length === 0 && (
                        <p className="text-muted-foreground py-2 text-center text-sm">Belum ada bulk code untuk produk ini.</p>
                    )}
                </div>

                <div className="bg-border-soft h-px" />

                <form onSubmit={submit} className="space-y-3">
                    <p className="text-[13px] font-bold">{data.id ? 'Edit Bulk Code' : 'Tambah Bulk Code'}</p>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="bulk_code">Bulk Code</Label>
                            <Input id="bulk_code" value={data.bulk_code} onChange={(e) => setData('bulk_code', e.target.value)} />
                            <InputError message={errors.bulk_code} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="no_batch">No Batch</Label>
                            <Input id="no_batch" value={data.no_batch} onChange={(e) => setData('no_batch', e.target.value)} />
                            <InputError message={errors.no_batch} />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox id="bc_is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                        <Label htmlFor="bc_is_active">Aktif</Label>
                    </div>
                    <DialogFooter>
                        {data.id && (
                            <Button type="button" variant="outline" onClick={startAdd}>
                                Batal Edit
                            </Button>
                        )}
                        <Button type="submit" disabled={processing}>
                            {data.id ? 'Simpan Perubahan' : 'Tambah'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function MasterProductsIndex({ products, filters }: { products: Paginated<MasterProduct>; filters: { q?: string } }) {
    const [open, setOpen] = useState(false);
    const [bulkCodeProductId, setBulkCodeProductId] = useState<number | null>(null);
    const bulkCodeProduct = products.data.find((product) => product.id === bulkCodeProductId) ?? null;
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm(emptyProductForm);

    const openCreate = () => {
        clearErrors();
        reset();
        setOpen(true);
    };

    const openEdit = (product: MasterProduct) => {
        clearErrors();
        setData({ id: product.id, fg_code: product.fg_code, product_name: product.product_name, is_active: product.is_active });
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('master-products.store'), { onSuccess: () => setOpen(false) });
    };

    const destroy = (product: MasterProduct) => {
        if (!confirm(`Hapus produk "${product.product_name}"? Bulk code terkait juga akan terhapus.`)) return;
        router.delete(route('master-products.destroy', product.id));
    };

    return (
        <IpcShell
            title="Master Produk"
            subtitle={`${products.total} produk`}
            backHref="/dashboard"
            headerActions={
                <Button type="button" onClick={openCreate} className="h-10 gap-1.5 px-3.5 text-[13px]">
                    <Plus className="size-4" strokeWidth={2.4} />
                    Produk Baru
                </Button>
            }
        >
            <Head title="Master Produk" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <MasterSearchBar
                    baseUrl={route('master-products.index')}
                    initialQ={filters.q ?? ''}
                    placeholder="Cari FG code / nama produk / bulk code..."
                />

                <div className="flex flex-col gap-3">
                    {products.data.map((product) => (
                        <div key={product.id} className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-[16px] font-bold tracking-tight">{product.fg_code}</p>
                                        <Badge variant={product.is_active ? 'default' : 'secondary'} className="text-[10.5px]">
                                            {product.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </div>
                                    <p className="mt-0.5 truncate text-[14px] font-semibold">{product.product_name}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1.5">
                                    <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => openEdit(product)}>
                                        <Pencil className="size-4" />
                                    </Button>
                                    <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => destroy(product)}>
                                        <Trash2 className="text-destructive size-4" />
                                    </Button>
                                </div>
                            </div>

                            <div className="bg-border-soft h-px" />

                            <button
                                type="button"
                                onClick={() => setBulkCodeProductId(product.id)}
                                className="text-muted-foreground/80 hover:text-foreground flex items-center gap-1.5 text-[12.5px] font-semibold"
                            >
                                <Tags className="size-3.5" strokeWidth={2} />
                                {product.bulk_codes_count} bulk code &middot; Kelola
                            </button>
                        </div>
                    ))}

                    {products.data.length === 0 && (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                            <Package className="text-muted-foreground/60 size-8" />
                            <p className="text-muted-foreground text-sm">Belum ada produk.</p>
                        </div>
                    )}
                </div>

                <PaginationFooter links={products.links} lastPage={products.last_page} />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{data.id ? 'Edit Produk' : 'Produk Baru'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="fg_code">FG Code</Label>
                            <Input id="fg_code" value={data.fg_code} onChange={(e) => setData('fg_code', e.target.value)} />
                            <InputError message={errors.fg_code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product_name">Nama Produk</Label>
                            <Input id="product_name" value={data.product_name} onChange={(e) => setData('product_name', e.target.value)} />
                            <InputError message={errors.product_name} />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                            <Label htmlFor="is_active">Aktif</Label>
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={processing}>
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <BulkCodeManager product={bulkCodeProduct} open={bulkCodeProduct !== null} onOpenChange={(next) => !next && setBulkCodeProductId(null)} />
        </IpcShell>
    );
}
