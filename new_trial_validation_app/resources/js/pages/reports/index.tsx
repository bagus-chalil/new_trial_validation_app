import { Head, Link } from '@inertiajs/react';
import {
    ClipboardList,
    FileCheck2,
    FileWarning,
    ListChecks,
    Printer,
} from 'lucide-react';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import {
    approved,
    auditPrintLog,
    departmentReview,
    index as reportsIndex,
    rejected,
    trialSummary,
} from '@/routes/reports';

// `key` is the report's group in lang/{locale}/reports.php.
const REPORTS = [
    { key: 'approved', href: approved(), icon: FileCheck2 },
    { key: 'rejected', href: rejected(), icon: FileWarning },
    { key: 'trial_summary', href: trialSummary(), icon: ListChecks },
    { key: 'department_review', href: departmentReview(), icon: ClipboardList },
    { key: 'audit_print_log', href: auditPrintLog(), icon: Printer },
];

export default function ReportsIndex() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('reports.index.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('reports.index.title')}
                    description={t('reports.index.description')}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {REPORTS.map((report) => (
                        <Link key={report.key} href={report.href.url}>
                            <Card className="h-full transition-colors hover:border-brand">
                                <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                                    <report.icon className="size-6 text-brand" />
                                    <CardTitle>
                                        {t(`reports.${report.key}.title`)}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CardDescription>
                                        {t(
                                            `reports.${report.key}.card_description`,
                                        )}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

// The reports breadcrumb trail is rebuilt (and translated) by
// contextualBreadcrumbs(); these literal titles are filtered out there.
ReportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: reportsIndex() },
    ],
};
