import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ValidationPlanController from '@/actions/App/Http/Controllers/ValidationPlanController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FilterBar, FilterField, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { show as journeyShow } from '@/routes/journey';
import { index as plansIndex } from '@/routes/validation/plans';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    case_id: number;
    case_no: string | null;
    product_name: string;
    week_start: string;
    status: string;
    notes: string | null;
    ppic_note: string | null;
    requested_by_name: string;
    confirmed_by_name: string;
    confirmed_at: string | null;
    can_confirm: boolean;
    can_cancel: boolean;
};

type Filters = { q: string; status: string; week: string };

type PageProps = {
    plans: Paginated<Row>;
    filters: Filters;
    statuses: string[];
    cases: { id: number; label: string }[];
    can: { create: boolean };
};

function statusClassName(status: string): string {
    switch (status) {
        case 'Confirmed':
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
        case 'Cancelled':
            return 'bg-muted text-muted-foreground';
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
    }
}

function RequestDialog({
    cases,
    open,
    onOpenChange,
}: {
    cases: PageProps['cases'];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [caseId, setCaseId] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('assessment.plan.add')}</DialogTitle>
                </DialogHeader>
                <Form
                    {...ValidationPlanController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="case_id"
                                value={caseId}
                            />
                            <div className="grid min-w-0 gap-2">
                                <Label>{t('assessment.plan.case')}</Label>
                                <Combobox
                                    options={cases.map((c) => ({
                                        value: String(c.id),
                                        label: c.label,
                                    }))}
                                    value={caseId}
                                    onChange={setCaseId}
                                    aria-invalid={Boolean(errors.case_id)}
                                />
                                <InputError message={errors.case_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="week">
                                    {t('assessment.plan.week')}
                                </Label>
                                <Input id="week" name="week" type="date" />
                                <InputError message={errors.week} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notes">
                                    {t('assessment.plan.notes')}
                                </Label>
                                <Textarea id="notes" name="notes" />
                                <InputError message={errors.notes} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('assessment.plan.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function PlansIndex({
    plans,
    filters,
    statuses,
    cases,
    can,
}: PageProps) {
    const { t, formatDateOnly } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const [requesting, setRequesting] = useState(false);
    const url = plansIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={t('assessment.plan.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('assessment.plan.title')}
                        description={t('assessment.plan.description')}
                    />
                    {can.create && (
                        <Button
                            className="gap-1.5"
                            onClick={() => setRequesting(true)}
                        >
                            <Plus className="size-4" />
                            {t('assessment.plan.add')}
                        </Button>
                    )}
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('assessment.plan.search_placeholder')}
                    onSubmit={submit}
                    onReset={() => router.get(url)}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('assessment.plan.columns.status')}
                        value={form.status}
                        onChange={(status) => setForm({ ...form, status })}
                        placeholder={t('assessment.plan.all_status')}
                        options={statuses.map((status) => ({
                            value: status,
                            label: t(`assessment.plan.status.${status}`),
                        }))}
                    />
                    <FilterField label={t('assessment.plan.columns.week')}>
                        <Input
                            type="date"
                            value={form.week}
                            onChange={(e) =>
                                setForm({ ...form, week: e.target.value })
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
                                        {t('assessment.plan.columns.week')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.plan.columns.case')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.plan.columns.product')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.plan.columns.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'assessment.plan.columns.requested_by',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'assessment.plan.columns.confirmed_by',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.plan.columns.notes')}
                                    </TableHead>
                                    <TableHead>
                                        {t('assessment.plan.columns.actions')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {plans.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            {t('assessment.journey.week_of', {
                                                date: formatDateOnly(
                                                    row.week_start,
                                                ),
                                            })}
                                        </TableCell>
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
                                            {row.product_name}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="secondary"
                                                className={statusClassName(
                                                    row.status,
                                                )}
                                            >
                                                {t(
                                                    `assessment.plan.status.${row.status}`,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {row.requested_by_name}
                                        </TableCell>
                                        <TableCell>
                                            {row.confirmed_by_name || '-'}
                                            {row.confirmed_at &&
                                                ` · ${formatDateOnly(row.confirmed_at)}`}
                                        </TableCell>
                                        <TableCell>
                                            {[row.notes, row.ppic_note]
                                                .filter(Boolean)
                                                .join(' / ') || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-2">
                                                {row.can_confirm && (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                {t(
                                                                    'assessment.plan.confirm',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'assessment.plan.confirm_title',
                                                        )}
                                                        description={`${row.case_no} · ${row.product_name}`}
                                                        confirmLabel={t(
                                                            'assessment.plan.confirm',
                                                        )}
                                                        confirmVariant="default"
                                                        formProps={ValidationPlanController.confirm.form(
                                                            row.id,
                                                        )}
                                                    >
                                                        {({ errors }) => (
                                                            <div className="grid gap-2">
                                                                <Label
                                                                    htmlFor={`ppic_note_${row.id}`}
                                                                >
                                                                    {t(
                                                                        'assessment.plan.ppic_note',
                                                                    )}
                                                                </Label>
                                                                <Textarea
                                                                    id={`ppic_note_${row.id}`}
                                                                    name="ppic_note"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.ppic_note
                                                                    }
                                                                />
                                                            </div>
                                                        )}
                                                    </ConfirmDialog>
                                                )}
                                                {row.can_cancel && (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                {t(
                                                                    'assessment.plan.cancel',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'assessment.plan.cancel_title',
                                                        )}
                                                        description={`${row.case_no} · ${row.product_name}`}
                                                        confirmLabel={t(
                                                            'assessment.plan.cancel',
                                                        )}
                                                        formProps={ValidationPlanController.cancel.form(
                                                            row.id,
                                                        )}
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {plans.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('assessment.plan.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={{ ...filters }}
                            currentPage={plans.current_page}
                            lastPage={plans.last_page}
                            total={plans.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>

            {requesting && (
                <RequestDialog
                    cases={cases}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setRequesting(false);
                        }
                    }}
                />
            )}
        </>
    );
}

PlansIndex.layout = {
    breadcrumbs: [{ title: 'Validation Plan', href: plansIndex() }],
};
