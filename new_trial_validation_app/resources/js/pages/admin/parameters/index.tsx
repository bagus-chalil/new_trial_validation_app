import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ParameterController from '@/actions/App/Http/Controllers/Admin/ParameterController';
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
    longTextCellClassName,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { index as parametersIndex } from '@/routes/admin/parameters';
import type { Paginated } from '@/types';

type Parameter = {
    id: number;
    product_type: string;
    parameter_name: string;
    specification: string | null;
    sort_order: number;
};

type PageProps = {
    parameters: Paginated<Parameter>;
    productTypes: string[];
};

function ParameterFormDialog({
    open,
    onOpenChange,
    editingParameter,
    productTypes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editingParameter: Parameter | null;
    productTypes: string[];
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {editingParameter
                            ? t('master_data.parameters.edit_title')
                            : t('master_data.parameters.add')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...ParameterController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess={[
                        'product_type',
                        'parameter_name',
                        'specification',
                        'sort_order',
                    ]}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {editingParameter && (
                                <input
                                    type="hidden"
                                    name="id"
                                    value={editingParameter.id}
                                />
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="product_type">
                                    {t('master_data.parameters.product_type')}
                                </Label>
                                <Select
                                    name="product_type"
                                    defaultValue={
                                        editingParameter?.product_type ??
                                        productTypes[0]
                                    }
                                >
                                    <SelectTrigger id="product_type">
                                        <SelectValue
                                            placeholder={t(
                                                'master_data.parameters.product_type_placeholder',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {productTypes.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {type}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.product_type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parameter_name">
                                    {t('master_data.parameters.parameter')}
                                </Label>
                                <Input
                                    id="parameter_name"
                                    name="parameter_name"
                                    required
                                    defaultValue={
                                        editingParameter?.parameter_name ?? ''
                                    }
                                />
                                <InputError message={errors.parameter_name} />
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
                                        editingParameter?.sort_order ?? 0
                                    }
                                />
                                <InputError message={errors.sort_order} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="specification">
                                    {t('master_data.parameters.specification')}
                                </Label>
                                <Textarea
                                    id="specification"
                                    name="specification"
                                    defaultValue={
                                        editingParameter?.specification ?? ''
                                    }
                                />
                                <InputError message={errors.specification} />
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {editingParameter
                                        ? t('master_data.actions.save_changes')
                                        : t('master_data.parameters.add')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminParametersIndex({
    parameters,
    productTypes,
}: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingParameter, setEditingParameter] = useState<Parameter | null>(
        null,
    );
    const { t } = useTranslation();

    function openCreate() {
        setEditingParameter(null);
        setDialogOpen(true);
    }

    function openEdit(parameter: Parameter) {
        setEditingParameter(parameter);
        setDialogOpen(true);
    }

    return (
        <>
            <Head title={t('master_data.parameters.head_title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('master_data.parameters.title')}
                    description={t('master_data.parameters.description')}
                />

                <ParameterFormDialog
                    open={dialogOpen}
                    onOpenChange={setDialogOpen}
                    editingParameter={editingParameter}
                    productTypes={productTypes}
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-end">
                        <Button type="button" onClick={openCreate}>
                            {t('master_data.parameters.add')}
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'master_data.parameters.product_type',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.parameters.parameter')}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'master_data.parameters.specification',
                                        )}
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
                                {parameters.data.map((parameter) => (
                                    <TableRow key={parameter.id}>
                                        <TableCell>
                                            {parameter.product_type}
                                        </TableCell>
                                        <TableCell>
                                            {parameter.parameter_name}
                                        </TableCell>
                                        <TableCell
                                            className={longTextCellClassName}
                                        >
                                            {parameter.specification}
                                        </TableCell>
                                        <TableCell>
                                            {parameter.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openEdit(parameter)
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
                                                        'master_data.parameters.delete_title',
                                                    )}
                                                    description={t(
                                                        'master_data.parameters.delete_description',
                                                        {
                                                            type: parameter.product_type,
                                                            name: parameter.parameter_name,
                                                        },
                                                    )}
                                                    confirmLabel={t(
                                                        'master_data.actions.delete',
                                                    )}
                                                    formProps={ParameterController.destroy.form(
                                                        parameter.id,
                                                    )}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {parameters.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('master_data.parameters.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={parametersIndex().url}
                            currentPage={parameters.current_page}
                            lastPage={parameters.last_page}
                            total={parameters.total}
                            itemLabel={t('master_data.parameters.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminParametersIndex.layout = {
    breadcrumbs: [{ title: 'Parameters', href: parametersIndex() }],
};
