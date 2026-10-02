import { Head, Link } from '@inertiajs/react';
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
import { translate, useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { departmentReview, index as reportsIndex } from '@/routes/reports';
import { pdf as departmentReviewPdf } from '@/routes/reports/department-review';
import { show as reportShow } from '@/routes/trials/report';
import type { Paginated } from '@/types';

type DepartmentReviewItem = {
    id: number;
    trial_code: string;
    product_name: string;
    pending_with: string | null;
    departments: Record<string, string>;
    review_status: string;
};

// Stored review statuses (shared with the legacy app) -> display label key.
// Anything else (e.g. 'N/A') is shown as-is.
const REVIEW_STATUS_KEYS: Record<string, string> = {
    Pending: 'pending',
    Reviewed: 'reviewed',
};

type PageProps = {
    items: Paginated<DepartmentReviewItem>;
    reviewerDepartments: string[];
};

export default function ReportsDepartmentReview({
    items,
    reviewerDepartments,
}: PageProps) {
    const { t } = useTranslation();

    function reviewStatusLabel(status: string): string {
        const key = REVIEW_STATUS_KEYS[status];

        return key ? t(`report.review.statuses.${key}`) : status;
    }

    return (
        <>
            <Head title={t('reports.department_review.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        title={t('reports.department_review.title')}
                        description={t('reports.department_review.description')}
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={departmentReviewPdf().url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {t('report.pdf.button')}
                        </a>
                    </Button>
                </div>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('report.info.trial_id')}
                                    </TableHead>
                                    <TableHead>
                                        {t('report.info.product_name')}
                                    </TableHead>
                                    {reviewerDepartments.map((dept) => (
                                        <TableHead key={dept}>{dept}</TableHead>
                                    ))}
                                    <TableHead>
                                        {t(
                                            'reports.department_review.review_status',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'reports.department_review.pending_department',
                                        )}
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
                                        {reviewerDepartments.map((dept) => (
                                            <TableCell key={dept}>
                                                {reviewStatusLabel(
                                                    item.departments[dept] ??
                                                        'N/A',
                                                )}
                                            </TableCell>
                                        ))}
                                        <TableCell>
                                            {reviewStatusLabel(
                                                item.review_status,
                                            )}
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
                                                        'reports.actions.view_review',
                                                    )}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={
                                                reviewerDepartments.length + 5
                                            }
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t(
                                                'reports.department_review.empty',
                                            )}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={departmentReview().url}
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
ReportsDepartmentReview.layout = (props: {
    translations: Record<string, string>;
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: reportsIndex() },
        {
            title: translate(
                props.translations,
                'reports.department_review.title',
            ),
            href: departmentReview(),
        },
    ],
});
