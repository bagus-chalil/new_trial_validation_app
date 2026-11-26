import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
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
    journeyResultClassName,
    journeyResultLabel,
    journeyTypeLabel,
} from '@/lib/journey';
import { index as journeyIndex, show as journeyShow } from '@/routes/journey';
import type { Paginated } from '@/types';

type CaseRow = {
    id: number;
    case_no: string;
    case_type: string;
    product_name: string;
    finish_good_code: string | null;
    total: number;
    counts: Record<string, number>;
    last_result: string | null;
    cohort: string;
    classification: {
        process_area: string;
        purpose: string;
    };
};

type Filters = { q: string; type: string; cohort: string };

type PageProps = {
    cases: Paginated<CaseRow>;
    filters: Filters;
    caseTypes: string[];
};

export default function JourneyIndex({ cases, filters, caseTypes }: PageProps) {
    const { t } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = journeyIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        router.get(url);
    }

    return (
        <>
            <Head title={t('journey.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('journey.title')}
                    description={t('journey.description')}
                />

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('journey.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Boolean(
                        filters.q || filters.type || filters.cohort,
                    )}
                >
                    <FilterSelect
                        label={t('journey.columns.type')}
                        value={form.type}
                        onChange={(type) => setForm({ ...form, type })}
                        placeholder={t('journey.all_types')}
                        options={caseTypes.map((type) => ({
                            value: type,
                            label: journeyTypeLabel(t, type),
                        }))}
                    />
                    <FilterSelect
                        label={t('journey.cohort.badge_eligible')}
                        value={form.cohort}
                        onChange={(cohort) => setForm({ ...form, cohort })}
                        placeholder={t('journey.cohort.all')}
                        options={[
                            {
                                value: 'eligible',
                                label: t('journey.cohort.badge_eligible'),
                            },
                            {
                                value: 'historical',
                                label: t('journey.cohort.badge_historical'),
                            },
                        ]}
                    />
                </FilterBar>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('journey.columns.case_no')}
                                    </TableHead>
                                    <TableHead>
                                        {t('journey.columns.product')}
                                    </TableHead>
                                    <TableHead>
                                        {t('journey.columns.fg')}
                                    </TableHead>
                                    <TableHead>
                                        {t('journey.columns.type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('journey.columns.attempts')}
                                    </TableHead>
                                    <TableHead>
                                        {t('journey.columns.last_result')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cases.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={journeyShow(row.id).url}
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {row.case_no}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {row.product_name}
                                        </TableCell>
                                        <TableCell>
                                            {row.finish_good_code ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {journeyTypeLabel(t, row.case_type)}
                                            {row.cohort !== 'eligible' && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2 bg-muted text-muted-foreground"
                                                >
                                                    {t(
                                                        'journey.cohort.badge_historical',
                                                    )}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>{row.total}</TableCell>
                                        <TableCell>
                                            {row.last_result ? (
                                                <Badge
                                                    variant="secondary"
                                                    className={journeyResultClassName(
                                                        row.last_result,
                                                    )}
                                                >
                                                    {journeyResultLabel(
                                                        t,
                                                        row.last_result,
                                                    )}
                                                </Badge>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {cases.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('journey.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={{ q: filters.q, type: filters.type }}
                            currentPage={cases.current_page}
                            lastPage={cases.last_page}
                            total={cases.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

JourneyIndex.layout = {
    breadcrumbs: [{ title: 'Product Journey', href: journeyIndex() }],
};
