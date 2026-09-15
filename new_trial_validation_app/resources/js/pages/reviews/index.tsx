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
    const [form, setForm] = useState<Filters>(filters);
    const url = reviewsIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        router.get(url);
    }

    return (
        <>
            <Head title="Need Review" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Need Review"
                    description="Trial yang perlu direview oleh department Anda — semua departemen yang terlibat bisa melakukan aksi review di sini."
                />

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ q: value })}
                    searchPlaceholder="Cari trial atau product"
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Boolean(filters.q)}
                />

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Trial</TableHead>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Round</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Reviewer</TableHead>
                                    <TableHead>Comment</TableHead>
                                    <TableHead></TableHead>
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
                                        <TableCell>{item.status}</TableCell>
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
                                                        Tinjau & Review
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
                                            Tidak ada review pending.
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
                            itemLabel="reviews"
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
