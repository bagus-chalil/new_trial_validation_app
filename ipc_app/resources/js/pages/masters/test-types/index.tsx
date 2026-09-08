import InputError from '@/components/input-error';
import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, router, useForm } from '@inertiajs/react';
import { FlaskConical, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MasterTestType {
    id: number;
    name: string;
    category: string;
    is_active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const emptyForm = { id: null as number | null, name: '', category: '', is_active: true };

const categoryBadgeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    Leakage: 'default',
    Functional: 'secondary',
    Attribute: 'outline',
};

export default function MasterTestTypesIndex({
    testTypes,
    filters,
    categories,
}: {
    testTypes: Paginated<MasterTestType>;
    filters: { q?: string };
    categories: string[];
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm(emptyForm);

    const openCreate = () => {
        clearErrors();
        reset();
        setData('category', categories[0] ?? '');
        setOpen(true);
    };

    const openEdit = (testType: MasterTestType) => {
        clearErrors();
        setData({ id: testType.id, name: testType.name, category: testType.category, is_active: testType.is_active });
        setOpen(true);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('master-test-types.store'), { onSuccess: () => setOpen(false) });
    };

    const destroy = (testType: MasterTestType) => {
        if (!confirm(`Hapus test type "${testType.name}"?`)) return;
        router.delete(route('master-test-types.destroy', testType.id));
    };

    return (
        <IpcShell
            title="Master Test Type"
            subtitle={`${testTypes.total} test type`}
            backHref="/dashboard"
            headerActions={
                <Button type="button" onClick={openCreate} className="h-10 gap-1.5 px-3.5 text-[13px]">
                    <Plus className="size-4" strokeWidth={2.4} />
                    Test Type Baru
                </Button>
            }
        >
            <Head title="Master Test Type" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <MasterSearchBar baseUrl={route('master-test-types.index')} initialQ={filters.q ?? ''} placeholder="Cari nama / kategori..." />

                <div className="flex flex-col gap-3">
                    {testTypes.data.map((testType) => (
                        <div
                            key={testType.id}
                            className="border-border-soft bg-card flex items-start justify-between gap-3 rounded-[20px] border p-4"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-[15px] font-bold tracking-tight">{testType.name}</p>
                                    <Badge variant={testType.is_active ? 'default' : 'secondary'} className="text-[10.5px]">
                                        {testType.is_active ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </div>
                                <div className="text-muted-foreground/70 mt-1.5 flex items-center gap-1.5 text-[12.5px] font-medium">
                                    <FlaskConical className="size-3.5" strokeWidth={2} />
                                    <Badge variant={categoryBadgeVariant[testType.category] ?? 'outline'} className="text-[10.5px]">
                                        {testType.category}
                                    </Badge>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-1.5">
                                <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => openEdit(testType)}>
                                    <Pencil className="size-4" />
                                </Button>
                                <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => destroy(testType)}>
                                    <Trash2 className="text-destructive size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}

                    {testTypes.data.length === 0 && (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                            <FlaskConical className="text-muted-foreground/60 size-8" />
                            <p className="text-muted-foreground text-sm">Belum ada test type.</p>
                        </div>
                    )}
                </div>

                <PaginationFooter links={testTypes.links} lastPage={testTypes.last_page} />
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{data.id ? 'Edit Test Type' : 'Test Type Baru'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nama Test Type</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="category">Kategori</Label>
                            <Select value={data.category} onValueChange={(value) => setData('category', value)}>
                                <SelectTrigger id="category">
                                    <SelectValue placeholder="Pilih kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category} value={category}>
                                            {category}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.category} />
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
