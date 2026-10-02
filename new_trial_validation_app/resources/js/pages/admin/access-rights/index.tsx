import { Form, Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import AccessRightController from '@/actions/App/Http/Controllers/Admin/AccessRightController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationFooter } from '@/components/pagination-footer';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { index as accessRightsIndex } from '@/routes/admin/access-rights';
import type { Paginated, User } from '@/types';

type ReviewerDepartment = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
};

type DraftTrial = {
    id: number;
    trial_code: string;
    product_name: string;
    created_by: string | null;
};

type StaffUser = {
    id: number;
    name: string;
    email: string;
};

type DraftPermission = {
    id: number;
    granted_at: string;
    trial: {
        id: number;
        trial_code: string;
        product_name: string;
        created_by: string | null;
    } | null;
    user: { id: number; name: string; email: string } | null;
    granted_by: { id: number; name: string; email: string } | null;
};

type PageProps = {
    auth: { user: User };
    users: Paginated<User>;
    filters: { q: string };
    roleCategories: string[];
    reviewerDepartments: ReviewerDepartment[];
    draftTrials: DraftTrial[];
    staffUsers: StaffUser[];
    draftPermissions: DraftPermission[];
};

function EditRoleDialog({
    open,
    onOpenChange,
    editingUser,
    roleCategories,
    reviewerDepartments,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingUser: User | null;
    roleCategories: string[];
    reviewerDepartments: ReviewerDepartment[];
}) {
    // Phase 2 of the RBAC/Team-master redesign: this app's authoritative
    // role tier lives in `app_role`, not the legacy-shared `role` column
    // (see User::effectiveRole()) — fall back to `role` only for a user
    // never yet saved through this screen (app_role still null).
    const { t } = useTranslation();
    const currentAppRole = editingUser?.app_role ?? editingUser?.role ?? '';

    // An existing user's effective role may hold a value no longer offered
    // here — keep it selectable so saving without touching Role doesn't
    // change it to something unintended.
    const roleOptions =
        currentAppRole && !roleCategories.includes(currentAppRole)
            ? [currentAppRole, ...roleCategories]
            : roleCategories;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.access_rights.roles.edit_title')}
                    </DialogTitle>
                </DialogHeader>
                {editingUser && (
                    <Form
                        {...AccessRightController.updateRole.form(
                            editingUser.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label>{t('admin.fields.user')}</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {editingUser.name} ({editingUser.email})
                                    </p>
                                    {editingUser.department && (
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'admin.access_rights.roles.dept_legacy_note',
                                                {
                                                    department:
                                                        editingUser.department,
                                                },
                                            )}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'admin.access_rights.roles.role_legacy_note',
                                            { role: editingUser.role },
                                        )}
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="app_role">
                                        {t('admin.fields.role')}
                                    </Label>
                                    <Select
                                        name="app_role"
                                        defaultValue={currentAppRole}
                                    >
                                        <SelectTrigger id="app_role">
                                            <SelectValue
                                                placeholder={t(
                                                    'admin.fields.role',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roleOptions.map((role) => (
                                                <SelectItem
                                                    key={role}
                                                    value={role}
                                                >
                                                    {role}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'admin.access_rights.roles.role_hint',
                                        )}
                                    </p>
                                    <InputError message={errors.app_role} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="review_team_id">
                                        {t('admin.fields.review_team')}
                                    </Label>
                                    <Select
                                        name="review_team_id"
                                        defaultValue={
                                            editingUser.review_team_id != null
                                                ? String(
                                                      editingUser.review_team_id,
                                                  )
                                                : '__none'
                                        }
                                    >
                                        <SelectTrigger id="review_team_id">
                                            <SelectValue
                                                placeholder={t(
                                                    'admin.access_rights.none',
                                                )}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">
                                                {t('admin.access_rights.none')}
                                            </SelectItem>
                                            {reviewerDepartments.map(
                                                (department) => (
                                                    <SelectItem
                                                        key={department.id}
                                                        value={String(
                                                            department.id,
                                                        )}
                                                    >
                                                        {department.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'admin.access_rights.roles.review_team_hint',
                                        )}
                                    </p>
                                    <InputError
                                        message={errors.review_team_id}
                                    />
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {t('admin.actions.save_changes')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function ReviewerDepartmentFormDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.access_rights.departments.add_title')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...AccessRightController.storeReviewerDepartment.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={['name', 'sort_order']}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('admin.access_rights.departments.name')}
                                </Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">
                                    {t('admin.fields.sort')}
                                </Label>
                                <Input
                                    id="sort_order"
                                    name="sort_order"
                                    type="number"
                                    defaultValue={0}
                                />
                                <InputError message={errors.sort_order} />
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('admin.access_rights.departments.add')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function ReviewerDepartmentEditDialog({
    open,
    onOpenChange,
    editingDepartment,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingDepartment: ReviewerDepartment | null;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.access_rights.departments.rename_title')}
                    </DialogTitle>
                </DialogHeader>
                {editingDepartment && (
                    <Form
                        {...AccessRightController.updateReviewerDepartment.form(
                            editingDepartment.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit_name">
                                        {t(
                                            'admin.access_rights.departments.name',
                                        )}
                                    </Label>
                                    <Input
                                        id="edit_name"
                                        name="name"
                                        required
                                        defaultValue={editingDepartment.name}
                                    />
                                    <InputError message={errors.name} />
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'admin.access_rights.departments.rename_hint',
                                        )}
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="edit_sort_order">
                                        {t('admin.fields.sort')}
                                    </Label>
                                    <Input
                                        id="edit_sort_order"
                                        name="sort_order"
                                        type="number"
                                        defaultValue={
                                            editingDepartment.sort_order
                                        }
                                    />
                                    <InputError message={errors.sort_order} />
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {t('admin.actions.save_changes')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function GrantPermissionFormDialog({
    open,
    onOpenChange,
    draftTrials,
    staffUsers,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    draftTrials: DraftTrial[];
    staffUsers: StaffUser[];
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.access_rights.permissions.grant_title')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...AccessRightController.grantPermission.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="trial_id">
                                    {t(
                                        'admin.access_rights.permissions.draft_report',
                                    )}
                                </Label>
                                <Select name="trial_id">
                                    <SelectTrigger id="trial_id">
                                        <SelectValue
                                            placeholder={t(
                                                'admin.access_rights.permissions.draft_report_placeholder',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {draftTrials.map((trial) => (
                                            <SelectItem
                                                key={trial.id}
                                                value={String(trial.id)}
                                            >
                                                {trial.trial_code} —{' '}
                                                {trial.product_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.trial_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="user_id">
                                    {t(
                                        'admin.access_rights.permissions.staff_user',
                                    )}
                                </Label>
                                <Select name="user_id">
                                    <SelectTrigger id="user_id">
                                        <SelectValue
                                            placeholder={t(
                                                'admin.access_rights.permissions.staff_user_placeholder',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {staffUsers.map((staff) => (
                                            <SelectItem
                                                key={staff.id}
                                                value={String(staff.id)}
                                            >
                                                {staff.name} ({staff.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.user_id} />
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('admin.access_rights.permissions.grant')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminAccessRightsIndex({
    auth,
    users,
    filters,
    roleCategories,
    reviewerDepartments,
    draftTrials,
    staffUsers,
    draftPermissions,
}: PageProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.q);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [departmentDialogOpen, setDepartmentDialogOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] =
        useState<ReviewerDepartment | null>(null);
    const [permissionDialogOpen, setPermissionDialogOpen] = useState(false);

    function submitSearch(e: FormEvent) {
        e.preventDefault();
        router.get(
            accessRightsIndex().url,
            { q: search },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title={t('admin.access_rights.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('admin.access_rights.title')}
                    description={t('admin.access_rights.description')}
                />

                <EditRoleDialog
                    open={editingUser !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingUser(null);
                        }
                    }}
                    editingUser={editingUser}
                    roleCategories={roleCategories}
                    reviewerDepartments={reviewerDepartments}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('admin.access_rights.roles.title')}
                        </CardTitle>
                        <form
                            onSubmit={submitSearch}
                            className="flex items-end gap-2 pt-2"
                        >
                            <div className="grid gap-2 sm:max-w-sm">
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
                                        {t('admin.fields.review_team')}
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
                                        <TableCell>
                                            {usr.app_role ?? usr.role}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {usr.department ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {usr.review_unit ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {usr.id !== auth.user.id && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setEditingUser(usr)
                                                    }
                                                >
                                                    {t('admin.actions.edit')}
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {users.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('admin.users.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={accessRightsIndex().url}
                            query={{ q: filters.q }}
                            currentPage={users.current_page}
                            lastPage={users.last_page}
                            total={users.total}
                            itemLabel={t('admin.items.users')}
                        />
                    </CardContent>
                </Card>

                <ReviewerDepartmentFormDialog
                    open={departmentDialogOpen}
                    onOpenChange={setDepartmentDialogOpen}
                />

                <ReviewerDepartmentEditDialog
                    open={editingDepartment !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingDepartment(null);
                        }
                    }}
                    editingDepartment={editingDepartment}
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <div>
                            <CardTitle>
                                {t('admin.access_rights.departments.title')}
                            </CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {t(
                                    'admin.access_rights.departments.description',
                                )}
                            </p>
                        </div>
                        <Button
                            type="button"
                            onClick={() => setDepartmentDialogOpen(true)}
                        >
                            {t('admin.access_rights.departments.add')}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('admin.fields.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.sort')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reviewerDepartments.map((department) => (
                                    <TableRow key={department.id}>
                                        <TableCell>{department.name}</TableCell>
                                        <TableCell>
                                            {department.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setEditingDepartment(
                                                            department,
                                                        )
                                                    }
                                                >
                                                    {t('admin.actions.edit')}
                                                </Button>
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
                                                        'admin.access_rights.departments.delete_title',
                                                    )}
                                                    description={t(
                                                        'admin.access_rights.departments.delete_description',
                                                        {
                                                            name: department.name,
                                                        },
                                                    )}
                                                    confirmLabel={t(
                                                        'admin.actions.delete',
                                                    )}
                                                    formProps={AccessRightController.destroyReviewerDepartment.form(
                                                        department.id,
                                                    )}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {reviewerDepartments.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t(
                                                'admin.access_rights.departments.empty',
                                            )}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <GrantPermissionFormDialog
                    open={permissionDialogOpen}
                    onOpenChange={setPermissionDialogOpen}
                    draftTrials={draftTrials}
                    staffUsers={staffUsers}
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>
                            {t('admin.access_rights.permissions.title')}
                        </CardTitle>
                        <Button
                            type="button"
                            onClick={() => setPermissionDialogOpen(true)}
                        >
                            {t('admin.access_rights.permissions.grant')}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'admin.access_rights.permissions.draft_report',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'admin.access_rights.permissions.owner',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'admin.access_rights.permissions.granted_to',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'admin.access_rights.permissions.granted_by',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {draftPermissions.map((permission) => (
                                    <TableRow key={permission.id}>
                                        <TableCell>
                                            {permission.trial?.trial_code} —{' '}
                                            {permission.trial?.product_name}
                                        </TableCell>
                                        <TableCell>
                                            {permission.trial?.created_by}
                                        </TableCell>
                                        <TableCell>
                                            {permission.user?.name} (
                                            {permission.user?.email})
                                        </TableCell>
                                        <TableCell>
                                            {permission.granted_by?.name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                    >
                                                        {t(
                                                            'admin.access_rights.permissions.revoke',
                                                        )}
                                                    </Button>
                                                }
                                                title={t(
                                                    'admin.access_rights.permissions.revoke_title',
                                                )}
                                                description={t(
                                                    'admin.access_rights.permissions.revoke_description',
                                                    {
                                                        name:
                                                            permission.user
                                                                ?.name ?? '-',
                                                        trial:
                                                            permission.trial
                                                                ?.trial_code ??
                                                            '-',
                                                    },
                                                )}
                                                confirmLabel={t(
                                                    'admin.access_rights.permissions.revoke',
                                                )}
                                                formProps={AccessRightController.revokePermission.form(
                                                    permission.id,
                                                )}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {draftPermissions.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t(
                                                'admin.access_rights.permissions.empty',
                                            )}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminAccessRightsIndex.layout = {
    breadcrumbs: [{ title: 'Access Rights', href: accessRightsIndex() }],
};
