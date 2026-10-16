import { Head, Link, router } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { FilterBar } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { index as approvalsIndex } from '@/routes/approvals';
import type { Paginated } from '@/types';

type ApprovalItem = {
    id: number;
    trial_code: string;
    product_name: string;
    product_type: string;
    progress_status: string;
    final_decision: string | null;
    updated_at: string | null;
    approver: { id: number; name: string; email: string } | null;
};

type LineConfigItem = {
    trial_id: number;
    trial_code: string | null;
    product_name: string | null;
    product_type: string | null;
    stage: string;
};

type Filters = {
    q: string;
};

type PageProps = {
    items: Paginated<ApprovalItem>;
    lineConfigItems: LineConfigItem[];
    filters: Filters;
};

function SectionHeader({
    title,
    description,
    count,
}: {
    title: string;
    description: string;
    count: number;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 className="font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <Badge variant="secondary">
                {t('trials.approvals.summary', { count })}
            </Badge>
        </div>
    );
}

export default function ApprovalsIndex({
    items,
    lineConfigItems,
    filters,
}: PageProps) {
    const { t } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = approvalsIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        router.get(url);
    }

    return (
        <>
            <Head title={t('trials.approvals.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('trials.approvals.title')}
                    description={t('trials.approvals.description')}
                />

                {lineConfigItems.length > 0 && (
                    <Card className="border-amber-300/60 dark:border-amber-500/30">
                        <CardContent className="space-y-4">
                            <SectionHeader
                                title={t('trials.approvals.line_config_title')}
                                description={t(
                                    'trials.approvals.line_config_description',
                                )}
                                count={lineConfigItems.length}
                            />
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('trials.queue.trial')}
                                        </TableHead>
                                        <TableHead>
                                            {t('trials.queue.product')}
                                        </TableHead>
                                        <TableHead>
                                            {t(
                                                'trials.approvals.line_config_stage',
                                            )}
                                        </TableHead>
                                        <TableHead>
                                            <span className="sr-only">
                                                {t('trials.table.actions')}
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lineConfigItems.map((lc) => (
                                        <TableRow key={lc.trial_id}>
                                            <TableCell className="font-medium whitespace-nowrap">
                                                {lc.trial_code}
                                            </TableCell>
                                            <TableCell className="max-w-md truncate">
                                                {lc.product_name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-amber-400 bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"
                                                >
                                                    <Clock className="size-3" />
                                                    {t(
                                                        'trials.approvals.line_config_stage_badge',
                                                        { stage: lc.stage },
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button size="sm" asChild>
                                                    <Link
                                                        href={
                                                            TrialReportController.show(
                                                                lc.trial_id,
                                                            ).url
                                                        }
                                                    >
                                                        {t(
                                                            'trials.approvals.sign_action',
                                                        )}
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ q: value })}
                    searchPlaceholder={t('trials.queue.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Boolean(filters.q)}
                />

                <Card>
                    <CardContent className="space-y-4">
                        <SectionHeader
                            title={t('trials.approvals.final_title')}
                            description={t(
                                'trials.approvals.final_description',
                            )}
                            count={items.total}
                        />
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('trials.queue.trial')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.queue.product')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.queue.product_type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.queue.status')}
                                    </TableHead>
                                    <TableHead>
                                        <span className="sr-only">
                                            {t('trials.table.actions')}
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <Link
                                                href={
                                                    TrialReportController.show(
                                                        item.id,
                                                    ).url
                                                }
                                                className="font-medium underline"
                                            >
                                                {item.trial_code}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {item.product_name}
                                        </TableCell>
                                        <TableCell>
                                            {item.product_type}
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
                                        <TableCell className="text-right">
                                            <Button size="sm" asChild>
                                                <Link
                                                    href={
                                                        TrialReportController.show(
                                                            item.id,
                                                        ).url
                                                    }
                                                >
                                                    {t(
                                                        'trials.approvals.action',
                                                    )}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('trials.approvals.empty')}
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

ApprovalsIndex.layout = {
    breadcrumbs: [{ title: 'Need Approval', href: approvalsIndex() }],
};
