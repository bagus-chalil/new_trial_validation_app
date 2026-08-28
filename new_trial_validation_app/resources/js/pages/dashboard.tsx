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
import { TRIAL_STATUSES } from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { index as trialsIndex } from '@/routes/trials';
import type { Paginated } from '@/types';

type Summary = {
    total: number;
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
    departmentPending: { department: string; count: number }[];
};

type PageProps = {
    trials: Paginated<TrialRow>;
    filters: Filters;
    productTypes: string[];
    summary: Summary;
    overview: Overview;
};

const summaryCards: {
    key: keyof Summary;
    label: string;
    href: () => string;
    icon: typeof ListChecks;
}[] = [
    {
        key: 'total',
        label: 'Total Trials',
        href: () => dashboard().url,
        icon: ListChecks,
    },
    {
        key: 'draft',
        label: 'Draft',
        href: () => dashboard({ query: { status: 'Draft' } }).url,
        icon: FileEdit,
    },
    {
        key: 'in_review',
        label: 'In Review',
        href: () => trialsIndex('in-review').url,
        icon: Search,
    },
    {
        key: 'ready',
        label: 'Ready for Approval',
        href: () => trialsIndex('waiting-approval').url,
        icon: Clock,
    },
    {
        key: 'approved',
        label: 'Approved',
        href: () => trialsIndex('approved').url,
        icon: CheckCircle2,
    },
    {
        key: 'need_revision',
        label: 'Need Revision',
        href: () => trialsIndex('need-revision').url,
        icon: AlertTriangle,
    },
    {
        key: 'rejected',
        label: 'Rejected',
        href: () => trialsIndex('rejected').url,
        icon: XCircle,
    },
];

export default function Dashboard({
    trials,
    filters,
    productTypes,
    summary,
    overview,
}: PageProps) {
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

    const hasActiveFilters = Object.values(filters).some(Boolean);
    const activeChips: ActiveFilterChip[] = [
        filters.q && {
            key: 'q',
            label: `Search: ${filters.q}`,
            onClear: () => clearFilter('q'),
        },
        filters.status && {
            key: 'status',
            label: `Status: ${filters.status}`,
            onClear: () => clearFilter('status'),
        },
        filters.product_type && {
            key: 'product_type',
            label: `Product Type: ${filters.product_type}`,
            onClear: () => clearFilter('product_type'),
        },
        filters.date_from && {
            key: 'date_from',
            label: `Dari: ${filters.date_from}`,
            onClear: () => clearFilter('date_from'),
        },
        filters.date_to && {
            key: 'date_to',
            label: `Sampai: ${filters.date_to}`,
            onClear: () => clearFilter('date_to'),
        },
    ].filter(Boolean) as ActiveFilterChip[];

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Trial Dashboard"
                    description="Overview sistem trial validation — kesehatan proses, tren, dan breakdown."
                />

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        label="Approval Rate"
                        value={
                            overview.headline.approvalRate === null
                                ? 'Belum ada'
                                : `${overview.headline.approvalRate}%`
                        }
                        caption="dari trial yang sudah diputuskan"
                        icon={Percent}
                        accent
                    />
                    <KpiTile
                        label="Rata-rata Waktu Approval"
                        value={
                            overview.headline.avgApprovalDays === null
                                ? 'Belum ada'
                                : `${overview.headline.avgApprovalDays} hari`
                        }
                        caption="dari trial dibuat sampai disetujui"
                        icon={Clock}
                    />
                    <KpiTile
                        label="Trial Aktif"
                        value={overview.headline.activeTrials}
                        caption="trial yang sedang berjalan"
                        icon={Activity}
                    />
                    <KpiTile
                        label="Departemen Bottleneck"
                        value={
                            overview.headline.bottleneckDepartment
                                ?.department ?? 'Tidak ada'
                        }
                        caption={
                            overview.headline.bottleneckDepartment
                                ? `${overview.headline.bottleneckDepartment.count} review pending`
                                : 'Semua review sudah selesai'
                        }
                        icon={Building2}
                    />
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {summaryCards.map((card) => (
                        <a key={card.key} href={card.href()}>
                            <Card
                                className={
                                    'gap-2 py-4 transition-colors hover:bg-muted' +
                                    (card.key === 'total'
                                        ? ' border-l-4 border-l-brand'
                                        : '')
                                }
                            >
                                <CardContent className="flex items-center justify-between px-4">
                                    <div>
                                        <span className="text-sm text-muted-foreground">
                                            {card.label}
                                        </span>
                                        <div className="text-2xl font-semibold">
                                            {summary[card.key]}
                                        </div>
                                    </div>
                                    <card.icon className="size-5 text-muted-foreground" />
                                </CardContent>
                            </Card>
                        </a>
                    ))}
                </section>

                <section className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Tren Trial Dibuat
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TrendChart data={overview.trend} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Distribusi Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <StatusDistributionChart
                                data={overview.statusBreakdown}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Breakdown per Product Type
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CategoryBarChart
                                data={overview.productTypeBreakdown.map(
                                    (row) => ({
                                        label: row.label,
                                        count: row.count,
                                    }),
                                )}
                                emptyMessage="Belum ada trial."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Review Pending per Departemen
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CategoryBarChart
                                data={overview.departmentPending.map((row) => ({
                                    label: row.department,
                                    count: row.count,
                                }))}
                                emptyMessage="Tidak ada review yang pending."
                            />
                        </CardContent>
                    </Card>
                </section>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) =>
                        setForm({ ...form, q: value })
                    }
                    searchPlaceholder="Trial, product, FG code, category, scope, machine"
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={hasActiveFilters}
                    activeChips={activeChips}
                >
                    <FilterSelect
                        label="Status"
                        value={form.status}
                        onChange={(value) =>
                            setForm({ ...form, status: value })
                        }
                        options={[...TRIAL_STATUSES]}
                    />
                    <FilterSelect
                        label="Product Type"
                        value={form.product_type}
                        onChange={(value) =>
                            setForm({ ...form, product_type: value })
                        }
                        options={productTypes}
                    />
                    <FilterField label="Tanggal Dari">
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
                    <FilterField label="Tanggal Sampai">
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
                    emptyMessage="Tidak ada trial untuk filter ini."
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
