import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { ActiveFilterChip } from '@/components/filter-bar';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as monitoringIndex } from '@/routes/monitoring';
import { show as showReport } from '@/routes/trials/report';
import type { Paginated } from '@/types';

type Filters = { q: string; status: string; holder: string };

type Stage = {
    status: string;
    count: number;
    avg_days: number;
    oldest_days: number;
    stale: number;
};

type Holder = {
    name: string;
    kind: 'owner' | 'team' | 'approver';
    count: number;
    avg_days: number;
    oldest_days: number;
};

type MonitorRow = {
    id: number;
    trial_code: string;
    product_name: string;
    progress_status: string;
    final_decision: string | null;
    created_at: string;
    idle_days: number;
    holders: string[];
    owner_name: string | null;
    can_view: boolean;
};

type PageProps = {
    trials: Paginated<MonitorRow>;
    filters: Filters;
    stages: Stage[];
    holders: Holder[];
    thresholds: { warn: number; critical: number };
};

function idleClassName(days: number, warn: number, critical: number): string {
    if (days >= critical) {
        return 'border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300';
    }

    if (days >= warn) {
        return 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300';
    }

    return 'text-muted-foreground';
}

export default function MonitoringIndex({
    trials,
    filters,
    stages,
    holders,
    thresholds,
}: PageProps) {
    const { t, formatDate } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = monitoringIndex().url;

    function go(next: Filters) {
        router.get(url, next, { preserveState: true, replace: true });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        go(form);
    }

    function reset() {
        setForm({ q: '', status: '', holder: '' });
        router.get(url);
    }

    function setFilter(key: keyof Filters, value: string) {
        const next = { ...form, [key]: value };
        setForm(next);
        go(next);
    }

    const days = (count: number) => t('monitoring.days', { count });

    const activeChips: ActiveFilterChip[] = [
        filters.q && {
            key: 'q',
            label: `${t('monitoring.filters.search')}: ${filters.q}`,
            onClear: () => setFilter('q', ''),
        },
        filters.status && {
            key: 'status',
            label: `${t('monitoring.filters.status')}: ${trialStatusLabel(t, filters.status)}`,
            onClear: () => setFilter('status', ''),
        },
        filters.holder && {
            key: 'holder',
            label: `${t('monitoring.filters.holder')}: ${filters.holder}`,
            onClear: () => setFilter('holder', ''),
        },
    ].filter(Boolean) as ActiveFilterChip[];

    return (
        <>
            <Head title={t('monitoring.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('monitoring.title')}
                    description={t('monitoring.description')}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stages.map((stage) => (
                        <button
                            key={stage.status}
                            type="button"
                            onClick={() =>
                                setFilter(
                                    'status',
                                    filters.status === stage.status
                                        ? ''
                                        : stage.status,
                                )
                            }
                            className="text-left"
                        >
                            <Card
                                className={cn(
                                    'h-full transition-colors hover:border-foreground/30',
                                    filters.status === stage.status &&
                                        'border-brand',
                                )}
                            >
                                <CardHeader className="pb-2">
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'w-fit',
                                            trialStatusBadgeClassName(
                                                stage.status,
                                                null,
                                            ),
                                        )}
                                    >
                                        {trialStatusLabel(t, stage.status)}
                                    </Badge>
                                    <CardTitle className="text-3xl">
                                        {stage.count}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-0.5 text-xs text-muted-foreground">
                                    <p>
                                        {t('monitoring.stage_cards.avg', {
                                            days: stage.avg_days,
                                        })}
                                    </p>
                                    <p>
                                        {t('monitoring.stage_cards.oldest', {
                                            days: stage.oldest_days,
                                        })}
                                    </p>
                                    {stage.stale > 0 && (
                                        <p className="font-medium text-amber-700 dark:text-amber-300">
                                            {t('monitoring.stage_cards.stale', {
                                                count: stage.stale,
                                                days: thresholds.warn,
                                            })}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        </button>
                    ))}
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('monitoring.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={activeChips.length > 0}
                    activeChips={activeChips}
                >
                    <FilterSelect
                        label={t('monitoring.filters.status')}
                        value={form.status}
                        onChange={(value) =>
                            setForm({ ...form, status: value })
                        }
                        options={stages.map((stage) => ({
                            value: stage.status,
                            label: trialStatusLabel(t, stage.status),
                        }))}
                    />
                </FilterBar>

                <Card>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('monitoring.table.trial_code')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.table.product_name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.table.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.table.waiting_for')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.table.owner')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.table.idle')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.table.created_at')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {trials.data.map((trial) => (
                                    <TableRow
                                        key={trial.id}
                                        className="align-top"
                                    >
                                        <TableCell>
                                            {trial.can_view ? (
                                                <Link
                                                    href={
                                                        showReport(trial.id, {
                                                            query: {
                                                                from: 'overview',
                                                            },
                                                        }).url
                                                    }
                                                    className="font-medium underline underline-offset-2"
                                                >
                                                    {trial.trial_code}
                                                </Link>
                                            ) : (
                                                <span
                                                    className="font-medium"
                                                    title={t(
                                                        'monitoring.table.no_access',
                                                    )}
                                                >
                                                    {trial.trial_code}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {trial.product_name}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={trialStatusBadgeClassName(
                                                    trial.progress_status,
                                                    trial.final_decision,
                                                )}
                                            >
                                                {trialStatusLabel(
                                                    t,
                                                    trial.progress_status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {trial.holders.map((holder) => (
                                                    <Badge
                                                        key={holder}
                                                        variant="secondary"
                                                    >
                                                        {holder}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {trial.owner_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={idleClassName(
                                                    trial.idle_days,
                                                    thresholds.warn,
                                                    thresholds.critical,
                                                )}
                                            >
                                                {days(trial.idle_days)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(trial.created_at)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {trials.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('monitoring.table.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={filters}
                            currentPage={trials.current_page}
                            lastPage={trials.last_page}
                            total={trials.total}
                            itemLabel={t('monitoring.table.item_label')}
                        />

                        <p className="text-xs text-muted-foreground">
                            {t('monitoring.legend', {
                                warn: thresholds.warn,
                                critical: thresholds.critical,
                            })}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('monitoring.holders.title')}</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {t('monitoring.holders.subtitle')}
                        </p>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('monitoring.holders.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('monitoring.holders.kind')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('monitoring.holders.count')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('monitoring.holders.avg_days')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('monitoring.holders.oldest_days')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {holders.map((holder) => (
                                    <TableRow
                                        key={holder.name}
                                        onClick={() =>
                                            setFilter(
                                                'holder',
                                                filters.holder === holder.name
                                                    ? ''
                                                    : holder.name,
                                            )
                                        }
                                        className={cn(
                                            'cursor-pointer',
                                            filters.holder === holder.name &&
                                                'bg-muted',
                                        )}
                                    >
                                        <TableCell className="font-medium">
                                            {holder.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {t(
                                                `monitoring.holders.kinds.${holder.kind}`,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-semibold">
                                            {holder.count}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {holder.avg_days}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Badge
                                                variant="outline"
                                                className={idleClassName(
                                                    holder.oldest_days,
                                                    thresholds.warn,
                                                    thresholds.critical,
                                                )}
                                            >
                                                {holder.oldest_days}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {holders.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('monitoring.holders.empty')}
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

MonitoringIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'All Trials', href: '#' },
    ],
};
