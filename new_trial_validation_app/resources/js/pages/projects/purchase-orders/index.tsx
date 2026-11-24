import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { SectionTabs } from '@/components/projects/section-tabs';
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
import { formatMoney } from '@/lib/project';
import { show as projectShow } from '@/routes/projects';
import {
    index as poIndex,
    show as poShow,
    template as poTemplate,
} from '@/routes/projects/purchase-orders';
import type { Paginated } from '@/types';

type Item = {
    id: number;
    project_id: number;
    project_name: string;
    project_code: string;
    po_no: string;
    po_date: string | null;
    vendor_name: string;
    subject: string;
    currency: string;
    total: number;
};

type Filters = { q: string; vendor: string };

type PageProps = {
    items: Paginated<Item>;
    filters: Filters;
    vendors: string[];
    canCreate: boolean;
};

export default function PurchaseOrderIndex({
    items,
    filters,
    vendors,
    canCreate,
}: PageProps) {
    const { t, formatDateOnly, intlLocale } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = poIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        setForm({ q: '', vendor: '' });
        router.get(url);
    }

    function setVendor(vendor: string) {
        const next = { ...form, vendor };
        setForm(next);
        router.get(url, next, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={t('purchase_orders.head_title')} />

            <div className="space-y-6 p-4">
                <SectionTabs section="finance" active="purchase-orders" />
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        className="space-y-0.5"
                        title={t('purchase_orders.title')}
                        description={t('purchase_orders.description')}
                    />
                    <Button variant="outline" size="sm" asChild>
                        <Link href={poTemplate()}>
                            {t('purchase_orders.template_link')}
                        </Link>
                    </Button>
                </div>

                {!canCreate && (
                    <p className="text-sm text-muted-foreground">
                        {t('purchase_orders.view_only')}
                    </p>
                )}

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(q) => setForm({ ...form, q })}
                    searchPlaceholder={t('purchase_orders.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('purchase_orders.columns.vendor')}
                        value={form.vendor}
                        onChange={setVendor}
                        options={vendors}
                    />
                </FilterBar>

                <Card>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('purchase_orders.columns.po_no')}
                                    </TableHead>
                                    <TableHead>
                                        {t('purchase_orders.columns.date')}
                                    </TableHead>
                                    <TableHead>
                                        {t('purchase_orders.columns.project')}
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        {t('purchase_orders.columns.vendor')}
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        {t('purchase_orders.columns.subject')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('purchase_orders.columns.total')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((po) => (
                                    <TableRow key={po.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={poShow(po.id)}
                                                className="hover:underline"
                                            >
                                                {po.po_no}
                                            </Link>
                                            <div className="text-xs font-normal text-muted-foreground md:hidden">
                                                {po.vendor_name}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {po.po_date
                                                ? formatDateOnly(po.po_date)
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                href={projectShow(
                                                    po.project_id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {po.project_code}{' '}
                                                {po.project_name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {po.vendor_name}
                                        </TableCell>
                                        <TableCell className="hidden max-w-xs truncate lg:table-cell">
                                            {po.subject || '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatMoney(
                                                po.total,
                                                po.currency,
                                                intlLocale,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            {t('purchase_orders.empty')}
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
                            itemLabel="PO"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
