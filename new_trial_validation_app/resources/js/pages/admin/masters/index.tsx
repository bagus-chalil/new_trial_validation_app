import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import MasterOptionController from '@/actions/App/Http/Controllers/Admin/MasterOptionController';
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
import type { TranslateFn } from '@/hooks/use-translation';
import { index as mastersIndex } from '@/routes/admin/masters';
import type { Paginated } from '@/types';

type MasterOption = {
    id: number;
    type: string;
    name: string;
    sort_order: number;
};

type PageProps = {
    options: Paginated<MasterOption>;
    types: string[];
};

// Display-only label for a stored master_options.type value; the raw value
// stays the form value and is shown as-is when no label exists.
function masterTypeLabel(t: TranslateFn, type: string): string {
    const key = `master_data.masters.types.${type}`;
    const label = t(key);

    return label === key ? type : label;
}

function MasterOptionFormDialog({
    open,
    onOpenChange,
    editingOption,
    types,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingOption: MasterOption | null;
    types: string[];
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editingOption
                            ? t('master_data.masters.edit_title')
                            : t('master_data.masters.add')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...MasterOptionController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={['type', 'name', 'sort_order']}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {editingOption && (
                                <input
                                    type="hidden"
                                    name="id"
                                    value={editingOption.id}
                                />
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="type">
                                    {t('master_data.masters.type')}
                                </Label>
                                <Select
                                    name="type"
                                    defaultValue={
                                        editingOption?.type ?? types[0]
                                    }
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue
                                            placeholder={t(
                                                'master_data.masters.type_placeholder',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {masterTypeLabel(t, type)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('master_data.masters.name')}
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={editingOption?.name ?? ''}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">
                                    {t('master_data.columns.sort')}
                                </Label>
                                <Input
                                    id="sort_order"
                                    name="sort_order"
                                    type="number"
                                    defaultValue={
                                        editingOption?.sort_order ?? 0
                                    }
                                />
                                <InputError message={errors.sort_order} />
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {editingOption
                                        ? t('master_data.actions.save_changes')
                                        : t('master_data.masters.add')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminMastersIndex({ options, types }: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingOption, setEditingOption] = useState<MasterOption | null>(
        null,
    );
    const { t } = useTranslation();

    function openCreate() {
        setEditingOption(null);
        setDialogOpen(true);
    }

    function openEdit(option: MasterOption) {
        setEditingOption(option);
        setDialogOpen(true);
    }

    return (
        <>
            <Head title={t('master_data.masters.head_title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('master_data.masters.title')}
                    description={t('master_data.masters.description')}
                />

                <MasterOptionFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    editingOption={editingOption}
                    types={types}
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-end">
                        <Button type="button" onClick={openCreate}>
                            {t('master_data.masters.add')}
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('master_data.masters.type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.masters.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.columns.sort')}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.columns.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {options.data.map((option) => (
                                    <TableRow key={option.id}>
                                        <TableCell>
                                            {masterTypeLabel(t, option.type)}
                                        </TableCell>
                                        <TableCell>{option.name}</TableCell>
                                        <TableCell>
                                            {option.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openEdit(option)
                                                    }
                                                >
                                                    {t(
                                                        'master_data.actions.edit',
                                                    )}
                                                </Button>
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                        >
                                                            {t(
                                                                'master_data.actions.delete',
                                                            )}
                                                        </Button>
                                                    }
                                                    title={t(
                                                        'master_data.masters.delete_title',
                                                    )}
                                                    description={t(
                                                        'master_data.masters.delete_description',
                                                        {
                                                            type: masterTypeLabel(
                                                                t,
                                                                option.type,
                                                            ),
                                                            name: option.name,
                                                        },
                                                    )}
                                                    confirmLabel={t(
                                                        'master_data.actions.delete',
                                                    )}
                                                    formProps={MasterOptionController.destroy.form(
                                                        option.id,
                                                    )}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {options.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('master_data.masters.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={mastersIndex().url}
                            currentPage={options.current_page}
                            lastPage={options.last_page}
                            total={options.total}
                            itemLabel={t('master_data.masters.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminMastersIndex.layout = {
    breadcrumbs: [{ title: 'Masters', href: mastersIndex() }],
};
