import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
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
import { mixingStatusClassName, mixingUrl } from '@/lib/validation-steps';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    mixing_code: string;
    product_name: string;
    product_type: string;
    batch_no: string;
    mixing_date: string | null;
    progress_status: string;
    pending_with: string;
    case_id: number | null;
};

type Filters = { q: string; status: string };

type PageProps = {
    mixings: Paginated<Row>;
    filters: Filters;
    statuses: string[];
    canCreate: boolean;
};

export default function MixingIndex({
    mixings,
    filters,
    statuses,
    canCreate,
}: PageProps) {
    const { t } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(mixingUrl.index(), form, {
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title={t('mixing.title')} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={t('mixing.title')}
                        description={t('mixing.subtitle')}
                    />
                    {canCreate && (
                        <Button asChild className="gap-1.5">
                            <Link href={mixingUrl.create()}>
                                <Plus className="size-4" />
                                {t('mixing.new')}
                            </Link>
                        </Button>
                    )}
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(q) => setForm({ ...form, q })}
                    searchPlaceholder={t('mixing.list.search')}
                    onSubmit={submit}
                    onReset={() => router.get(mixingUrl.index())}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('mixing.list.status')}
                        value={form.status}
                        onChange={(status) => setForm({ ...form, status })}
                        placeholder={t('mixing.list.all_status')}
                        options={statuses.map((s) => ({
                            value: s,
                            label: t(`mixing.status.${s}`),
                        }))}
                    />
                </FilterBar>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('mixing.list.code')}
                                    </TableHead>
                                    <TableHead>
                                        {t('mixing.list.product')}
                                    </TableHead>
                                    <TableHead>
                                        {t('mixing.list.batch')}
                                    </TableHead>
                                    <TableHead>
                                        {t('mixing.list.date')}
                                    </TableHead>
                                    <TableHead>
                                        {t('mixing.list.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('mixing.list.pending')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {mixings.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-center text-muted-foreground"
                                        >
                                            {t('mixing.list.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                                {mixings.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={mixingUrl.show(m.id)}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {m.mixing_code}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {m.product_name}
                                            <div className="text-xs text-muted-foreground">
                                                {m.product_type}
                                            </div>
                                        </TableCell>
                                        <TableCell>{m.batch_no}</TableCell>
                                        <TableCell>
                                            {m.mixing_date ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                className={mixingStatusClassName(
                                                    m.progress_status,
                                                )}
                                            >
                                                {t(
                                                    `mixing.status.${m.progress_status}`,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {m.pending_with || '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <PaginationFooter
                            url={mixingUrl.index()}
                            query={{ ...filters }}
                            currentPage={mixings.current_page}
                            lastPage={mixings.last_page}
                            total={mixings.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

MixingIndex.layout = {
    breadcrumbs: [{ title: 'Mixing', href: '/mixings' }],
};
