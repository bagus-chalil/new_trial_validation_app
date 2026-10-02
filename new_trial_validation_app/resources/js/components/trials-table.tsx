import { Link } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { PaginationFooter } from '@/components/pagination-footer';
import { TrialProcessProgress } from '@/components/trial-process-progress';
import { TrialStepProgress } from '@/components/trial-step-progress';
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
import type { TranslateFn } from '@/hooks/use-translation';
import {
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { edit as editTrial } from '@/routes/trials';
import type { Paginated } from '@/types';

export type TrialRow = {
    id: number;
    trial_code: string;
    product_name: string;
    finish_good_code: string;
    product_type: string;
    validation_scope: string[] | null;
    progress_status: string;
    final_decision: string | null;
    current_step: string | null;
    created_at: string;
    pending_with: string | null;
    can_edit: boolean;
};

const columnHelper = createColumnHelper<TrialRow>();

function buildColumns(
    t: TranslateFn,
    formatDate: (value: string | null | undefined) => string,
) {
    return [
        columnHelper.accessor('trial_code', {
            header: t('trials.table.trial_code'),
            cell: (info) => (
                <Link
                    href={TrialReportController.show(info.row.original.id).url}
                    className="font-medium underline underline-offset-2"
                >
                    {info.getValue()}
                </Link>
            ),
        }),
        columnHelper.accessor('product_name', {
            header: t('trials.table.product_name'),
        }),
        columnHelper.accessor('finish_good_code', {
            header: t('trials.table.finish_good_code'),
        }),
        columnHelper.accessor('product_type', {
            header: t('trials.table.product_type'),
        }),
        columnHelper.accessor('validation_scope', {
            header: t('trials.table.validation_scope'),
            cell: (info) => {
                const scopes = info.getValue() ?? [];

                if (scopes.length === 0) {
                    return '-';
                }

                return (
                    <div className="flex flex-wrap gap-1">
                        {scopes.map((scope) => (
                            <Badge key={scope} variant="secondary">
                                {scope}
                            </Badge>
                        ))}
                    </div>
                );
            },
        }),
        columnHelper.accessor('progress_status', {
            header: t('trials.table.status'),
            cell: (info) => (
                <Badge
                    variant="outline"
                    className={trialStatusBadgeClassName(
                        info.getValue(),
                        info.row.original.final_decision,
                    )}
                >
                    {trialStatusLabel(t, info.getValue())}
                </Badge>
            ),
        }),
        columnHelper.accessor('current_step', {
            header: t('trials.table.progress'),
            cell: (info) => (
                <div className="space-y-2">
                    <TrialProcessProgress trial={info.row.original} />
                    {info.row.original.progress_status === 'Draft' && (
                        <TrialStepProgress trial={info.row.original} />
                    )}
                </div>
            ),
        }),
        columnHelper.accessor('created_at', {
            header: t('trials.table.created_at'),
            cell: (info) => formatDate(info.getValue()),
        }),
        columnHelper.accessor('pending_with', {
            header: t('trials.table.pending_with'),
            cell: (info) => info.getValue() ?? '-',
        }),
        columnHelper.display({
            id: 'actions',
            header: t('trials.table.actions'),
            cell: (info) =>
                info.row.original.can_edit ? (
                    <Button asChild variant="outline" size="sm">
                        <Link href={editTrial(info.row.original.id).url}>
                            {t('trials.table.edit')}
                        </Link>
                    </Button>
                ) : (
                    '-'
                ),
        }),
    ];
}

type TrialsTableProps = {
    trials: Paginated<TrialRow>;
    url: string;
    query: Record<string, string>;
    emptyMessage?: string;
};

export function TrialsTable({
    trials,
    url,
    query,
    emptyMessage,
}: TrialsTableProps) {
    const { t, formatDate } = useTranslation();
    const columns = useMemo(() => buildColumns(t, formatDate), [t, formatDate]);
    const table = useReactTable({
        data: trials.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <Card>
            <CardContent className="space-y-4">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {flexRender(
                                            header.column.columnDef.header,
                                            header.getContext(),
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.map((row) => (
                            <TableRow key={row.id} className="align-top">
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                        {trials.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    {emptyMessage ?? t('trials.table.empty')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>

                <PaginationFooter
                    url={url}
                    query={query}
                    currentPage={trials.current_page}
                    lastPage={trials.last_page}
                    total={trials.total}
                    itemLabel={t('trials.table.item_label')}
                />
            </CardContent>
        </Card>
    );
}
