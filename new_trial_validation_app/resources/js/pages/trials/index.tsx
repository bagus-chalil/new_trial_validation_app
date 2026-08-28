import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { ActiveFilterChip } from '@/components/filter-bar';
import { FilterBar, FilterField, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { TrialsTable } from '@/components/trials-table';
import type { TrialRow } from '@/components/trials-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { create as createTrial, index as trialsIndex } from '@/routes/trials';
import type { Paginated } from '@/types';

type Filters = {
    q: string;
    product_type: string;
    date_from: string;
    date_to: string;
};

type PageProps = {
    trials: Paginated<TrialRow>;
    filters: Filters;
    productTypes: string[];
    pageTitle: string;
    pageSubtitle: string;
    group: string;
    canCreateTrial: boolean;
};

export default function TrialsIndex({
    trials,
    filters,
    productTypes,
    pageTitle,
    pageSubtitle,
    group,
    canCreateTrial,
}: PageProps) {
    const [form, setForm] = useState<Filters>(filters);
    const url = trialsIndex(group).url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        router.get(url);
    }

    function clearFilter(key: keyof Filters) {
        const next = { ...form, [key]: '' };
        setForm(next);
        router.get(url, next, { preserveState: true, replace: true });
    }

    const hasActiveFilters = Object.values(filters).some(Boolean);
    const activeChips: ActiveFilterChip[] = [
        filters.q && {
            key: 'q',
            label: `Search: ${filters.q}`,
            onClear: () => clearFilter('q'),
        },
        filters.product_type && {
            key: 'product_type',
            label: `Product Type: ${filters.product_type}`,
            onClear: () => clearFilter('product_type'),
        },
        filters.date_from && {
            key: 'date_from',
            label: `Dari: ${filters.date_from}`,
            onClear: () => clearFilter('date_from'),
        },
        filters.date_to && {
            key: 'date_to',
            label: `Sampai: ${filters.date_to}`,
            onClear: () => clearFilter('date_to'),
        },
    ].filter(Boolean) as ActiveFilterChip[];

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={pageTitle} description={pageSubtitle} />
                    {canCreateTrial && (
                        <Button asChild>
                            <Link href={createTrial().url}>New Trial</Link>
                        </Button>
                    )}
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) =>
                        setForm({ ...form, q: value })
                    }
                    searchPlaceholder="Trial, product, FG code, scope, machine"
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={hasActiveFilters}
                    activeChips={activeChips}
                >
                    <FilterSelect
                        label="Product Type"
                        value={form.product_type}
                        onChange={(value) =>
                            setForm({ ...form, product_type: value })
                        }
                        options={productTypes}
                    />
                    <FilterField label="Tanggal Dari">
                        <Input
                            type="date"
                            value={form.date_from}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    date_from: e.target.value,
                                })
                            }
                        />
                    </FilterField>
                    <FilterField label="Tanggal Sampai">
                        <Input
                            type="date"
                            value={form.date_to}
                            onChange={(e) =>
                                setForm({ ...form, date_to: e.target.value })
                            }
                        />
                    </FilterField>
                </FilterBar>

                <TrialsTable
                    trials={trials}
                    url={url}
                    query={filters}
                    emptyMessage="Tidak ada trial pada halaman ini."
                />
            </div>
        </>
    );
}

TrialsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Trials', href: '#' },
    ],
};
