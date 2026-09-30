import InputError from '@/components/input-error';
import { MasterImportDialog } from '@/components/ipc/master-import-dialog';
import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IpcShell } from '@/layouts/ipc-shell';
import { RETURN_TO_PARAM, returnToFromUrl } from '@/lib/return-to';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Package, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface BulkCode {
    id: number;
    bulk_code: string;
    is_active: boolean;
}

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
    shelf_life_months: number | null;
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

const emptyProductForm = { id: null as number | null, fg_code: '', product_name: '', shelf_life_months: '' as number | '', is_active: true };
const emptyBulkCodeForm = { id: null as number | null, bulk_code: '', is_active: true };

function BulkCodeManager({ product, open, onOpenChange }: { product: MasterProduct | null; open: boolean; onOpenChange: (open: boolean) => void }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm(emptyBulkCodeForm);

    const startAdd = () => {
        clearErrors();
        reset();
    };

    const startEdit = (bulkCode: BulkCode) => {
        clearErrors();
        setData({ id: bulkCode.id, bulk_code: bulkCode.bulk_code, is_active: bulkCode.is_active });
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
                    <div className="grid gap-1.5">
                        <Label htmlFor="bulk_code">Bulk Code</Label>
                        <Input id="bulk_code" value={data.bulk_code} onChange={(e) => setData('bulk_code', e.target.value)} />
                        <InputError message={errors.bulk_code} />
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

type BulkFilter = 'all' | 'with' | 'without';

const bulkFilterOptions: { value: BulkFilter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'with', label: 'Ada bulk code' },
    { value: 'without', label: 'Belum ada bulk code' },
];

export default function MasterProductsIndex({
    products,
    filters,
    bulkCodeCounts,
}: {
    products: Paginated<MasterProduct>;
    filters: { q?: string; bulk?: string };
    bulkCodeCounts: Record<BulkFilter, number>;
}) {
    const bulkFilter: BulkFilter = filters.bulk === 'with' || filters.bulk === 'without' ? filters.bulk : 'all';
    // Set when another page (e.g. Batch Baru) sent the user here to fix something; kept across
    // search/filter so the back button still leads there.
    const returnTo = returnToFromUrl(usePage().url);
    const keptParams: Record<string, string> = {
        ...(returnTo ? { [RETURN_TO_PARAM]: returnTo } : {}),
        ...(bulkFilter === 'all' ? {} : { bulk: bulkFilter }),
    };
    const setBulkFilter = (value: BulkFilter) => {
        const params: Record<string, string> = returnTo ? { [RETURN_TO_PARAM]: returnTo } : {};
        if (filters.q) params.q = filters.q;
        if (value !== 'all') params.bulk = value;
        router.get(route('master-products.index'), params, { preserveState: true, replace: true });
    };

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
        setData({
            id: product.id,
            fg_code: product.fg_code,
            product_name: product.product_name,
            shelf_life_months: product.shelf_life_months ?? '',
            is_active: product.is_active,
        });
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
                    extraParams={keptParams}
                />

                {returnTo && (
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-[13px] text-blue-900 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-200">
                        <span>Setelah bulk code ditambahkan, kembali ke halaman sebelumnya untuk melanjutkan.</span>
                        <Link href={returnTo} className="flex items-center gap-1 font-semibold underline underline-offset-2">
                            <ArrowLeft className="size-3.5" />
                            Kembali ke halaman sebelumnya
                        </Link>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    {bulkFilterOptions.map((option) => {
                        const active = bulkFilter === option.value;
                        return (
                            <button
                                key={option.value}
                                type="button"
                                aria-pressed={active}
                                onClick={() => setBulkFilter(option.value)}
                                className={`flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-[12.5px] font-semibold transition-colors ${
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border-soft bg-card text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {option.label}
                                <span className={`text-[11.5px] tabular-nums ${active ? 'opacity-80' : 'text-muted-foreground/70'}`}>
                                    {bulkCodeCounts[option.value].toLocaleString('id-ID')}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <MasterImportDialog
                    templateHref={route('master-products.template')}
                    type="master_products"
                    entityLabel="produk"
                    title="Import Master Produk"
                    description="Upload file Excel hasil isian dari template. FG Code dan Nama Produk wajib diisi; Bulk Code dan Shelf Life (Bulan) boleh kosong. Shelf Life yang kosong tidak menghapus nilai yang sudah ada. FG Code / Bulk Code yang sudah ada akan diperbarui, yang belum ada akan ditambahkan."
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
                                    <p
                                        className={`mt-1 flex items-center gap-1 text-[12px] font-medium ${product.shelf_life_months ? 'text-muted-foreground' : 'text-amber-600 dark:text-amber-400'}`}
                                    >
                                        <CalendarClock className="size-3.5" strokeWidth={2} />
                                        {product.shelf_life_months ? `Shelf life ${product.shelf_life_months} bulan` : 'Shelf life belum diisi'}
                                    </p>
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
                            <p className="text-muted-foreground text-sm">
                                {filters.q || bulkFilter !== 'all' ? 'Tidak ada produk yang cocok dengan filter.' : 'Belum ada produk.'}
                            </p>
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
                        <div className="grid gap-2">
                            <Label htmlFor="shelf_life_months">Shelf Life (Bulan)</Label>
                            <Input
                                id="shelf_life_months"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={120}
                                placeholder="mis. 36"
                                value={data.shelf_life_months}
                                onChange={(e) => setData('shelf_life_months', e.target.value === '' ? '' : Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-[12px]">
                                Dipakai OCR untuk menghitung EXP dari MFD. Kosongkan jika belum diketahui.
                            </p>
                            <InputError message={errors.shelf_life_months} />
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
