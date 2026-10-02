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
import { useTranslation } from '@/hooks/use-translation';
import { trialStatusLabel } from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { create as createTrial, index as trialsIndex } from '@/routes/trials';
import type { Paginated } from '@/types';

type Filters = {
    q: string;
    product_type: string;
    validation_scope: string;
    date_from: string;
    date_to: string;
    status: string;
};

type PageProps = {
    trials: Paginated<TrialRow>;
    filters: Filters;
    productTypes: string[];
    validationScopes: string[];
    pageTitle: string;
    pageSubtitle: string;
    group: string;
    canCreateTrial: boolean;
};

export default function TrialsIndex({
    trials,
    filters,
    productTypes,
    validationScopes,
    pageTitle,
    pageSubtitle,
    group,
    canCreateTrial,
}: PageProps) {
    const { t } = useTranslation();
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
            label: `${t('trials.list.filters.search')}: ${filters.q}`,
            onClear: () => clearFilter('q'),
        },
        filters.status && {
            key: 'status',
            label: `${t('trials.list.filters.status')}: ${trialStatusLabel(t, filters.status)}`,
            onClear: () => clearFilter('status'),
        },
        filters.product_type && {
            key: 'product_type',
            label: `${t('trials.list.filters.product_type')}: ${filters.product_type}`,
            onClear: () => clearFilter('product_type'),
        },
        filters.validation_scope && {
            key: 'validation_scope',
            label: `${t('trials.list.filters.validation_scope')}: ${filters.validation_scope}`,
            onClear: () => clearFilter('validation_scope'),
        },
        filters.date_from && {
            key: 'date_from',
            label: `${t('trials.list.filters.chip_from')}: ${filters.date_from}`,
            onClear: () => clearFilter('date_from'),
        },
        filters.date_to && {
            key: 'date_to',
            label: `${t('trials.list.filters.chip_to')}: ${filters.date_to}`,
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
                            <Link href={createTrial().url}>
                                {t('trials.list.new_trial')}
                            </Link>
                        </Button>
                    )}
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('trials.list.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={hasActiveFilters}
                    activeChips={activeChips}
                >
                    {group === 'tracking' && (
                        <FilterSelect
                            label={t('trials.list.filters.status')}
                            value={form.status}
                            onChange={(value) =>
                                setForm({ ...form, status: value })
                            }
                            options={['In Review', 'Ready for Approval'].map(
                                (status) => ({
                                    value: status,
                                    label: trialStatusLabel(t, status),
                                }),
                            )}
                        />
                    )}
                    <FilterSelect
                        label={t('trials.list.filters.product_type')}
                        value={form.product_type}
                        onChange={(value) =>
                            setForm({ ...form, product_type: value })
                        }
                        options={productTypes}
                    />
                    <FilterSelect
                        label={t('trials.list.filters.validation_scope')}
                        value={form.validation_scope}
                        onChange={(value) =>
                            setForm({ ...form, validation_scope: value })
                        }
                        options={validationScopes}
                        placeholder={t(
                            'trials.list.filters.all_validation_scopes',
                        )}
                    />
                    <FilterField label={t('trials.list.filters.date_from')}>
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
                    <FilterField label={t('trials.list.filters.date_to')}>
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
                    emptyMessage={t('trials.list.empty')}
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
