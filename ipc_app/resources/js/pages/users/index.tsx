import InputError from '@/components/input-error';
import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { IpcShell } from '@/layouts/ipc-shell';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Power, PowerOff, Users } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const roleBadgeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    admin: 'default',
    approver: 'secondary',
    staff: 'outline',
};

const emptyCreateForm = { name: '', email: '', password: '', password_confirmation: '', role: 'staff' };

function CreateUserDialog({
    open,
    onOpenChange,
    roles,
    roleLabels,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: string[];
    roleLabels: Record<string, string>;
}) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm(emptyCreateForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('users.store'), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    clearErrors();
                    reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah User</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nama</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>
                        <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                            <SelectTrigger id="role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {roleLabels[r] ?? r}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditUserDialog({
    user,
    open,
    onOpenChange,
    roles,
    roleLabels,
}: {
    user: UserRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: string[];
    roleLabels: Record<string, string>;
}) {
    const { data, setData, patch, processing, errors, clearErrors, setDefaults } = useForm({
        name: '',
        email: '',
        role: 'staff',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!user) return;
        patch(route('users.update', user.id), { onSuccess: () => onOpenChange(false) });
    };

    if (!user) return null;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    clearErrors();
                    const defaults = { name: user.name, email: user.email, role: user.role, password: '', password_confirmation: '' };
                    setDefaults(defaults);
                    setData(defaults);
                }
                onOpenChange(next);
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit User</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-2">
                        <Label htmlFor="edit_name">Nama</Label>
                        <Input id="edit_name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit_email">Email</Label>
                        <Input id="edit_email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit_role">Role</Label>
                        <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                            <SelectTrigger id="edit_role">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {roleLabels[r] ?? r}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} />
                    </div>

                    <div className="bg-border-soft h-px" />

                    <div className="grid gap-2">
                        <Label htmlFor="edit_password">Reset Password (opsional)</Label>
                        <Input
                            id="edit_password"
                            type="password"
                            placeholder="Kosongkan jika tidak ingin ubah password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit_password_confirmation">Konfirmasi Password Baru</Label>
                        <Input
                            id="edit_password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function UserCard({
    user,
    currentUserId,
    roleLabels,
    onEdit,
}: {
    user: UserRow;
    currentUserId: number;
    roleLabels: Record<string, string>;
    onEdit: (user: UserRow) => void;
}) {
    const isSelf = user.id === currentUserId;

    const toggleStatus = () => {
        const action = user.is_active ? 'Nonaktifkan' : 'Aktifkan';
        if (!confirm(`${action} user "${user.name}"?`)) return;
        router.patch(route('users.toggle-status', user.id), {}, { preserveScroll: true });
    };

    return (
        <div className="border-border-soft bg-card flex flex-wrap items-center justify-between gap-3 rounded-[20px] border p-4">
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="text-[15px] font-bold tracking-tight">{user.name}</p>
                    <Badge variant={roleBadgeVariant[user.role] ?? 'outline'} className="text-[10.5px]">
                        {roleLabels[user.role] ?? user.role}
                    </Badge>
                    <Badge variant={user.is_active ? 'secondary' : 'destructive'} className="text-[10.5px]">
                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                </div>
                <p className="text-muted-foreground/70 mt-1 text-[12.5px] font-medium">{user.email}</p>
            </div>
            <div className="flex shrink-0 items-center gap-1.5">
                <Button type="button" variant="outline" size="icon" className="size-10" onClick={() => onEdit(user)}>
                    <Pencil className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-10"
                    disabled={isSelf}
                    title={isSelf ? 'Tidak bisa menonaktifkan akun sendiri' : undefined}
                    onClick={toggleStatus}
                >
                    {user.is_active ? <PowerOff className="text-destructive size-4" /> : <Power className="size-4" />}
                </Button>
            </div>
        </div>
    );
}

export default function UsersIndex({
    users,
    filters,
    roles,
    roleLabels,
}: {
    users: Paginated<UserRow>;
    filters: { q?: string };
    roles: string[];
    roleLabels: Record<string, string>;
}) {
    const { props } = usePage<SharedData>();
    const currentUserId = props.auth.user.id;
    const [createOpen, setCreateOpen] = useState(false);
    const [editUser, setEditUser] = useState<UserRow | null>(null);

    return (
        <IpcShell
            title="Manajemen User"
            subtitle={`${users.total} user`}
            backHref="/dashboard"
            headerActions={
                <Button type="button" onClick={() => setCreateOpen(true)} className="h-10 gap-1.5 px-3.5 text-[13px]">
                    <Plus className="size-4" strokeWidth={2.4} />
                    Tambah User
                </Button>
            }
        >
            <Head title="Manajemen User" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <MasterSearchBar baseUrl={route('users.index')} initialQ={filters.q ?? ''} placeholder="Cari nama / email..." />

                <div className="flex flex-col gap-3">
                    {users.data.map((user) => (
                        <UserCard key={user.id} user={user} currentUserId={currentUserId} roleLabels={roleLabels} onEdit={setEditUser} />
                    ))}

                    {users.data.length === 0 && (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                            <Users className="text-muted-foreground/60 size-8" />
                            <p className="text-muted-foreground text-sm">Tidak ada user.</p>
                        </div>
                    )}
                </div>

                <PaginationFooter links={users.links} lastPage={users.last_page} />
            </div>

            <CreateUserDialog open={createOpen} onOpenChange={setCreateOpen} roles={roles} roleLabels={roleLabels} />
            <EditUserDialog
                user={editUser}
                open={editUser !== null}
                onOpenChange={(next) => !next && setEditUser(null)}
                roles={roles}
                roleLabels={roleLabels}
            />
        </IpcShell>
    );
}
