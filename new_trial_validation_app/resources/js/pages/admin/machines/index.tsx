import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import MachineLineController from '@/actions/App/Http/Controllers/Admin/MachineLineController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import {
    AreaField,
    FormGrid,
    SelectField,
    TextField,
} from '@/components/machines/fields';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import {
    index as machinesIndex,
    show as machinesShow,
} from '@/routes/admin/machines';
import type { Paginated } from '@/types';

type Plant = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    is_active: boolean;
};

export type Line = {
    id: number;
    line_code: string;
    category: string;
    line_type: string | null;
    name: string;
    plant_id: number | null;
    building: string | null;
    floor: string | null;
    area: string | null;
    status: string;
    products: string | null;
    rated_capacity: number | null;
    capacity_unit: string | null;
    pack_format: string | null;
    automation_level: string | null;
    manpower: number | null;
    pic: string | null;
    layout_link: string | null;
    tooling_link: string | null;
    remarks: string | null;
    plant?: { id: number; code: string; name: string } | null;
    equipment_count?: number;
};

export type MachineOptions = {
    categories: string[];
    statuses: string[];
    automation: string[];
    capacityUnits: string[];
    equipmentTypes: string[];
    conditions: string[];
    manualStatuses: string[];
};

type PageProps = {
    lines: Paginated<Line>;
    plants: Plant[];
    options: MachineOptions;
    filters: {
        q?: string;
        category?: string;
        status?: string;
        plant_id?: string;
    };
};

export function statusClass(status: string): string {
    switch (status) {
        case 'Active':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
        case 'To be verified':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
        case 'Under Maintenance':
        case 'Under Installation':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

export function LineFormDialog({
    open,
    onOpenChange,
    line,
    plants,
    options,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    line: Line | null;
    plants: Plant[];
    options: MachineOptions;
}) {
    const { t } = useTranslation();
    const plantOptions = plants
        .filter((p) => p.is_active)
        .map((p) => ({ value: p.id, label: `${p.code} - ${p.name}` }));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {line
                            ? t('machines.edit_line')
                            : t('machines.add_line')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...MachineLineController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {line && (
                                <input
                                    type="hidden"
                                    name="id"
                                    value={line.id}
                                />
                            )}
                            <FormGrid>
                                {line ? (
                                    <div className="grid gap-2">
                                        <span className="text-sm font-medium">
                                            {t('machines.line_code')}
                                        </span>
                                        <span className="font-mono text-sm">
                                            {line.line_code}
                                        </span>
                                    </div>
                                ) : (
                                    <TextField
                                        name="line_code"
                                        label={t('machines.line_code')}
                                        hint={t('machines.code_hint')}
                                        error={errors.line_code}
                                    />
                                )}
                                <SelectField
                                    name="category"
                                    label={t('machines.category')}
                                    options={options.categories}
                                    defaultValue={line?.category ?? 'Skincare'}
                                    required
                                    error={errors.category}
                                />
                                <TextField
                                    name="name"
                                    label={t('machines.name')}
                                    defaultValue={line?.name}
                                    required
                                    error={errors.name}
                                />
                                <TextField
                                    name="line_type"
                                    label={t('machines.line_type')}
                                    defaultValue={line?.line_type}
                                    error={errors.line_type}
                                />
                                <SelectField
                                    name="status"
                                    label={t('machines.status')}
                                    options={options.statuses}
                                    defaultValue={
                                        line?.status ?? 'To be verified'
                                    }
                                    required
                                    error={errors.status}
                                />
                                <SelectField
                                    name="automation_level"
                                    label={t('machines.automation')}
                                    options={options.automation}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={line?.automation_level}
                                    error={errors.automation_level}
                                />
                                <SelectField
                                    name="plant_id"
                                    label={t('machines.plant')}
                                    options={plantOptions}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={line?.plant_id}
                                    error={errors.plant_id}
                                />
                                <TextField
                                    name="building"
                                    label={t('machines.building')}
                                    defaultValue={line?.building}
                                    error={errors.building}
                                />
                                <TextField
                                    name="floor"
                                    label={t('machines.floor')}
                                    defaultValue={line?.floor}
                                    error={errors.floor}
                                />
                                <TextField
                                    name="area"
                                    label={t('machines.area')}
                                    defaultValue={line?.area}
                                    error={errors.area}
                                />
                                <TextField
                                    name="rated_capacity"
                                    type="number"
                                    label={t('machines.rated_capacity')}
                                    defaultValue={line?.rated_capacity}
                                    error={errors.rated_capacity}
                                />
                                <SelectField
                                    name="capacity_unit"
                                    label={t('machines.capacity_unit')}
                                    options={options.capacityUnits}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={line?.capacity_unit}
                                    error={errors.capacity_unit}
                                />
                                <TextField
                                    name="pack_format"
                                    label={t('machines.pack_format')}
                                    defaultValue={line?.pack_format}
                                    error={errors.pack_format}
                                />
                                <TextField
                                    name="manpower"
                                    type="number"
                                    label={t('machines.manpower')}
                                    defaultValue={line?.manpower}
                                    error={errors.manpower}
                                />
                                <TextField
                                    name="pic"
                                    label={t('machines.pic')}
                                    defaultValue={line?.pic}
                                    error={errors.pic}
                                />
                                <TextField
                                    name="layout_link"
                                    label={t('machines.layout_link')}
                                    defaultValue={line?.layout_link}
                                    error={errors.layout_link}
                                />
                                <TextField
                                    name="tooling_link"
                                    label={t('machines.tooling_link')}
                                    defaultValue={line?.tooling_link}
                                    error={errors.tooling_link}
                                />
                                <AreaField
                                    name="products"
                                    label={t('machines.products')}
                                    className="sm:col-span-2"
                                    defaultValue={line?.products}
                                    error={errors.products}
                                />
                                <AreaField
                                    name="remarks"
                                    label={t('machines.remarks')}
                                    className="sm:col-span-2"
                                    defaultValue={line?.remarks}
                                    error={errors.remarks}
                                />
                            </FormGrid>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('machines.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function PlantsDialog({
    open,
    onOpenChange,
    plants,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    plants: Plant[];
}) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<Plant | null>(null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('machines.plants')}</DialogTitle>
                </DialogHeader>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{t('machines.plant_code')}</TableHead>
                            <TableHead>{t('machines.plant_name')}</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {plants.map((p) => (
                            <TableRow key={p.id}>
                                <TableCell className="font-mono">
                                    {p.code}
                                </TableCell>
                                <TableCell>{p.name}</TableCell>
                                <TableCell className="flex justify-end gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setEditing(p)}
                                    >
                                        {t('master_data.actions.edit')}
                                    </Button>
                                    <ConfirmDialog
                                        trigger={
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                            >
                                                {t(
                                                    'master_data.actions.delete',
                                                )}
                                            </Button>
                                        }
                                        title={t('machines.plants')}
                                        description={`${p.code} - ${p.name}`}
                                        confirmLabel={t(
                                            'master_data.actions.delete',
                                        )}
                                        formProps={MachineLineController.destroyPlant.form(
                                            p.id,
                                        )}
                                    />
                                </TableCell>
                            </TableRow>
                        ))}
                        {plants.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={3}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    {t('machines.plant_empty')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <Form
                    key={editing?.id ?? 'new'}
                    {...MachineLineController.storePlant.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setEditing(null)}
                    className="grid gap-3 border-t pt-4 sm:grid-cols-3"
                >
                    {({ processing, errors }) => (
                        <>
                            {editing && (
                                <input
                                    type="hidden"
                                    name="id"
                                    value={editing.id}
                                />
                            )}
                            <TextField
                                name="code"
                                label={t('machines.plant_code')}
                                defaultValue={editing?.code}
                                required
                                error={errors.code ?? errors.plant}
                            />
                            <TextField
                                name="name"
                                label={t('machines.plant_name')}
                                defaultValue={editing?.name}
                                required
                                error={errors.name}
                            />
                            <TextField
                                name="address"
                                label={t('machines.plant_address')}
                                defaultValue={editing?.address}
                                error={errors.address}
                            />
                            <div className="sm:col-span-3">
                                <Button type="submit" disabled={processing}>
                                    {editing
                                        ? t('machines.save')
                                        : t('machines.add_plant')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function MachineLinesIndex({
    lines,
    plants,
    options,
    filters,
}: PageProps) {
    const { t } = useTranslation();
    const [lineDialog, setLineDialog] = useState<{
        open: boolean;
        line: Line | null;
    }>({ open: false, line: null });
    const [plantsOpen, setPlantsOpen] = useState(false);
    const [form, setForm] = useState({
        q: filters.q ?? '',
        category: filters.category ?? '',
        status: filters.status ?? '',
        plant_id: filters.plant_id ?? '',
    });

    function submit(e?: FormEvent) {
        e?.preventDefault();
        router.get(machinesIndex().url, { ...form }, { preserveState: true });
    }

    function reset() {
        const empty = { q: '', category: '', status: '', plant_id: '' };
        setForm(empty);
        router.get(machinesIndex().url, {}, { preserveState: true });
    }

    return (
        <>
            <Head title={t('machines.head')} />
            <div className="space-y-6 p-4">
                <Heading
                    title={t('machines.title')}
                    description={t('machines.description')}
                />

                <LineFormDialog
                    key={lineDialog.line?.id ?? 'new'}
                    open={lineDialog.open}
                    onOpenChange={(open) =>
                        setLineDialog((s) => ({ ...s, open }))
                    }
                    line={lineDialog.line}
                    plants={plants}
                    options={options}
                />
                <PlantsDialog
                    open={plantsOpen}
                    onOpenChange={setPlantsOpen}
                    plants={plants}
                />

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(q) => setForm((s) => ({ ...s, q }))}
                    searchPlaceholder={t('machines.search')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('machines.category')}
                        value={form.category}
                        onChange={(category) =>
                            setForm((s) => ({ ...s, category }))
                        }
                        options={options.categories}
                        placeholder={t('machines.all')}
                    />
                    <FilterSelect
                        label={t('machines.status')}
                        value={form.status}
                        onChange={(status) =>
                            setForm((s) => ({ ...s, status }))
                        }
                        options={options.statuses}
                        placeholder={t('machines.all')}
                    />
                    <FilterSelect
                        label={t('machines.plant')}
                        value={form.plant_id}
                        onChange={(plant_id) =>
                            setForm((s) => ({ ...s, plant_id }))
                        }
                        options={plants.map((p) => ({
                            value: String(p.id),
                            label: `${p.code} - ${p.name}`,
                        }))}
                        placeholder={t('machines.all')}
                    />
                </FilterBar>

                <Card>
                    <CardHeader className="flex-row items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPlantsOpen(true)}
                        >
                            {t('machines.manage_plants')}
                        </Button>
                        <Button
                            type="button"
                            onClick={() =>
                                setLineDialog({ open: true, line: null })
                            }
                        >
                            {t('machines.add_line')}
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('machines.line_code')}
                                    </TableHead>
                                    <TableHead>{t('machines.name')}</TableHead>
                                    <TableHead>
                                        {t('machines.category')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.location')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.equipment_count')}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.columns.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lines.data.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell className="font-mono font-medium">
                                            <Link
                                                href={machinesShow(line.id)}
                                                className="hover:underline"
                                            >
                                                {line.line_code}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {line.name}
                                            {line.line_type && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {line.line_type}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>{line.category}</TableCell>
                                        <TableCell className="text-sm">
                                            {[
                                                line.plant?.code,
                                                line.building,
                                                line.floor,
                                                line.area,
                                            ]
                                                .filter(Boolean)
                                                .join(' / ') || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={statusClass(
                                                    line.status,
                                                )}
                                            >
                                                {line.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {line.equipment_count ?? 0}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={machinesShow(
                                                            line.id,
                                                        )}
                                                    >
                                                        {t('machines.open')}
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setLineDialog({
                                                            open: true,
                                                            line,
                                                        })
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
                                                        'machines.delete_line_title',
                                                    )}
                                                    description={t(
                                                        'machines.delete_line_desc',
                                                        {
                                                            code: line.line_code,
                                                            name: line.name,
                                                        },
                                                    )}
                                                    confirmLabel={t(
                                                        'master_data.actions.delete',
                                                    )}
                                                    formProps={MachineLineController.destroy.form(
                                                        line.id,
                                                    )}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {lines.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('machines.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={machinesIndex().url}
                            query={{ ...filters }}
                            currentPage={lines.current_page}
                            lastPage={lines.last_page}
                            total={lines.total}
                            itemLabel={t('machines.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MachineLinesIndex.layout = {
    breadcrumbs: [{ title: 'Machines', href: machinesIndex() }],
};
