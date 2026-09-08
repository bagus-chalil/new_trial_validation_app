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
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MasterLine {
    id: number;
    category: string;
    area: string;
    code: string;
    name: string;
    is_active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const emptyForm = { id: null as number | null, category: '', area: '', code: '', name: '', is_active: true };

export default function MasterLinesIndex({ lines, filters }: { lines: Paginated<MasterLine>; filters: { q?: string } }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm(emptyForm);

    const openCreate = () => {
        clearErrors();
        reset();
        setOpen(true);
    };

    const openEdit = (line: MasterLine) => {
        clearErrors();
        setData({ id: line.id, category: line.category, area: line.area, code: line.code, name: line.name, is_active: line.is_active });
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('master-lines.store'), { onSuccess: () => setOpen(false) });
    };

    const destroy = (line: MasterLine) => {
        if (!confirm(`Hapus line "${line.name}"?`)) return;
        router.delete(route('master-lines.destroy', line.id));
    };

    return (
        <IpcShell
            title="Master Line"
            subtitle={`${lines.total} line`}
            backHref="/dashboard"
            headerActions={
                <Button type="button" onClick={openCreate} className="h-10 gap-1.5 px-3.5 text-[13px]">
                    <Plus className="size-4" strokeWidth={2.4} />
                    Line Baru
                </Button>
            }
        >
            <Head title="Master Line" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <MasterSearchBar
                    baseUrl={route('master-lines.index')}
                    initialQ={filters.q ?? ''}
                    placeholder="Cari kategori / area / kode / nama..."
                />

                <div className="flex flex-col gap-3">
                    {lines.data.map((line) => (
                        <div key={line.id} className="border-border-soft bg-card flex items-start justify-between gap-3 rounded-[20px] border p-4">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-[16px] font-bold tracking-tight">{line.code}</p>
                                    <Badge variant={line.is_active ? 'default' : 'secondary'} className="text-[10.5px]">
                                        {line.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                                <p className="mt-0.5 truncate text-[14px] font-semibold">{line.name}</p>
                                <div className="text-muted-foreground/70 mt-1 flex items-center gap-1.5 text-[12.5px] font-medium">
                                    <MapPin className="size-3.5" strokeWidth={2} />
                                    {line.category} &middot; {line.area}
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => openEdit(line)}>
                                    <Pencil className="size-4" />
                                </Button>
                                <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => destroy(line)}>
                                    <Trash2 className="text-destructive size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}

                    {lines.data.length === 0 && (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                            <MapPin className="text-muted-foreground/60 size-8" />
                            <p className="text-muted-foreground text-sm">Belum ada line.</p>
                        </div>
                    )}
                </div>

                <PaginationFooter links={lines.links} lastPage={lines.last_page} />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{data.id ? 'Edit Line' : 'Line Baru'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="category">Kategori</Label>
                                <Input id="category" value={data.category} onChange={(e) => setData('category', e.target.value)} />
                                <InputError message={errors.category} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="area">Area</Label>
                                <Input id="area" value={data.area} onChange={(e) => setData('area', e.target.value)} />
                                <InputError message={errors.area} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code">Kode Line</Label>
                            <Input id="code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama Line</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            <InputError message={errors.name} />
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
        </IpcShell>
    );
}
