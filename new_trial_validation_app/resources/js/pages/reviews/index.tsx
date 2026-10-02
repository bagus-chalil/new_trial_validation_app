import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { FilterBar } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
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
import { index as reviewsIndex } from '@/routes/reviews';
import type { Paginated } from '@/types';

type ReviewItem = {
    id: number;
    trial_id: number;
    trial_code: string;
    product_name: string;
    review_round: number;
    status: string;
    reviewer_name: string | null;
    comment: string | null;
    active: boolean;
};

type Filters = {
    q: string;
};

type PageProps = {
    items: Paginated<ReviewItem>;
    filters: Filters;
};

export default function ReviewsIndex({ items, filters }: PageProps) {
    const { t } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = reviewsIndex().url;

    // trials_review.status is a stored value shared with the legacy app;
    // only its displayed label is translated.
    function reviewStatusLabel(status: string): string {
        const key = status.toLowerCase();

        return key === 'pending' || key === 'reviewed'
            ? t(`trials.reviews.status.${key}`)
            : status;
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        router.get(url);
    }

    return (
        <>
            <Head title={t('trials.reviews.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('trials.reviews.title')}
                    description={t('trials.reviews.description')}
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
                                        {t('trials.reviews.round')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.queue.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.reviews.reviewer')}
                                    </TableHead>
                                    <TableHead>
                                        {t('trials.reviews.comment')}
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
                                                        item.trial_id,
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
                                            {item.review_round}
                                        </TableCell>
                                        <TableCell>
                                            {reviewStatusLabel(item.status)}
                                        </TableCell>
                                        <TableCell>
                                            {item.reviewer_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.comment ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.active && (
                                                <Button size="sm" asChild>
                                                    <Link
                                                        href={
                                                            TrialReportController.show(
                                                                item.trial_id,
                                                            ).url
                                                        }
                                                    >
                                                        {t(
                                                            'trials.reviews.action',
                                                        )}
                                                    </Link>
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('trials.reviews.empty')}
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
                            itemLabel={t('trials.reviews.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReviewsIndex.layout = {
    breadcrumbs: [{ title: 'Need Review', href: reviewsIndex() }],
};
