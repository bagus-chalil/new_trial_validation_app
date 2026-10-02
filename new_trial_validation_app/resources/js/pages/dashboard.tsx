import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Building2,
    CheckCircle2,
    Clock,
    FileEdit,
    ListChecks,
    Percent,
    Search,
    XCircle,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { CategoryBarChart } from '@/components/dashboard/category-bar-chart';
import { KpiTile } from '@/components/dashboard/kpi-tile';
import { ProductTypePieChart } from '@/components/dashboard/product-type-pie-chart';
import { StatusDistributionChart } from '@/components/dashboard/status-distribution-chart';
import type { StatusDatum } from '@/components/dashboard/status-distribution-chart';
import { TrendChart } from '@/components/dashboard/trend-chart';
import type { TrendDatum } from '@/components/dashboard/trend-chart';
import type { ActiveFilterChip } from '@/components/filter-bar';
import { FilterBar, FilterField, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { TrialsTable } from '@/components/trials-table';
import type { TrialRow } from '@/components/trials-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { TRIAL_STATUSES, trialStatusLabel } from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { index as trialsIndex } from '@/routes/trials';
import type { Paginated } from '@/types';

type Summary = {
    total: number;
    total_mixing: number;
    total_filling: number;
    draft: number;
    in_review: number;
    ready: number;
    approved: number;
    need_revision: number;
    rejected: number;
};

type Filters = {
    q: string;
    product_type: string;
    validation_scope: string;
    date_from: string;
    date_to: string;
    status: string;
};

type Headline = {
    approvalRate: number | null;
    avgApprovalDays: number | null;
    activeTrials: number;
    bottleneckDepartment: { department: string; count: number } | null;
};

type Overview = {
    headline: Headline;
    trend: TrendDatum[];
    statusBreakdown: StatusDatum[];
    productTypeBreakdown: { label: string; count: number }[];
    productTypePie: { label: string; count: number }[];
    departmentPending: { department: string; count: number }[];
};

type PageProps = {
    trials: Paginated<TrialRow>;
    filters: Filters;
    productTypes: string[];
    validationScopes: string[];
    summary: Summary;
    overview: Overview;
};

const summaryCards: {
    key: keyof Summary;
    /** Stored trial status this card counts; null for the overall total. */
    status: string | null;
    href: () => string;
    icon: typeof ListChecks;
}[] = [
    {
        key: 'total',
        status: null,
        href: () => dashboard().url,
        icon: ListChecks,
    },
    {
        key: 'draft',
        status: 'Draft',
        href: () => dashboard({ query: { status: 'Draft' } }).url,
        icon: FileEdit,
    },
    {
        key: 'in_review',
        status: 'In Review',
        href: () =>
            trialsIndex('tracking', { query: { status: 'In Review' } }).url,
        icon: Search,
    },
    {
        key: 'ready',
        status: 'Ready for Approval',
        href: () =>
            trialsIndex('tracking', { query: { status: 'Ready for Approval' } })
                .url,
        icon: Clock,
    },
    {
        key: 'approved',
        status: 'Approved',
        href: () => trialsIndex('approved').url,
        icon: CheckCircle2,
    },
    {
        key: 'need_revision',
        status: 'Need Revision',
        href: () => trialsIndex('need-revision').url,
        icon: AlertTriangle,
    },
    {
        key: 'rejected',
        status: 'Rejected',
        href: () => trialsIndex('rejected').url,
        icon: XCircle,
    },
];

export default function Dashboard({
    trials,
    filters,
    productTypes,
    validationScopes,
    summary,
    overview,
}: PageProps) {
    const { t, intlLocale } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(dashboard().url, form, {
            preserveState: true,
            replace: true,
        });
    }

    function reset() {
        router.get(dashboard().url);
    }

    function clearFilter(key: keyof Filters) {
        const next = { ...form, [key]: '' };
        setForm(next);
        router.get(dashboard().url, next, {
            preserveState: true,
            replace: true,
        });
    }

    const chip = (label: string, value: string) =>
        t('dashboard.filters.chip', { label, value });
    // Trial::productTypeBreakdown() buckets the tail into a fixed 'Lainnya'
    // label; translate it for display only.
    const otherBucket = (label: string) =>
        label === 'Lainnya' ? t('dashboard.charts.other') : label;
    const headline = overview.headline;

    const hasActiveFilters = Object.values(filters).some(Boolean);
    const activeChips: ActiveFilterChip[] = [
        filters.q && {
            key: 'q',
            label: chip(t('dashboard.filters.search'), filters.q),
            onClear: () => clearFilter('q'),
        },
        filters.status && {
            key: 'status',
            label: chip(
                t('dashboard.filters.status'),
                trialStatusLabel(t, filters.status),
            ),
            onClear: () => clearFilter('status'),
        },
        filters.product_type && {
            key: 'product_type',
            label: chip(
                t('dashboard.filters.product_type'),
                filters.product_type,
            ),
            onClear: () => clearFilter('product_type'),
        },
        filters.validation_scope && {
            key: 'validation_scope',
            label: chip(
                t('dashboard.filters.trial_type'),
                filters.validation_scope,
            ),
            onClear: () => clearFilter('validation_scope'),
        },
        filters.date_from && {
            key: 'date_from',
            label: chip(t('dashboard.filters.date_from'), filters.date_from),
            onClear: () => clearFilter('date_from'),
        },
        filters.date_to && {
            key: 'date_to',
            label: chip(t('dashboard.filters.date_to'), filters.date_to),
            onClear: () => clearFilter('date_to'),
        },
    ].filter(Boolean) as ActiveFilterChip[];

    return (
        <>
            <Head title={t('common.nav.dashboard')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('dashboard.title')}
                    description={t('dashboard.description')}
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        label={t('dashboard.kpi.approval_rate')}
                        value={
                            headline.approvalRate === null
                                ? t('dashboard.kpi.no_data')
                                : `${headline.approvalRate.toLocaleString(intlLocale)}%`
                        }
                        caption={t('dashboard.kpi.approval_rate_caption')}
                        icon={Percent}
                        accent
                    />
                    <KpiTile
                        label={t('dashboard.kpi.avg_approval_time')}
                        value={
                            headline.avgApprovalDays === null
                                ? t('dashboard.kpi.no_data')
                                : t('dashboard.kpi.days', {
                                      count: headline.avgApprovalDays.toLocaleString(
                                          intlLocale,
                                      ),
                                  })
                        }
                        caption={t('dashboard.kpi.avg_approval_time_caption')}
                        icon={Clock}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.active_trials')}
                        value={headline.activeTrials}
                        caption={t('dashboard.kpi.active_trials_caption')}
                        icon={Activity}
                    />
                    <KpiTile
                        label={t('dashboard.kpi.bottleneck')}
                        value={
                            headline.bottleneckDepartment?.department ??
                            t('dashboard.kpi.bottleneck_none')
                        }
                        caption={
                            headline.bottleneckDepartment
                                ? t('dashboard.kpi.bottleneck_caption', {
                                      count: headline.bottleneckDepartment
                                          .count,
                                  })
                                : t('dashboard.kpi.bottleneck_none_caption')
                        }
                        icon={Building2}
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {summaryCards.map((card) => (
                        <a key={card.key} href={card.href()} className="block">
                            <Card
                                className={
                                    'h-full gap-2 py-4 transition-colors hover:bg-muted' +
                                    (card.key === 'total'
                                        ? ' border-l-4 border-l-brand'
                                        : '')
                                }
                            >
                                <CardContent className="flex h-full items-center justify-between px-4">
                                    <div>
                                        <span className="text-sm text-muted-foreground">
                                            {card.status === null
                                                ? t('dashboard.summary.total')
                                                : trialStatusLabel(
                                                      t,
                                                      card.status,
                                                  )}
                                        </span>
                                        <div className="text-2xl font-semibold">
                                            {summary[card.key]}
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {card.key === 'total'
                                                ? t(
                                                      'dashboard.summary.total_breakdown',
                                                      {
                                                          mixing: summary.total_mixing,
                                                          filling:
                                                              summary.total_filling,
                                                      },
                                                  )
                                                : ' '}
                                        </div>
                                    </div>
                                    <card.icon className="size-5 shrink-0 text-muted-foreground" />
                                </CardContent>
                            </Card>
                        </a>
                    ))}
                </section>

                <section className="grid items-stretch gap-4 lg:grid-cols-2">
                    <Card className="flex h-full flex-col lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.charts.trend_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-1 items-center">
                            <TrendChart data={overview.trend} />
                        </CardContent>
                    </Card>

                    <Card className="flex h-full flex-col">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.charts.status_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-1 items-center">
                            <StatusDistributionChart
                                data={overview.statusBreakdown}
                            />
                        </CardContent>
                    </Card>

                    <Card className="flex h-full flex-col">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.charts.product_type_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-1 items-center">
                            <CategoryBarChart
                                data={overview.productTypeBreakdown.map(
                                    (row) => ({
                                        label: otherBucket(row.label),
                                        count: row.count,
                                    }),
                                )}
                                emptyMessage={t(
                                    'dashboard.charts.empty_trials',
                                )}
                            />
                        </CardContent>
                    </Card>

                    <Card className="flex h-full flex-col">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.charts.product_type_share_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-1 items-center">
                            <ProductTypePieChart
                                data={overview.productTypePie}
                                emptyMessage={t(
                                    'dashboard.charts.empty_trials',
                                )}
                            />
                        </CardContent>
                    </Card>

                    <Card className="flex h-full flex-col">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('dashboard.charts.department_pending_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-1 items-center">
                            <CategoryBarChart
                                data={overview.departmentPending.map((row) => ({
                                    label: row.department,
                                    count: row.count,
                                }))}
                                emptyMessage={t(
                                    'dashboard.charts.empty_pending_reviews',
                                )}
                            />
                        </CardContent>
                    </Card>
                </section>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t(
                        'dashboard.filters.search_placeholder',
                    )}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={hasActiveFilters}
                    activeChips={activeChips}
                >
                    <FilterSelect
                        label={t('dashboard.filters.status')}
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
                        label={t('dashboard.filters.product_type')}
                        value={form.product_type}
                        onChange={(value) =>
                            setForm({ ...form, product_type: value })
                        }
                        options={productTypes}
                    />
                    <FilterSelect
                        label={t('dashboard.filters.trial_type')}
                        value={form.validation_scope}
                        onChange={(value) =>
                            setForm({ ...form, validation_scope: value })
                        }
                        options={validationScopes}
                        placeholder={t(
                            'dashboard.filters.trial_type_placeholder',
                        )}
                    />
                    <FilterField label={t('dashboard.filters.date_from')}>
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
                    <FilterField label={t('dashboard.filters.date_to')}>
                        <Input
                            type="date"
                            value={form.date_to}
                            onChange={(e) =>
                                setForm({ ...form, date_to: e.target.value })
                            }
                        />
                    </FilterField>
                </FilterBar>

                <TrialsTable
                    trials={trials}
                    url={dashboard().url}
                    query={filters}
                    emptyMessage={t('dashboard.filters.empty')}
                />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
