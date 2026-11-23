import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { SignaturePills } from '@/components/toolings/signature-pills';
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
    toolingActionLabel,
    toolingStatusClassName,
    toolingStatusLabel,
} from '@/lib/tooling';
import { cn } from '@/lib/utils';
import {
    create,
    index as toolingsIndex,
    show,
} from '@/routes/projects/toolings';

type Item = {
    id: number;
    npd_no: string;
    product_name: string;
    client_code: string;
    packaging_type: string;
    status: string;
    sample_status: string;
    is_critical: boolean;
    submitted_by_name: string;
    due_date: string | null;
    is_late: boolean;
    signed: Record<string, boolean>;
    my_action: string | null;
};

type Kpi = {
    active: number;
    waiting_rni_sample: number;
    pie_check: number;
    production: number;
    approved: number;
    mine: number;
};

type Filters = { q: string; status: string; scope: string };

type PageProps = {
    items: Item[];
    kpi: Kpi;
    statuses: string[];
    filters: Filters;
    can: { create: boolean };
    myRoles: string[];
};

export default function ToolingIndex({
    items,
    kpi,
    statuses,
    filters,
    can,
    myRoles,
}: PageProps) {
    const { t } = useTranslation();
    const [q, setQ] = useState(filters.q);
    const [status, setStatus] = useState(filters.status);
    const [scope, setScope] = useState(filters.scope);
    const url = toolingsIndex().url;

    function visit(next: Partial<Filters>) {
        router.get(
            url,
            { q, status, scope, ...next },
            { preserveState: true, replace: true },
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        visit({});
    }

    function reset() {
        setQ('');
        setStatus('');
        setScope('active');
        router.get(url);
    }

    function pickKpi(next: Partial<Filters>) {
        const merged = { q, status: '', scope: 'all', ...next };
        setStatus(merged.status);
        setScope(merged.scope);
        router.get(url, merged, { preserveState: true, replace: true });
    }

    const kpiCards: {
        key: keyof Kpi;
        onClick: () => void;
        tone?: string;
    }[] = [
        {
            key: 'mine',
            onClick: () => pickKpi({ scope: 'mine' }),
            tone: 'border-l-4 border-l-brand',
        },
        { key: 'active', onClick: () => pickKpi({ scope: 'active' }) },
        {
            key: 'waiting_rni_sample',
            onClick: () =>
                pickKpi({ scope: 'active', status: 'Menunggu RNI & Sampel' }),
        },
        {
            key: 'pie_check',
            onClick: () => pickKpi({ status: 'Cek Tooling PI' }),
        },
        {
            key: 'production',
            onClick: () => pickKpi({ status: 'Review Production' }),
        },
        { key: 'approved', onClick: () => pickKpi({ status: 'Approved' }) },
    ];

    return (
        <>
            <Head title={t('toolings.head_title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <Heading
                            title={t('toolings.title')}
                            description={t('toolings.description')}
                        />
                        <p className="text-xs text-muted-foreground">
                            {myRoles.length > 0
                                ? t('toolings.my_roles', {
                                      roles: myRoles
                                          .map((r) =>
                                              t(`toolings.roles_short.${r}`),
                                          )
                                          .join(', '),
                                  })
                                : t('toolings.my_roles_none')}
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus />
                                {t('toolings.new')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    {kpiCards.map((card) => (
                        <button
                            key={card.key}
                            type="button"
                            onClick={card.onClick}
                            className={cn(
                                'rounded-xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-muted/50',
                                card.tone,
                            )}
                        >
                            <div className="text-xs text-muted-foreground">
                                {t(`toolings.kpi.${card.key}`)}
                            </div>
                            <div className="mt-1 text-2xl font-semibold tabular-nums">
                                {kpi[card.key]}
                            </div>
                        </button>
                    ))}
                </div>

                <FilterBar
                    searchValue={q}
                    onSearchChange={setQ}
                    searchPlaceholder={t('toolings.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={
                        filters.q !== '' ||
                        filters.status !== '' ||
                        filters.scope !== 'active'
                    }
                >
                    <FilterSelect
                        label={t('toolings.scope.label')}
                        value={scope === 'all' ? '' : scope}
                        onChange={(v) => setScope(v === '' ? 'all' : v)}
                        options={[
                            {
                                value: 'active',
                                label: t('toolings.scope.active'),
                            },
                            { value: 'mine', label: t('toolings.scope.mine') },
                        ]}
                        className="min-w-44"
                    />
                    <FilterSelect
                        label={t('toolings.columns.status')}
                        value={status}
                        onChange={setStatus}
                        options={statuses.map((s) => ({
                            value: s,
                            label: toolingStatusLabel(t, s),
                        }))}
                        className="min-w-52"
                    />
                </FilterBar>

                {items.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            {t('toolings.empty')}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="py-0">
                        <CardContent className="overflow-x-auto px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('toolings.columns.npd_no')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.columns.product')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.columns.packaging')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.columns.status')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.columns.signatures')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.columns.due_date')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.columns.submitted_by')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium whitespace-nowrap">
                                                <Link
                                                    href={show(item.id).url}
                                                    className="underline underline-offset-2"
                                                >
                                                    {item.npd_no ||
                                                        `#${item.id}`}
                                                </Link>
                                                {item.my_action && (
                                                    <div className="mt-1">
                                                        <Badge className="bg-brand text-white">
                                                            {toolingActionLabel(
                                                                t,
                                                                item.my_action,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="max-w-md min-w-56 whitespace-normal">
                                                <div>{item.product_name}</div>
                                                {item.client_code && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {item.client_code}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span>
                                                        {item.packaging_type}
                                                    </span>
                                                    {item.is_critical && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-red-300 text-red-700 dark:text-red-300"
                                                        >
                                                            {t(
                                                                'toolings.critical',
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className={toolingStatusClassName(
                                                        item.status,
                                                    )}
                                                >
                                                    {toolingStatusLabel(
                                                        t,
                                                        item.status,
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <SignaturePills
                                                    signed={item.signed}
                                                />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {item.due_date ?? '-'}
                                                {item.is_late && (
                                                    <span className="ml-1 inline-flex items-center gap-0.5 text-xs text-red-600 dark:text-red-400">
                                                        <AlertTriangle className="size-3" />
                                                        {t('toolings.late')}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {item.submitted_by_name}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
