import { Head } from '@inertiajs/react';
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
import { auditPrintLog, index as reportsIndex } from '@/routes/reports';
import { pdf as auditPrintLogPdf } from '@/routes/reports/audit-print-log';
import type { Paginated } from '@/types';

type AuditPrintLogItem = {
    id: number;
    trial: { id: number; trial_code: string } | null;
    user_email: string | null;
    created_at: string | null;
    new_data: { report_type?: string } | null;
};

type PageProps = {
    items: Paginated<AuditPrintLogItem>;
};

export default function ReportsAuditPrintLog({ items }: PageProps) {
    const { t, formatDate } = useTranslation();

    return (
        <>
            <Head title={t('reports.audit_print_log.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        title={t('reports.audit_print_log.title')}
                        description={t('reports.audit_print_log.description')}
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={auditPrintLogPdf().url}
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
                                        {t(
                                            'reports.audit_print_log.printed_by',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'reports.audit_print_log.printed_at',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'reports.audit_print_log.report_type',
                                        )}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            {item.trial?.trial_code ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.user_email ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(item.created_at, true)}
                                        </TableCell>
                                        {/* Stored audit value, not translated. */}
                                        <TableCell>
                                            {item.new_data?.report_type ??
                                                'Report'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('reports.audit_print_log.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={auditPrintLog().url}
                            currentPage={items.current_page}
                            lastPage={items.last_page}
                            total={items.total}
                            itemLabel={t('reports.audit_print_log.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

// Layout callback so the last breadcrumb is translated; see approved.tsx.
ReportsAuditPrintLog.layout = (props: {
    translations: Record<string, string>;
}) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: reportsIndex() },
        {
            title: translate(
                props.translations,
                'reports.audit_print_log.title',
            ),
            href: auditPrintLog(),
        },
    ],
});
