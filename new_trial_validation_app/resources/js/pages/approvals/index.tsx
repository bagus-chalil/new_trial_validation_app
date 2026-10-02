import { Head, Link, router } from '@inertiajs/react';
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

type Filters = {
    q: string;
};

type PageProps = {
    items: Paginated<ApprovalItem>;
    filters: Filters;
};

export default function ApprovalsIndex({ items, filters }: PageProps) {
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

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ q: value })}
                    searchPlaceholder={t('trials.queue.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Boolean(filters.q)}
                />

                <Card>
                    <CardContent>
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
                                        {t('trials.approvals.approver')}
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
                                        <TableCell>
                                            {item.approver
                                                ? (item.approver.name ??
                                                  item.approver.email)
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
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
                                            colSpan={6}
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
