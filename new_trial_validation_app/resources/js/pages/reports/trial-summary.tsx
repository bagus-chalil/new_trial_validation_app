import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import type { ActiveFilterChip } from '@/components/filter-bar';
import { FilterBar, FilterField, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TRIAL_STATUSES, trialStatusBadgeClassName } from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { index as reportsIndex, trialSummary } from '@/routes/reports';
import { pdf as trialSummaryPdf } from '@/routes/reports/trial-summary';
import { show as reportShow } from '@/routes/trials/report';
import type { Paginated } from '@/types';

type SummaryItem = {
    id: number;
    trial_code: string;
    product_name: string;
    finish_good_code: string;
    product_type: string;
    validation_scope: string[] | null;
    machine_used: string[] | null;
    progress_status: string;
    final_decision: string | null;
    current_step: string | null;
    created_by: string | null;
    created_at: string | null;
    pending_with: string | null;
};

type Filters = {
    date_from: string;
    date_to: string;
    status: string;
    product_type: string;
    validation_scope: string;
    machine_used: string;
    product_name: string;
};

type PageProps = {
    items: Paginated<SummaryItem>;
    filters: Filters;
    productTypes: string[];
    validationScopes: string[];
    machines: string[];
};

export default function ReportsTrialSummary({
    items,
    filters,
    productTypes,
    validationScopes,
    machines,
}: PageProps) {
    const [form, setForm] = useState<Filters>(filters);
    const url = trialSummary().url;

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
        filters.status && {
            key: 'status',
            label: `Status: ${filters.status}`,
            onClear: () => clearFilter('status'),
        },
        filters.product_type && {
            key: 'product_type',
            label: `Product Type: ${filters.product_type}`,
            onClear: () => clearFilter('product_type'),
        },
        filters.validation_scope && {
            key: 'validation_scope',
            label: `Scope: ${filters.validation_scope}`,
            onClear: () => clearFilter('validation_scope'),
        },
        filters.machine_used && {
            key: 'machine_used',
            label: `Machine: ${filters.machine_used}`,
            onClear: () => clearFilter('machine_used'),
        },
        filters.product_name && {
            key: 'product_name',
            label: `Product: ${filters.product_name}`,
            onClear: () => clearFilter('product_name'),
        },
    ].filter(Boolean) as ActiveFilterChip[];

    return (
        <>
            <Head title="Trial Summary Report" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        title="Trial Summary Report"
                        description="Ringkasan semua trial validation."
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={trialSummaryPdf({ query: filters }).url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Unduh PDF
                        </a>
                    </Button>
                </div>

                <FilterBar
                    className="print:hidden"
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={hasActiveFilters}
                    activeChips={activeChips}
                >
                    <FilterField label="Date From">
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
                    <FilterField label="Date To">
                        <Input
                            type="date"
                            value={form.date_to}
                            onChange={(e) =>
                                setForm({ ...form, date_to: e.target.value })
                            }
                        />
                    </FilterField>
                    <FilterSelect
                        label="Status"
                        value={form.status}
                        onChange={(value) =>
                            setForm({ ...form, status: value })
                        }
                        options={[...TRIAL_STATUSES]}
                    />
                    <FilterSelect
                        label="Product Type"
                        value={form.product_type}
                        onChange={(value) =>
                            setForm({ ...form, product_type: value })
                        }
                        options={productTypes}
                        placeholder="Semua product type"
                    />
                    <FilterSelect
                        label="Validation Scope"
                        value={form.validation_scope}
                        onChange={(value) =>
                            setForm({ ...form, validation_scope: value })
                        }
                        options={validationScopes}
                        placeholder="Semua scope"
                    />
                    <FilterSelect
                        label="Machine Used"
                        value={form.machine_used}
                        onChange={(value) =>
                            setForm({ ...form, machine_used: value })
                        }
                        options={machines}
                        placeholder="Semua machine"
                    />
                    <FilterField label="Product Name">
                        <Input
                            placeholder="Product name"
                            value={form.product_name}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    product_name: e.target.value,
                                })
                            }
                        />
                    </FilterField>
                </FilterBar>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Trial ID</TableHead>
                                    <TableHead>Product Name</TableHead>
                                    <TableHead>Finish Good Code</TableHead>
                                    <TableHead>Product Type</TableHead>
                                    <TableHead>Validation Scope</TableHead>
                                    <TableHead>Machine Used</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Current Step</TableHead>
                                    <TableHead>Created By</TableHead>
                                    <TableHead>Created Date</TableHead>
                                    <TableHead>Pending With</TableHead>
                                    <TableHead className="print:hidden">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>{item.trial_code}</TableCell>
                                        <TableCell>
                                            {item.product_name}
                                        </TableCell>
                                        <TableCell>
                                            {item.finish_good_code}
                                        </TableCell>
                                        <TableCell>
                                            {item.product_type}
                                        </TableCell>
                                        <TableCell>
                                            {(item.validation_scope ?? []).join(
                                                ', ',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {(item.machine_used ?? []).join(
                                                ', ',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={trialStatusBadgeClassName(
                                                    item.progress_status,
                                                    item.final_decision,
                                                )}
                                            >
                                                {item.progress_status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {item.current_step ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.created_by ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.created_at ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.pending_with ?? '-'}
                                        </TableCell>
                                        <TableCell className="print:hidden">
                                            <Button
                                                variant="link"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        reportShow(item.id).url
                                                    }
                                                >
                                                    View Summary
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={12}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            Tidak ada data trial.
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
                            itemLabel="trials"
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReportsTrialSummary.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: reportsIndex() },
        { title: 'Trial Summary Report', href: trialSummary() },
    ],
};
