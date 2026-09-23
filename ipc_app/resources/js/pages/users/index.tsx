import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useState } from 'react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
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

function UserRoleRow({ user, roles, roleLabels }: { user: UserRow; roles: string[]; roleLabels: Record<string, string> }) {
    const [role, setRole] = useState(user.role);
    const [saving, setSaving] = useState(false);
    const dirty = role !== user.role;

    const save = () => {
        setSaving(true);
        router.patch(route('users.update-role', user.id), { role }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <div className="border-border-soft bg-card flex flex-wrap items-center justify-between gap-3 rounded-[20px] border p-4">
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <p className="text-[15px] font-bold tracking-tight">{user.name}</p>
                    <Badge variant={roleBadgeVariant[user.role] ?? 'outline'} className="text-[10.5px]">
                        {roleLabels[user.role] ?? user.role}
                    </Badge>
                </div>
                <p className="text-muted-foreground/70 mt-1 text-[12.5px] font-medium">{user.email}</p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <Select value={role} onValueChange={setRole}>
                    <SelectTrigger className="w-[140px]">
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
                <Button type="button" size="sm" disabled={!dirty || saving} onClick={save}>
                    Simpan
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
    return (
        <IpcShell title="Manajemen User" subtitle={`${users.total} user`} backHref="/dashboard">
            <Head title="Manajemen User" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                <MasterSearchBar baseUrl={route('users.index')} initialQ={filters.q ?? ''} placeholder="Cari nama / email..." />

                <div className="flex flex-col gap-3">
                    {users.data.map((user) => (
                        <UserRoleRow key={user.id} user={user} roles={roles} roleLabels={roleLabels} />
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
        </IpcShell>
    );
}
