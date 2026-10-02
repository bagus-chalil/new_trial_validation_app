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
import { index as reportsIndex, rejected } from '@/routes/reports';
import { pdf as rejectedPdf } from '@/routes/reports/rejected';
import { show as reportShow } from '@/routes/trials/report';
import type { Paginated } from '@/types';

type RejectedItem = {
    id: number;
    trial_code: string;
    product_name: string;
    finish_good_code: string;
    product_type: string;
    rejected_at: string | null;
    rejected_by: string | null;
    approval_comment: string | null;
};

type PageProps = {
    items: Paginated<RejectedItem>;
};

export default function ReportsRejected({ items }: PageProps) {
    const { t, formatDate } = useTranslation();

    return (
        <>
            <Head title={t('reports.rejected.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        title={t('reports.rejected.title')}
                        description={t('reports.rejected.description')}
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={rejectedPdf().url}
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
                                    <TableHead>
                                        {t('report.info.fg_code')}
                                    </TableHead>
                                    <TableHead>
                                        {t('report.info.product_type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('report.decision.rejected_at')}
                                    </TableHead>
                                    <TableHead>
                                        {t('report.decision.rejected_by')}
                                    </TableHead>
                                    <TableHead>
                                        {t('reports.rejected.reason')}
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
                                            {formatDate(item.rejected_at)}
                                        </TableCell>
                                        <TableCell>
                                            {item.rejected_by ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.approval_comment ?? '-'}
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
                                                        'reports.actions.view_report',
                                                    )}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={8}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('reports.rejected.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={rejected().url}
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
ReportsRejected.layout = (props: { translations: Record<string, string> }) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: reportsIndex() },
        {
            title: translate(props.translations, 'reports.rejected.title'),
            href: rejected(),
        },
    ],
});
