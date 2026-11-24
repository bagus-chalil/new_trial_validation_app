import { Head, Link, router } from '@inertiajs/react';
import {
    FileDown,
    FileText,
    FileSpreadsheet,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ToolingController from '@/actions/App/Http/Controllers/ToolingController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { SignaturePills } from '@/components/toolings/signature-pills';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import {
    toolingProductTypeLabel,
    toolingStatusClassName,
    toolingStatusLabel,
} from '@/lib/tooling';
import { cn } from '@/lib/utils';
import { show as projectShow } from '@/routes/projects';
import {
    create,
    index as queueIndex,
    pdf,
    report,
    show,
    word,
} from '@/routes/projects/toolings';
import { exportMethod } from '@/routes/projects/toolings/report';

type Row = {
    id: number;
    can_delete: boolean;
    npd_no: string;
    product_name: string;
    client_code: string;
    packdev: string;
    packaging_type: string;
    product_type: string;
    bom_primary: string | null;
    quantity_po: string;
    recommended_machine: string;
    risk_level: string;
    need_invest: string;
    is_critical: boolean;
    status: string;
    signed: Record<string, boolean>;
    finished_at: string | null;
    need_invest_pending: boolean;
    project: { id: number; code: string; progress: number } | null;
};

type Kpi = {
    queue: number;
    queue_pie: number;
    queue_production: number;
    done: number;
    need_invest: number;
    need_invest_ready: number;
    with_project: number;
    no_invest: number;
};

type FilterKey = 'all' | 'need_invest' | 'no_invest' | 'project' | 'rejected';

type PageProps = {
    items: Row[];
    kpi: Kpi;
    counts: Record<FilterKey, number>;
    filters: { q: string; filter: FilterKey };
    can: { create: boolean };
};

function RowSummary({ row }: { row: Row }) {
    const { t } = useTranslation();
    const fields: [string, string | null][] = [
        ['npd_no', row.npd_no],
        ['client_code', row.client_code],
        ['product_name', row.product_name],
        ['packdev', row.packdev],
        ['packaging_type', row.packaging_type],
        ['quantity_po', row.quantity_po],
        ['bom_primary', row.bom_primary],
        ['recommended_machine', row.recommended_machine],
        ['risk_level', row.risk_level],
        ['need_invest', row.need_invest],
    ];

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {t('toolings.report.summary')}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogTitle>{row.npd_no}</DialogTitle>
                <DialogDescription>
                    {toolingStatusLabel(t, row.status)}
                    {row.finished_at ? ` · ${row.finished_at}` : ''}
                </DialogDescription>
                <SignaturePills signed={row.signed} />
                <dl className="grid gap-3 sm:grid-cols-2">
                    {fields.map(([key, value]) => (
                        <div key={key} className="min-w-0">
                            <dt className="text-xs text-muted-foreground">
                                {t(`toolings.fields.${key}`)}
                            </dt>
                            <dd className="text-sm font-medium break-words">
                                {value || '-'}
                            </dd>
                        </div>
                    ))}
                </dl>
                {row.project && (
                    <Link
                        href={projectShow(row.project.id).url}
                        className="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                        {t('toolings.report.project_progress', {
                            code: row.project.code,
                            pct: row.project.progress,
                        })}
                    </Link>
                )}
            </DialogContent>
        </Dialog>
    );
}

const FILTER_KEYS: FilterKey[] = [
    'all',
    'need_invest',
    'no_invest',
    'project',
    'rejected',
];

const FILTER_ACTIVE: Record<FilterKey, string> = {
    all: 'bg-brand text-white border-brand hover:bg-brand/90 hover:text-white',
    need_invest:
        'bg-amber-500 text-white border-amber-500 hover:bg-amber-500/90 hover:text-white',
    no_invest:
        'bg-emerald-600 text-white border-emerald-600 hover:bg-emerald-600/90 hover:text-white',
    project:
        'bg-blue-600 text-white border-blue-600 hover:bg-blue-600/90 hover:text-white',
    rejected:
        'bg-red-600 text-white border-red-600 hover:bg-red-600/90 hover:text-white',
};

function accent(row: Row): string {
    if (row.status === 'Ditolak') {
        return 'border-l-red-500 bg-red-500/5';
    }

    if (row.need_invest_pending) {
        return 'border-l-amber-500 bg-amber-500/5';
    }

    if (row.project) {
        return row.project.progress >= 100
            ? 'border-l-emerald-500 bg-emerald-500/5'
            : 'border-l-blue-500 bg-blue-500/5';
    }

    return 'border-l-emerald-500';
}

export default function ToolingReport({
    items,
    kpi,
    counts,
    filters,
    can,
}: PageProps) {
    const { t } = useTranslation();
    const [q, setQ] = useState(filters.q);
    const url = report().url;

    function visit(next: Partial<PageProps['filters']>) {
        router.get(
            url,
            { q, filter: filters.filter, ...next },
            { preserveState: true, replace: true },
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        visit({});
    }

    const kpiCards = [
        {
            key: 'queue',
            value: kpi.queue,
            sub: t('toolings.report.kpi.queue_sub', {
                pie: kpi.queue_pie,
                production: kpi.queue_production,
            }),
            tone: 'border-l-brand',
            href: queueIndex().url,
        },
        {
            key: 'done',
            value: kpi.done,
            sub: t('toolings.report.kpi.done_sub'),
            tone: 'border-l-emerald-500',
        },
        {
            key: 'need_invest',
            value: kpi.need_invest,
            sub: t('toolings.report.kpi.need_invest_sub', {
                count: kpi.need_invest_ready,
            }),
            tone: 'border-l-amber-500',
        },
        {
            key: 'with_project',
            value: kpi.with_project,
            sub: t('toolings.report.kpi.with_project_sub', {
                count: kpi.no_invest,
            }),
            tone: 'border-l-blue-500',
        },
    ];

    return (
        <>
            <Head title={t('toolings.report.head_title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('toolings.report.title')}
                        description={t('toolings.report.description')}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <a href={exportMethod().url}>
                                <FileSpreadsheet />
                                {t('toolings.report.export')}
                            </a>
                        </Button>
                        <Button asChild>
                            <Link href={queueIndex().url}>
                                {t('toolings.report.queue_button', {
                                    count: kpi.queue,
                                })}{' '}
                                →
                            </Link>
                        </Button>
                        {can.create && (
                            <Button variant="outline" asChild>
                                <Link href={create().url}>
                                    <Plus />
                                    {t('toolings.new')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
                    {kpiCards.map((card) => {
                        const body = (
                            <>
                                <div className="text-2xl font-semibold tabular-nums">
                                    {card.value}
                                </div>
                                <div className="text-sm">
                                    {t(`toolings.report.kpi.${card.key}`)}
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {card.sub}
                                </div>
                            </>
                        );
                        const cls = cn(
                            'rounded-xl border border-l-4 bg-card p-3 shadow-xs sm:p-4',
                            card.tone,
                        );

                        return card.href ? (
                            <Link
                                key={card.key}
                                href={card.href}
                                className={cn(cls, 'hover:bg-muted/50')}
                            >
                                {body}
                            </Link>
                        ) : (
                            <div key={card.key} className={cls}>
                                {body}
                            </div>
                        );
                    })}
                </div>

                <Card>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-start justify-between gap-3 border-b pb-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h2 className="text-base font-semibold">
                                        {t('toolings.report.list_title')}
                                    </h2>
                                    <Badge variant="outline">
                                        {counts.all}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {t('toolings.report.list_hint')}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-1.5">
                                {FILTER_KEYS.filter(
                                    (key) =>
                                        key !== 'rejected' ||
                                        counts.rejected > 0 ||
                                        filters.filter === 'rejected',
                                ).map((key) => (
                                    <Button
                                        key={key}
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        className={cn(
                                            filters.filter === key &&
                                                FILTER_ACTIVE[key],
                                        )}
                                        onClick={() => visit({ filter: key })}
                                    >
                                        {t(`toolings.report.filters.${key}`, {
                                            count: counts[key],
                                        })}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <form
                            onSubmit={submit}
                            className="flex flex-wrap items-center gap-3"
                        >
                            <div className="relative w-full max-w-md">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder={t(
                                        'toolings.report.search_placeholder',
                                    )}
                                    className="pl-8"
                                />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {t('toolings.report.shown', {
                                    shown: items.length,
                                    total: counts[filters.filter],
                                })}
                            </span>
                        </form>

                        {items.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                {t('toolings.report.empty')}
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {items.map((row) => (
                                    <div
                                        key={row.id}
                                        className={cn(
                                            'grid gap-3 rounded-lg border border-l-4 p-3 lg:grid-cols-[2fr_2fr_1fr_auto] lg:items-center',
                                            accent(row),
                                        )}
                                    >
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <Link
                                                    href={show(row.id).url}
                                                    className="font-semibold underline-offset-2 hover:underline"
                                                >
                                                    {row.product_name ||
                                                        row.npd_no}
                                                </Link>
                                                {row.client_code && (
                                                    <Badge variant="outline">
                                                        {row.client_code}
                                                    </Badge>
                                                )}
                                                {row.is_critical && (
                                                    <Badge
                                                        variant="outline"
                                                        className="border-red-300 text-red-700 dark:text-red-300"
                                                    >
                                                        {t('toolings.critical')}
                                                    </Badge>
                                                )}
                                                {row.need_invest_pending && (
                                                    <Badge className="bg-amber-500 text-white">
                                                        {t(
                                                            'toolings.report.badge_need_invest',
                                                        )}
                                                    </Badge>
                                                )}
                                                {row.project ? (
                                                    <Badge className="bg-blue-600 text-white">
                                                        {t(
                                                            'toolings.report.badge_project',
                                                            {
                                                                code: row
                                                                    .project
                                                                    .code,
                                                            },
                                                        )}
                                                    </Badge>
                                                ) : (
                                                    row.need_invest === 'NO' &&
                                                    row.status ===
                                                        'Approved' && (
                                                        <Badge className="bg-emerald-600 text-white">
                                                            {t(
                                                                'toolings.report.badge_no_invest',
                                                            )}
                                                        </Badge>
                                                    )
                                                )}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {row.npd_no} ·{' '}
                                                {t('toolings.report.packdev', {
                                                    name: row.packdev || '-',
                                                })}
                                            </div>
                                            <SignaturePills
                                                signed={row.signed}
                                            />
                                        </div>

                                        <div className="min-w-0 space-y-0.5 text-sm">
                                            <div>
                                                <b>
                                                    {row.packaging_type || '-'}
                                                </b>{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    (
                                                    {toolingProductTypeLabel(
                                                        t,
                                                        row.product_type,
                                                    )}
                                                    )
                                                </span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {t('toolings.report.bom', {
                                                    bom: row.bom_primary || '-',
                                                })}
                                                {row.quantity_po
                                                    ? ` · ${t('toolings.report.po', { qty: row.quantity_po })}`
                                                    : ''}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {t('toolings.report.machine')}:{' '}
                                                <b>
                                                    {row.recommended_machine ||
                                                        '-'}
                                                </b>
                                                {row.risk_level
                                                    ? ` · ${row.risk_level}`
                                                    : ''}
                                            </div>
                                        </div>

                                        <div className="space-y-1 text-sm">
                                            <Badge
                                                variant="outline"
                                                className={toolingStatusClassName(
                                                    row.status,
                                                )}
                                            >
                                                {toolingStatusLabel(
                                                    t,
                                                    row.status,
                                                )}
                                            </Badge>
                                            <div className="text-xs text-muted-foreground">
                                                {row.finished_at}
                                            </div>
                                            {row.project && (
                                                <Link
                                                    href={
                                                        projectShow(
                                                            row.project.id,
                                                        ).url
                                                    }
                                                    className="block text-xs font-medium text-blue-600 underline-offset-2 hover:underline dark:text-blue-400"
                                                >
                                                    {t(
                                                        'toolings.report.project_progress',
                                                        {
                                                            code: row.project
                                                                .code,
                                                            pct: row.project
                                                                .progress,
                                                        },
                                                    )}
                                                </Link>
                                            )}
                                        </div>

                                        <div className="flex flex-wrap gap-1.5">
                                            <RowSummary row={row} />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <Link href={show(row.id).url}>
                                                    {t(
                                                        'toolings.report.detail',
                                                    )}
                                                </Link>
                                            </Button>
                                            <Button size="sm" asChild>
                                                <a
                                                    href={pdf(row.id).url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <FileDown />
                                                    {t('toolings.report.pdf')}
                                                </a>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a href={word(row.id).url}>
                                                    <FileText />
                                                    {t('toolings.report.word')}
                                                </a>
                                            </Button>
                                            {row.can_delete && (
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-destructive"
                                                        >
                                                            <Trash2 />
                                                            {t(
                                                                'toolings.delete',
                                                            )}
                                                        </Button>
                                                    }
                                                    title={t(
                                                        'toolings.show.delete_title',
                                                    )}
                                                    description={t(
                                                        'toolings.show.delete_description',
                                                        { npd: row.npd_no },
                                                    )}
                                                    confirmLabel={t(
                                                        'toolings.delete',
                                                    )}
                                                    formProps={ToolingController.destroy.form(
                                                        row.id,
                                                    )}
                                                >
                                                    {() => (
                                                        <input
                                                            type="hidden"
                                                            name="from"
                                                            value="report"
                                                        />
                                                    )}
                                                </ConfirmDialog>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
