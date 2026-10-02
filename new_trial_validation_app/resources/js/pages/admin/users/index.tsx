import { Form, Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import ImpersonationController from '@/actions/App/Http/Controllers/Admin/ImpersonationController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationFooter } from '@/components/pagination-footer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { index as usersIndex } from '@/routes/admin/users';
import type { Auth, Paginated, User } from '@/types';

type PageProps = {
    auth: Auth;
    users: Paginated<User>;
    roleCategories: string[];
    filters: { q: string };
};

function UserFormDialog({
    open,
    onOpenChange,
    editingUser,
    roleCategories,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingUser: User | null;
    roleCategories: string[];
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editingUser
                            ? t('admin.users.edit')
                            : t('admin.users.add')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...UserController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={['name', 'email', 'password']}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            {editingUser && (
                                <input
                                    type="hidden"
                                    name="id"
                                    defaultValue={editingUser.id}
                                />
                            )}
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="name">
                                    {t('admin.fields.name')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={editingUser?.name ?? ''}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="email">
                                    {t('admin.fields.email')}
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    readOnly={!!editingUser}
                                    className={
                                        editingUser
                                            ? 'bg-muted text-muted-foreground'
                                            : undefined
                                    }
                                    defaultValue={editingUser?.email ?? ''}
                                />
                                {editingUser && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.users.email_locked')}
                                    </p>
                                )}
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="password">
                                    {editingUser
                                        ? t('admin.users.new_password')
                                        : t('admin.users.password')}
                                </Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    required
                                    placeholder={
                                        editingUser
                                            ? t('admin.users.password_reenter')
                                            : undefined
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="role">
                                    {t('admin.fields.role')}
                                </Label>
                                <Select
                                    name="role"
                                    defaultValue={
                                        editingUser?.role ?? roleCategories[0]
                                    }
                                >
                                    <SelectTrigger id="role">
                                        <SelectValue
                                            placeholder={t('admin.fields.role')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roleCategories.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {role}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    {t('admin.users.role_hint')}
                                </p>
                                <InputError message={errors.role} />
                            </div>

                            <DialogFooter className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {editingUser
                                        ? t('admin.actions.save_changes')
                                        : t('admin.users.add')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminUsersIndex({
    auth,
    users,
    roleCategories,
    filters,
}: PageProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.q);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);

    function submitSearch(e: FormEvent) {
        e.preventDefault();
        router.get(usersIndex().url, { q: search }, { preserveState: true });
    }

    function openCreate() {
        setEditingUser(null);
        setDialogOpen(true);
    }

    function openEdit(user: User) {
        setEditingUser(user);
        setDialogOpen(true);
    }

    return (
        <>
            <Head title={t('admin.users.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('admin.users.title')}
                    description={t('admin.users.description')}
                />

                <UserFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    editingUser={editingUser}
                    roleCategories={roleCategories}
                />

                <Card>
                    <CardHeader className="flex-row flex-wrap items-end justify-between gap-4">
                        <form
                            onSubmit={submitSearch}
                            className="flex flex-1 items-end gap-2"
                        >
                            <div className="grid flex-1 gap-2 sm:max-w-sm">
                                <Label htmlFor="q">
                                    {t('admin.search_user.label')}
                                </Label>
                                <Input
                                    id="q"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder={t(
                                        'admin.search_user.placeholder',
                                    )}
                                />
                            </div>
                            <Button type="submit" variant="secondary">
                                {t('common.actions.search')}
                            </Button>
                        </form>
                        <Button type="button" onClick={openCreate}>
                            {t('admin.users.add')}
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('admin.fields.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.email')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.role')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.dept_legacy')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((usr) => (
                                    <TableRow key={usr.id}>
                                        <TableCell>{usr.name}</TableCell>
                                        <TableCell>{usr.email}</TableCell>
                                        <TableCell>{usr.role}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {usr.department ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                {usr.id !== auth.user.id && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(usr)
                                                        }
                                                    >
                                                        {t(
                                                            'admin.actions.edit',
                                                        )}
                                                    </Button>
                                                )}
                                                {usr.id !== auth.user.id &&
                                                    (usr.role !==
                                                        'Super Admin' ||
                                                        auth.user.role ===
                                                            'Super Admin') && (
                                                        <ConfirmDialog
                                                            trigger={
                                                                <Button
                                                                    variant="destructive"
                                                                    size="sm"
                                                                >
                                                                    {t(
                                                                        'admin.actions.delete',
                                                                    )}
                                                                </Button>
                                                            }
                                                            title={t(
                                                                'admin.users.delete_title',
                                                            )}
                                                            description={t(
                                                                'admin.users.delete_description',
                                                                {
                                                                    name: usr.name,
                                                                    email: usr.email,
                                                                },
                                                            )}
                                                            confirmLabel={t(
                                                                'admin.actions.delete',
                                                            )}
                                                            formProps={UserController.destroy.form(
                                                                usr.id,
                                                            )}
                                                        />
                                                    )}
                                                {usr.id !== auth.user.id &&
                                                    (usr.role !==
                                                        'Super Admin' ||
                                                        auth.user.role ===
                                                            'Super Admin') && (
                                                        <ConfirmDialog
                                                            trigger={
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                >
                                                                    {t(
                                                                        'admin.users.login_as',
                                                                    )}
                                                                </Button>
                                                            }
                                                            title={t(
                                                                'admin.users.login_as_title',
                                                            )}
                                                            description={t(
                                                                'admin.users.login_as_description',
                                                                {
                                                                    name: usr.name,
                                                                    email: usr.email,
                                                                },
                                                            )}
                                                            confirmLabel={t(
                                                                'admin.users.login_as',
                                                            )}
                                                            confirmVariant="default"
                                                            formProps={ImpersonationController.store.form(
                                                                usr.id,
                                                            )}
                                                        />
                                                    )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {users.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('admin.users.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={usersIndex().url}
                            query={{ q: filters.q }}
                            currentPage={users.current_page}
                            lastPage={users.last_page}
                            total={users.total}
                            itemLabel={t('admin.items.users')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: usersIndex() }],
};
