import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { SectionTabs } from '@/components/projects/section-tabs';
import { Badge } from '@/components/ui/badge';
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
    formatMoney,
    quotationStatusClassName,
    quotationStatusLabel,
} from '@/lib/project';
import { show } from '@/routes/projects';
import { index as quotationsIndex } from '@/routes/projects/quotations';
import type { Paginated } from '@/types';

type Item = {
    id: number;
    project_id: number;
    project_name: string;
    project_code: string;
    vendor_name: string;
    quotation_no: string;
    amount: number;
    currency: string;
    quotation_date: string | null;
    valid_until: string | null;
    status: string;
    has_file: boolean;
};

type Filters = { q: string; status: string; vendor: string };

type PageProps = {
    items: Paginated<Item>;
    filters: Filters;
    options: { statuses: string[]; vendors: string[] };
};

export default function ProjectQuotations({
    items,
    filters,
    options,
}: PageProps) {
    const { t, formatDateOnly, intlLocale } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = quotationsIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        setForm({ q: '', status: '', vendor: '' });
        router.get(url);
    }

    function setSelect(patch: Partial<Filters>) {
        const next = { ...form, ...patch };
        setForm(next);
        router.get(url, next, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={t('projects.quotation.page.head_title')} />

            <div className="space-y-6 p-4">
                <SectionTabs section="finance" active="quotations" />
                <Heading
                    className="space-y-0.5"
                    title={t('projects.quotation.page.title')}
                    description={t('projects.quotation.page.description')}
                />

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(q) => setForm({ ...form, q })}
                    searchPlaceholder={t(
                        'projects.quotation.page.search_placeholder',
                    )}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('projects.fields.status')}
                        value={form.status}
                        onChange={(status) => setSelect({ status })}
                        options={options.statuses.map((value) => ({
                            value,
                            label: quotationStatusLabel(t, value),
                        }))}
                    />
                    <FilterSelect
                        label={t('projects.quotation.vendor')}
                        value={form.vendor}
                        onChange={(vendor) => setSelect({ vendor })}
                        options={options.vendors}
                    />
                </FilterBar>

                <Card>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('projects.quotation.page.project')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.quotation.vendor')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.quotation.number')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('projects.quotation.amount')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.quotation.valid_until')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.status')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((q) => (
                                    <TableRow key={q.id}>
                                        <TableCell>
                                            <Link
                                                href={show(q.project_id).url}
                                                className="font-medium underline-offset-2 hover:underline"
                                            >
                                                {q.project_name}
                                            </Link>
                                            {q.project_code && (
                                                <div className="text-xs text-muted-foreground">
                                                    {q.project_code}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>{q.vendor_name}</TableCell>
                                        <TableCell>
                                            {q.quotation_no || '-'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(
                                                q.amount,
                                                q.currency,
                                                intlLocale,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateOnly(q.valid_until)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={quotationStatusClassName(
                                                    q.status,
                                                )}
                                            >
                                                {quotationStatusLabel(
                                                    t,
                                                    q.status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('projects.quotation.page.empty')}
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
                            itemLabel={t('projects.quotation.page.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
