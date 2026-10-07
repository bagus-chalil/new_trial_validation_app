import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { ActiveFilterChip } from '@/components/filter-bar';
import { FilterBar, FilterField, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { translate, useTranslation } from '@/hooks/use-translation';
import {
    TRIAL_STATUSES,
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { index as reportsIndex, trialSummary } from '@/routes/reports';
import { pdf as trialSummaryPdf } from '@/routes/reports/trial-summary';
import { show as reportShow } from '@/routes/trials/report';
import type { Paginated } from '@/types';

type SummaryItem = {
    id: number;
    trial_code: string;
    product_name: string;
    finish_good_code: string;
    product_type: string;
    validation_scope: string[] | null;
    machine_used: string[] | null;
    progress_status: string;
    final_decision: string | null;
    current_step: string | null;
    created_by: string | null;
    created_at: string | null;
    pending_with: string | null;
};

type Filters = {
    date_from: string;
    date_to: string;
    status: string;
    product_type: string;
    validation_scope: string;
    machine_used: string;
    product_name: string;
};

// Stored current_step values (shared with the legacy app) -> display label
// key. An unknown value is shown as-is.
const CURRENT_STEP_KEYS: Record<string, string> = {
    Header: 'header',
    Validation: 'validation',
    WeighingPackaging: 'weighing_packaging',
    WeighingFilling: 'weighing_filling',
    Attachment: 'attachment',
    Review: 'review',
    Approval: 'approval',
};

type PageProps = {
    items: Paginated<SummaryItem>;
    filters: Filters;
    productTypes: string[];
    validationScopes: string[];
    machines: string[];
};

export default function ReportsTrialSummary({
    items,
    filters,
    productTypes,
    validationScopes,
    machines,
}: PageProps) {
    const { t, formatDate } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = trialSummary().url;

    function currentStepLabel(step: string): string {
        const key = CURRENT_STEP_KEYS[step];

        return key ? t(`reports.trial_summary.steps.${key}`) : step;
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        router.get(url);
    }

    function clearFilter(key: keyof Filters) {
        const next = { ...form, [key]: '' };
        setForm(next);
        router.get(url, next, { preserveState: true, replace: true });
    }

    const labels = {
        status: t('report.header.status'),
        productType: t('report.info.product_type'),
        validationScope: t('report.info.validation_scope'),
        machineUsed: t('report.info.machine_used'),
        productName: t('report.info.product_name'),
    };

    const hasActiveFilters = Object.values(filters).some(Boolean);
    const activeChips: ActiveFilterChip[] = [
        filters.date_from && {
            key: 'date_from',
            label: `${t('trials.list.filters.chip_from')}: ${filters.date_from}`,
            onClear: () => clearFilter('date_from'),
        },
        filters.date_to && {
            key: 'date_to',
            label: `${t('trials.list.filters.chip_to')}: ${filters.date_to}`,
            onClear: () => clearFilter('date_to'),
        },
        filters.status && {
            key: 'status',
            label: `${labels.status}: ${trialStatusLabel(t, filters.status)}`,
            onClear: () => clearFilter('status'),
        },
        filters.product_type && {
            key: 'product_type',
            label: `${labels.productType}: ${filters.product_type}`,
            onClear: () => clearFilter('product_type'),
        },
        filters.validation_scope && {
            key: 'validation_scope',
            label: `${labels.validationScope}: ${filters.validation_scope}`,
            onClear: () => clearFilter('validation_scope'),
        },
        filters.machine_used && {
            key: 'machine_used',
            label: `${labels.machineUsed}: ${filters.machine_used}`,
            onClear: () => clearFilter('machine_used'),
        },
        filters.product_name && {
            key: 'product_name',
            label: `${labels.productName}: ${filters.product_name}`,
            onClear: () => clearFilter('product_name'),
        },
    ].filter(Boolean) as ActiveFilterChip[];

    return (
        <>
            <Head title={t('reports.trial_summary.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        title={t('reports.trial_summary.title')}
                        description={t('reports.trial_summary.description')}
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={trialSummaryPdf({ query: filters }).url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t('report.pdf.button')}
                        </a>
                    </Button>
                </div>

                <FilterBar
                    className="print:hidden"
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={hasActiveFilters}
                    activeChips={activeChips}
                >
                    <FilterField label={t('trials.list.filters.date_from')}>
                        <Input
                            type="date"
                            value={form.date_from}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    date_from: e.target.value,
                                })
                            }
                        />
                    </FilterField>
                    <FilterField label={t('trials.list.filters.date_to')}>
                        <Input
                            type="date"
                            value={form.date_to}
                            onChange={(e) =>
                                setForm({ ...form, date_to: e.target.value })
                            }
                        />
                    </FilterField>
                    <FilterSelect
                        label={labels.status}
                        value={form.status}
                        onChange={(value) =>
                            setForm({ ...form, status: value })
                        }
                        options={TRIAL_STATUSES.map((status) => ({
                            value: status,
                            label: trialStatusLabel(t, status),
                        }))}
                    />
                    <FilterSelect
                        label={labels.productType}
                        value={form.product_type}
                        onChange={(value) =>
                            setForm({ ...form, product_type: value })
                        }
                        options={productTypes}
                        placeholder={t(
                            'reports.trial_summary.all_product_types',
                        )}
                    />
                    <FilterSelect
                        label={labels.validationScope}
                        value={form.validation_scope}
                        onChange={(value) =>
                            setForm({ ...form, validation_scope: value })
                        }
                        options={validationScopes}
                        placeholder={t('reports.trial_summary.all_scopes')}
                    />
                    <FilterSelect
                        label={labels.machineUsed}
                        value={form.machine_used}
                        onChange={(value) =>
                            setForm({ ...form, machine_used: value })
                        }
                        options={machines}
                        placeholder={t('reports.trial_summary.all_machines')}
                    />
                    <FilterField label={labels.productName}>
                        <Input
                            placeholder={t(
                                'reports.trial_summary.product_name_placeholder',
                            )}
                            value={form.product_name}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    product_name: e.target.value,
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
                                        {t('report.info.trial_id')}
                                    </TableHead>
                                    <TableHead>{labels.productName}</TableHead>
                                    <TableHead>
                                        {t('report.info.fg_code')}
                                    </TableHead>
                                    <TableHead>{labels.productType}</TableHead>
                                    <TableHead>
                                        {labels.validationScope}
                                    </TableHead>
                                    <TableHead>{labels.machineUsed}</TableHead>
                                    <TableHead>{labels.status}</TableHead>
                                    <TableHead>
                                        {t(
                                            'reports.trial_summary.current_step',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t('report.info.created_by')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.table.created_at')}
                                    </TableHead>
                                    <TableHead>
                                        {t('report.header.pending_with')}
                                    </TableHead>
                                    <TableHead className="print:hidden">
                                        {t('trials.table.actions')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>{item.trial_code}</TableCell>
                                        <TableCell>
                                            {item.product_name}
                                        </TableCell>
                                        <TableCell>
                                            {item.finish_good_code}
                                        </TableCell>
                                        <TableCell>
                                            {item.product_type}
                                        </TableCell>
                                        <TableCell>
                                            {(item.validation_scope ?? []).join(
                                                ', ',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {(item.machine_used ?? []).join(
                                                ', ',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={trialStatusBadgeClassName(
                                                    item.progress_status,
                                                    item.final_decision,
                                                )}
                                            >
                                                {trialStatusLabel(
                                                    t,
                                                    item.progress_status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {item.current_step
                                                ? currentStepLabel(
                                                      item.current_step,
                                                  )
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.created_by ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(item.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            {item.pending_with ?? '-'}
                                        </TableCell>
                                        <TableCell className="print:hidden">
                                            <Button
                                                variant="link"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        reportShow(item.id).url
                                                    }
                                                >
                                                    {t(
                                                        'reports.actions.view_summary',
                                                    )}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={12}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('reports.trial_summary.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={filters}
                            currentPage={items.current_page}
                            lastPage={items.last_page}
                            total={items.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

// Layout callback so the last breadcrumb is translated; see approved.tsx.
ReportsTrialSummary.layout = (props: {
    translations: Record<string, string>;
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: reportsIndex() },
        {
            title: translate(props.translations, 'reports.trial_summary.title'),
            href: trialSummary(),
        },
    ],
});
