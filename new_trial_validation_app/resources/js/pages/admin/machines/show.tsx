import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import EquipmentController from '@/actions/App/Http/Controllers/Admin/EquipmentController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import {
    AreaField,
    FormGrid,
    SelectField,
    TextField,
} from '@/components/machines/fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import type { Line, MachineOptions } from './index';
import { statusClass } from './index';

type Equipment = {
    id: number;
    equipment_code: string;
    process_step: number | null;
    name: string;
    equipment_type: string | null;
    manufacturer: string | null;
    model: string | null;
    serial_no: string | null;
    year_made: number | null;
    country: string | null;
    rated_capacity: number | null;
    capacity_unit: string | null;
    max_capacity: number | null;
    heads: string | null;
    working_volume: string | null;
    voltage: string | null;
    compressed_air: string | null;
    dimension: string | null;
    weight_kg: string | null;
    other_spec: string | null;
    condition: string | null;
    manual_status: string | null;
    manual_link: string | null;
    drawing_link: string | null;
    qty: number | null;
    remarks: string | null;
};

type PageProps = {
    line: Line;
    equipment: Equipment[];
    options: MachineOptions;
};

function EquipmentDialog({
    open,
    onOpenChange,
    lineId,
    item,
    options,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    lineId: number;
    item: Equipment | null;
    options: MachineOptions;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {item
                            ? t('machines.edit_equipment')
                            : t('machines.add_equipment')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...EquipmentController.store.form(lineId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {item && (
                                <input
                                    type="hidden"
                                    name="id"
                                    value={item.id}
                                />
                            )}
                            <FormGrid>
                                <div className="grid gap-1">
                                    <span className="text-sm font-medium">
                                        {t('machines.eq_code')}
                                    </span>
                                    <span className="font-mono text-sm">
                                        {item?.equipment_code ??
                                            t('machines.eq_code_hint')}
                                    </span>
                                </div>
                                <TextField
                                    name="process_step"
                                    type="number"
                                    label={t('machines.step')}
                                    defaultValue={item?.process_step}
                                    error={errors.process_step}
                                />
                                <TextField
                                    name="name"
                                    label={t('machines.name')}
                                    defaultValue={item?.name}
                                    required
                                    error={errors.name}
                                />
                                <SelectField
                                    name="equipment_type"
                                    label={t('machines.eq_type')}
                                    options={options.equipmentTypes}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={item?.equipment_type}
                                    error={errors.equipment_type}
                                />
                                <TextField
                                    name="manufacturer"
                                    label={t('machines.manufacturer')}
                                    defaultValue={item?.manufacturer}
                                    error={errors.manufacturer}
                                />
                                <TextField
                                    name="model"
                                    label={t('machines.model')}
                                    defaultValue={item?.model}
                                    error={errors.model}
                                />
                                <TextField
                                    name="serial_no"
                                    label={t('machines.serial_no')}
                                    defaultValue={item?.serial_no}
                                    error={errors.serial_no}
                                />
                                <TextField
                                    name="year_made"
                                    type="number"
                                    label={t('machines.year_made')}
                                    defaultValue={item?.year_made}
                                    error={errors.year_made}
                                />
                                <TextField
                                    name="country"
                                    label={t('machines.country')}
                                    defaultValue={item?.country}
                                    error={errors.country}
                                />
                                <TextField
                                    name="qty"
                                    type="number"
                                    label={t('machines.qty')}
                                    defaultValue={item?.qty}
                                    error={errors.qty}
                                />
                                <TextField
                                    name="rated_capacity"
                                    type="number"
                                    label={t('machines.rated_capacity')}
                                    defaultValue={item?.rated_capacity}
                                    error={errors.rated_capacity}
                                />
                                <TextField
                                    name="max_capacity"
                                    type="number"
                                    label={t('machines.max_capacity')}
                                    defaultValue={item?.max_capacity}
                                    error={errors.max_capacity}
                                />
                                <SelectField
                                    name="capacity_unit"
                                    label={t('machines.capacity_unit')}
                                    options={options.capacityUnits}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={item?.capacity_unit}
                                    error={errors.capacity_unit}
                                />
                                <TextField
                                    name="heads"
                                    label={t('machines.heads')}
                                    defaultValue={item?.heads}
                                    error={errors.heads}
                                />
                                <TextField
                                    name="working_volume"
                                    label={t('machines.working_volume')}
                                    defaultValue={item?.working_volume}
                                    error={errors.working_volume}
                                />
                                <TextField
                                    name="voltage"
                                    label={t('machines.voltage')}
                                    defaultValue={item?.voltage}
                                    error={errors.voltage}
                                />
                                <TextField
                                    name="compressed_air"
                                    label={t('machines.compressed_air')}
                                    defaultValue={item?.compressed_air}
                                    error={errors.compressed_air}
                                />
                                <TextField
                                    name="dimension"
                                    label={t('machines.dimension')}
                                    defaultValue={item?.dimension}
                                    error={errors.dimension}
                                />
                                <TextField
                                    name="weight_kg"
                                    label={t('machines.weight')}
                                    defaultValue={item?.weight_kg}
                                    error={errors.weight_kg}
                                />
                                <SelectField
                                    name="condition"
                                    label={t('machines.condition')}
                                    options={options.conditions}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={item?.condition}
                                    error={errors.condition}
                                />
                                <SelectField
                                    name="manual_status"
                                    label={t('machines.manual_status')}
                                    options={options.manualStatuses}
                                    emptyLabel={t('machines.not_set')}
                                    defaultValue={item?.manual_status}
                                    error={errors.manual_status}
                                />
                                <TextField
                                    name="manual_link"
                                    label={t('machines.manual_link')}
                                    defaultValue={item?.manual_link}
                                    error={errors.manual_link}
                                />
                                <TextField
                                    name="drawing_link"
                                    label={t('machines.drawing_link')}
                                    defaultValue={item?.drawing_link}
                                    error={errors.drawing_link}
                                />
                                <AreaField
                                    name="other_spec"
                                    label={t('machines.other_spec')}
                                    className="sm:col-span-2"
                                    defaultValue={item?.other_spec}
                                    error={errors.other_spec}
                                />
                                <AreaField
                                    name="remarks"
                                    label={t('machines.remarks')}
                                    className="sm:col-span-2"
                                    defaultValue={item?.remarks}
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

export default function MachineLineShow({
    line,
    equipment,
    options,
}: PageProps) {
    const { t } = useTranslation();
    const [dialog, setDialog] = useState<{
        open: boolean;
        item: Equipment | null;
    }>({ open: false, item: null });

    const info: [string, string | number | null | undefined][] = [
        [t('machines.category'), line.category],
        [t('machines.line_type'), line.line_type],
        [
            t('machines.location'),
            [line.plant?.name, line.building, line.floor, line.area]
                .filter(Boolean)
                .join(' / '),
        ],
        [t('machines.automation'), line.automation_level],
        [
            t('machines.rated_capacity'),
            line.rated_capacity
                ? `${line.rated_capacity} ${line.capacity_unit ?? ''}`
                : null,
        ],
        [t('machines.pack_format'), line.pack_format],
        [t('machines.manpower'), line.manpower],
        [t('machines.pic'), line.pic],
        [t('machines.products'), line.products],
    ];

    return (
        <>
            <Head title={line.line_code} />
            <div className="space-y-6 p-4">
                <Button asChild variant="ghost" size="sm">
                    <Link href={machinesIndex()}>{t('machines.back')}</Link>
                </Button>
                <div className="flex flex-wrap items-center gap-3">
                    <Heading
                        title={`${line.line_code} - ${line.name}`}
                        description={line.remarks ?? undefined}
                    />
                    <Badge
                        variant="outline"
                        className={statusClass(line.status)}
                    >
                        {line.status}
                    </Badge>
                </div>

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                        {info.map(([label, value]) => (
                            <div key={label} className="min-w-0">
                                <div className="text-xs text-muted-foreground">
                                    {label}
                                </div>
                                <div className="text-sm break-words">
                                    {value || '-'}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <EquipmentDialog
                    key={dialog.item?.id ?? 'new'}
                    open={dialog.open}
                    onOpenChange={(open) => setDialog((s) => ({ ...s, open }))}
                    lineId={line.id}
                    item={dialog.item}
                    options={options}
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle>{t('machines.equipment_title')}</CardTitle>
                        <Button
                            onClick={() =>
                                setDialog({ open: true, item: null })
                            }
                        >
                            {t('machines.add_equipment')}
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('machines.eq_code')}
                                    </TableHead>
                                    <TableHead>{t('machines.name')}</TableHead>
                                    <TableHead>
                                        {t('machines.eq_type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.manufacturer')}
                                    </TableHead>
                                    <TableHead>{t('machines.model')}</TableHead>
                                    <TableHead>
                                        {t('machines.year_made')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.manual_status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.columns.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {equipment.map((e) => (
                                    <TableRow key={e.id}>
                                        <TableCell className="font-mono">
                                            {e.equipment_code}
                                        </TableCell>
                                        <TableCell>{e.name}</TableCell>
                                        <TableCell>
                                            {e.equipment_type ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {e.manufacturer ?? '-'}
                                        </TableCell>
                                        <TableCell>{e.model ?? '-'}</TableCell>
                                        <TableCell>
                                            {e.year_made ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {e.manual_status ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setDialog({
                                                            open: true,
                                                            item: e,
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
                                                        'machines.delete_eq_title',
                                                    )}
                                                    description={t(
                                                        'machines.delete_eq_desc',
                                                        {
                                                            code: e.equipment_code,
                                                            name: e.name,
                                                        },
                                                    )}
                                                    confirmLabel={t(
                                                        'master_data.actions.delete',
                                                    )}
                                                    formProps={EquipmentController.destroy.form(
                                                        e.id,
                                                    )}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {equipment.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('machines.eq_empty')}
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

MachineLineShow.layout = {
    breadcrumbs: [{ title: 'Machines', href: machinesIndex() }],
};

export { machinesShow };
