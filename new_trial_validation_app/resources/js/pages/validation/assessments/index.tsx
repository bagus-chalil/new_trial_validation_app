import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ValidationAssessmentController from '@/actions/App/Http/Controllers/ValidationAssessmentController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FilterBar, FilterField, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MultiSelect } from '@/components/multi-select';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { journeyTypeLabel } from '@/lib/journey';
import { show as journeyShow } from '@/routes/journey';
import { index as assessmentsIndex } from '@/routes/validation/assessments';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    case_id: number;
    case_no: string | null;
    case_type: string | null;
    product_id: number | null;
    product_name: string;
    finish_good_code: string | null;
    receive_date: string | null;
    client: string;
    sales_month: string | null;
    qty_po: number | null;
    packaging_type: string;
    validation_scope: string[];
    recommended_machine: string;
    qco_no: string;
    qco_link: string;
    pm_received_date: string | null;
    pm_na: boolean;
    bulk_received_date: string | null;
    bulk_na: boolean;
    risk_level: string;
    notes: string | null;
    trial_count: number;
};

type Filters = {
    q: string;
    case_type: string;
    risk_level: string;
    client: string;
    packaging_type: string;
    sales_month: string;
    receive_from: string;
    receive_to: string;
};

type PageProps = {
    assessments: Paginated<Row>;
    filters: Filters;
    products: { id: number; product_name: string; finish_good_code: string }[];
    masterOptions: {
        product_type: string[];
        validation_scope: string[];
        machine_used: string[];
    };
    caseTypes: string[];
    riskLevels: string[];
    can: { create: boolean; delete: boolean };
    completeCase: {
        id: number;
        case_no: string;
        case_type: string;
        product_id: number | null;
        product_name: string;
    } | null;
};

function riskClassName(risk: string): string {
    switch (risk) {
        case 'High':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
        case 'Medium':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
        default:
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
    }
}

type CompleteCase = NonNullable<PageProps['completeCase']>;

function AssessmentDialog({
    editing,
    forCase,
    open,
    onOpenChange,
    products,
    masterOptions,
    caseTypes,
    riskLevels,
}: {
    editing: Row | null;
    forCase: CompleteCase | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    products: PageProps['products'];
    masterOptions: PageProps['masterOptions'];
    caseTypes: string[];
    riskLevels: string[];
}) {
    const { t } = useTranslation();
    const [productId, setProductId] = useState(
        editing?.product_id
            ? String(editing.product_id)
            : forCase?.product_id
              ? String(forCase.product_id)
              : '',
    );
    const [packaging, setPackaging] = useState(editing?.packaging_type ?? '');
    const [scope, setScope] = useState<string[]>(
        editing?.validation_scope ?? [],
    );
    const [pmNa, setPmNa] = useState(editing?.pm_na ?? false);
    const [bulkNa, setBulkNa] = useState(editing?.bulk_na ?? false);

    const formProps = editing
        ? ValidationAssessmentController.update.form(editing.id)
        : ValidationAssessmentController.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? t('assessment.edit')
                            : forCase
                              ? t('assessment.complete.title', {
                                    case: forCase.case_no,
                                })
                              : t('assessment.add')}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...formProps}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {forCase && (
                                <input
                                    type="hidden"
                                    name="case_id"
                                    value={forCase.id}
                                />
                            )}
                            <input
                                type="hidden"
                                name="product_id"
                                value={productId}
                            />
                            <input
                                type="hidden"
                                name="packaging_type"
                                value={packaging}
                            />
                            {scope.map((value) => (
                                <input
                                    key={value}
                                    type="hidden"
                                    name="validation_scope[]"
                                    value={value}
                                />
                            ))}
                            <input
                                type="hidden"
                                name="pm_na"
                                value={pmNa ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="bulk_na"
                                value={bulkNa ? '1' : '0'}
                            />

                            <div className="grid gap-4 sm:grid-cols-2">
                                {!editing && !forCase && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="case_type">
                                            {t('assessment.form.case_type')}
                                        </Label>
                                        <Select
                                            name="case_type"
                                            defaultValue={caseTypes[0]}
                                        >
                                            <SelectTrigger
                                                id="case_type"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {caseTypes.map((type) => (
                                                    <SelectItem
                                                        key={type}
                                                        value={type}
                                                    >
                                                        {journeyTypeLabel(
                                                            t,
                                                            type,
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'assessment.form.case_type_hint',
                                            )}
                                        </p>
                                        <InputError
                                            message={errors.case_type}
                                        />
                                    </div>
                                )}

                                <div className="grid min-w-0 gap-2">
                                    <Label>
                                        {t('assessment.form.product')}
                                    </Label>
                                    <Combobox
                                        options={[
                                            {
                                                value: '',
                                                label: t(
                                                    'assessment.form.product_none',
                                                ),
                                            },
                                            ...products.map((p) => ({
                                                value: String(p.id),
                                                label: `${p.product_name} (${p.finish_good_code})`,
                                            })),
                                        ]}
                                        value={productId}
                                        onChange={setProductId}
                                        aria-invalid={Boolean(
                                            errors.product_id,
                                        )}
                                    />
                                    <InputError message={errors.product_id} />
                                </div>

                                {productId === '' && (
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="product_name">
                                            {t('assessment.form.product_name')}
                                        </Label>
                                        <Input
                                            id="product_name"
                                            name="product_name"
                                            defaultValue={
                                                editing?.product_name ?? ''
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'assessment.form.product_name_hint',
                                            )}
                                        </p>
                                        <InputError
                                            message={errors.product_name}
                                        />
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="receive_date">
                                        {t('assessment.form.receive_date')}
                                    </Label>
                                    <Input
                                        id="receive_date"
                                        name="receive_date"
                                        type="date"
                                        defaultValue={
                                            editing?.receive_date ?? ''
                                        }
                                    />
                                    <InputError message={errors.receive_date} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="client">
                                        {t('assessment.form.client')}
                                    </Label>
                                    <Input
                                        id="client"
                                        name="client"
                                        defaultValue={editing?.client ?? ''}
                                    />
                                    <InputError message={errors.client} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="sales_month">
                                        {t('assessment.form.sales_month')}
                                    </Label>
                                    <Input
                                        id="sales_month"
                                        name="sales_month"
                                        type="month"
                                        defaultValue={
                                            editing?.sales_month ?? ''
                                        }
                                    />
                                    <InputError message={errors.sales_month} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="qty_po">
                                        {t('assessment.form.qty_po')}
                                    </Label>
                                    <Input
                                        id="qty_po"
                                        name="qty_po"
                                        type="number"
                                        min={0}
                                        defaultValue={editing?.qty_po ?? ''}
                                    />
                                    <InputError message={errors.qty_po} />
                                </div>

                                <div className="grid min-w-0 gap-2">
                                    <Label>
                                        {t('assessment.form.packaging_type')}
                                    </Label>
                                    <Combobox
                                        options={masterOptions.product_type.map(
                                            (name) => ({
                                                value: name,
                                                label: name,
                                            }),
                                        )}
                                        value={packaging}
                                        onChange={setPackaging}
                                        aria-invalid={Boolean(
                                            errors.packaging_type,
                                        )}
                                    />
                                    <InputError
                                        message={errors.packaging_type}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="risk_level">
                                        {t('assessment.form.risk_level')}
                                    </Label>
                                    <Select
                                        name="risk_level"
                                        defaultValue={
                                            editing?.risk_level ?? 'Medium'
                                        }
                                    >
                                        <SelectTrigger
                                            id="risk_level"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {riskLevels.map((level) => (
                                                <SelectItem
                                                    key={level}
                                                    value={level}
                                                >
                                                    {t(
                                                        `assessment.risk.${level}`,
                                                    )}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {t('assessment.form.risk_hint')}
                                    </p>
                                    <InputError message={errors.risk_level} />
                                </div>

                                <div className="grid min-w-0 gap-2 sm:col-span-2">
                                    <Label>
                                        {t('assessment.form.validation_scope')}
                                    </Label>
                                    <MultiSelect
                                        options={masterOptions.validation_scope.map(
                                            (name) => ({
                                                value: name,
                                                label: name,
                                            }),
                                        )}
                                        value={scope}
                                        onChange={setScope}
                                        aria-invalid={Boolean(
                                            errors.validation_scope,
                                        )}
                                    />
                                    <InputError
                                        message={errors.validation_scope}
                                    />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="recommended_machine">
                                        {t(
                                            'assessment.form.recommended_machine',
                                        )}
                                    </Label>
                                    <Input
                                        id="recommended_machine"
                                        name="recommended_machine"
                                        list="assessment-machines"
                                        defaultValue={
                                            editing?.recommended_machine ?? ''
                                        }
                                    />
                                    <datalist id="assessment-machines">
                                        {masterOptions.machine_used.map(
                                            (name) => (
                                                <option
                                                    key={name}
                                                    value={name}
                                                />
                                            ),
                                        )}
                                    </datalist>
                                    <p className="text-xs text-muted-foreground">
                                        {t(
                                            'assessment.form.recommended_machine_hint',
                                        )}
                                    </p>
                                    <InputError
                                        message={errors.recommended_machine}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="qco_no">
                                        {t('assessment.form.qco_no')}
                                    </Label>
                                    <Input
                                        id="qco_no"
                                        name="qco_no"
                                        defaultValue={editing?.qco_no ?? ''}
                                    />
                                    <InputError message={errors.qco_no} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="qco_link">
                                        {t('assessment.form.qco_link')}
                                    </Label>
                                    <Input
                                        id="qco_link"
                                        name="qco_link"
                                        type="url"
                                        defaultValue={editing?.qco_link ?? ''}
                                    />
                                    <InputError message={errors.qco_link} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="pm_received_date">
                                        {t('assessment.form.pm_received_date')}
                                    </Label>
                                    <Input
                                        id="pm_received_date"
                                        name="pm_received_date"
                                        type="date"
                                        disabled={pmNa}
                                        defaultValue={
                                            editing?.pm_received_date ?? ''
                                        }
                                    />
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={pmNa}
                                            onChange={(e) =>
                                                setPmNa(e.target.checked)
                                            }
                                        />
                                        {t('assessment.form.na')}
                                    </label>
                                    <InputError
                                        message={errors.pm_received_date}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="bulk_received_date">
                                        {t(
                                            'assessment.form.bulk_received_date',
                                        )}
                                    </Label>
                                    <Input
                                        id="bulk_received_date"
                                        name="bulk_received_date"
                                        type="date"
                                        disabled={bulkNa}
                                        defaultValue={
                                            editing?.bulk_received_date ?? ''
                                        }
                                    />
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={bulkNa}
                                            onChange={(e) =>
                                                setBulkNa(e.target.checked)
                                            }
                                        />
                                        {t('assessment.form.na')}
                                    </label>
                                    <InputError
                                        message={errors.bulk_received_date}
                                    />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">
                                        {t('assessment.form.notes')}
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        defaultValue={editing?.notes ?? ''}
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('assessment.form.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AssessmentsIndex({
    assessments,
    filters,
    products,
    masterOptions,
    caseTypes,
    riskLevels,
    can,
    completeCase,
}: PageProps) {
    const { t, formatDateOnly } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const [dialog, setDialog] = useState<{
        row: Row | null;
        forCase: CompleteCase | null;
    } | null>(
        can.create && completeCase
            ? { row: null, forCase: completeCase }
            : null,
    );
    const url = assessmentsIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    const na = t('assessment.na');
    const dateOrNa = (isNa: boolean, date: string | null) =>
        isNa ? na : date ? formatDateOnly(date) : '-';

    return (
        <>
            <Head title={t('assessment.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('assessment.title')}
                        description={t('assessment.description')}
                    />
                    {can.create && (
                        <Button
                            className="gap-1.5"
                            onClick={() =>
                                setDialog({ row: null, forCase: null })
                            }
                        >
                            <Plus className="size-4" />
                            {t('assessment.add')}
                        </Button>
                    )}
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('assessment.search_placeholder')}
                    onSubmit={submit}
                    onReset={() => router.get(url)}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('assessment.form.case_type')}
                        value={form.case_type}
                        onChange={(case_type) =>
                            setForm({ ...form, case_type })
                        }
                        placeholder={t('assessment.filters.all_types')}
                        options={caseTypes.map((type) => ({
                            value: type,
                            label: journeyTypeLabel(t, type),
                        }))}
                    />
                    <FilterSelect
                        label={t('assessment.form.risk_level')}
                        value={form.risk_level}
                        onChange={(risk_level) =>
                            setForm({ ...form, risk_level })
                        }
                        placeholder={t('assessment.filters.all_risks')}
                        options={riskLevels.map((level) => ({
                            value: level,
                            label: t(`assessment.risk.${level}`),
                        }))}
                    />
                    <FilterSelect
                        label={t('assessment.filters.packaging')}
                        value={form.packaging_type}
                        onChange={(packaging_type) =>
                            setForm({ ...form, packaging_type })
                        }
                        placeholder={t('assessment.filters.all_types')}
                        options={masterOptions.product_type.map((name) => ({
                            value: name,
                            label: name,
                        }))}
                    />
                    <FilterField label={t('assessment.filters.client')}>
                        <Input
                            value={form.client}
                            onChange={(e) =>
                                setForm({ ...form, client: e.target.value })
                            }
                        />
                    </FilterField>
                    <FilterField label={t('assessment.filters.sales_month')}>
                        <Input
                            type="month"
                            value={form.sales_month}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    sales_month: e.target.value,
                                })
                            }
                        />
                    </FilterField>
                    <FilterField label={t('assessment.filters.receive_from')}>
                        <Input
                            type="date"
                            value={form.receive_from}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    receive_from: e.target.value,
                                })
                            }
                        />
                    </FilterField>
                    <FilterField label={t('assessment.filters.receive_to')}>
                        <Input
                            type="date"
                            value={form.receive_to}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    receive_to: e.target.value,
                                })
                            }
                        />
                    </FilterField>
                </FilterBar>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('assessment.columns.case_no')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.product')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.client')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.receive_date')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.sales_month')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.qty_po')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.packaging')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.risk')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.pm')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.bulk')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.qco')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.trials')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.columns.actions')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assessments.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={
                                                    journeyShow(row.case_id).url
                                                }
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {row.case_no}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <div>{row.product_name}</div>
                                            {row.finish_good_code && (
                                                <div className="text-xs text-muted-foreground">
                                                    {row.finish_good_code}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>{row.client}</TableCell>
                                        <TableCell>
                                            {row.receive_date
                                                ? formatDateOnly(
                                                      row.receive_date,
                                                  )
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.sales_month ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.qty_po ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.packaging_type}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="secondary"
                                                className={riskClassName(
                                                    row.risk_level,
                                                )}
                                            >
                                                {t(
                                                    `assessment.risk.${row.risk_level}`,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {dateOrNa(
                                                row.pm_na,
                                                row.pm_received_date,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {dateOrNa(
                                                row.bulk_na,
                                                row.bulk_received_date,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {row.qco_link ? (
                                                <a
                                                    href={row.qco_link}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    {row.qco_no || 'QCO'}
                                                </a>
                                            ) : (
                                                row.qco_no || '-'
                                            )}
                                        </TableCell>
                                        <TableCell>{row.trial_count}</TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                {can.create && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t(
                                                            'assessment.edit',
                                                        )}
                                                        onClick={() =>
                                                            setDialog({
                                                                row,
                                                                forCase: null,
                                                            })
                                                        }
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>
                                                )}
                                                {can.delete && (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={t(
                                                                    'assessment.delete.confirm',
                                                                )}
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'assessment.delete.title',
                                                        )}
                                                        description={t(
                                                            'assessment.delete.description',
                                                        )}
                                                        confirmLabel={t(
                                                            'assessment.delete.confirm',
                                                        )}
                                                        formProps={ValidationAssessmentController.destroy.form(
                                                            row.id,
                                                        )}
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {assessments.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={13}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('assessment.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={{ ...filters }}
                            currentPage={assessments.current_page}
                            lastPage={assessments.last_page}
                            total={assessments.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>

            {dialog && (
                <AssessmentDialog
                    key={dialog.row?.id ?? 'new'}
                    editing={dialog.row}
                    forCase={dialog.forCase}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setDialog(null);
                        }
                    }}
                    products={products}
                    masterOptions={masterOptions}
                    caseTypes={caseTypes}
                    riskLevels={riskLevels}
                />
            )}
        </>
    );
}

AssessmentsIndex.layout = {
    breadcrumbs: [{ title: 'Validation Assessment', href: assessmentsIndex() }],
};
