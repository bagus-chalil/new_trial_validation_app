import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import TrialArchiveController from '@/actions/App/Http/Controllers/Admin/TrialArchiveController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { index as archiveIndex } from '@/routes/admin/archive';
import type { Paginated } from '@/types';

type TrialItem = {
    id: number;
    trial_code: string;
    product_name: string;
    product_type: string;
    progress_status: string;
    final_decision: string | null;
    archived_at: string;
    archived_by_user: { id: number; name: string; email: string } | null;
};

type Filters = {
    q: string;
};

type PageProps = {
    trials: Paginated<TrialItem>;
    filters: Filters;
};

export default function AdminArchiveIndex({ trials, filters }: PageProps) {
    const { t } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(archiveIndex().url, form, {
            preserveState: true,
            replace: true,
        });
    }

    function reset() {
        router.get(archiveIndex().url);
    }

    return (
        <>
            <Head title={t('master_data.archive.head_title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('master_data.archive.title')}
                    description={t('master_data.archive.description')}
                />

                <Card>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
                        >
                            <div className="grid gap-2 lg:col-span-2">
                                <Label htmlFor="q">
                                    {t('master_data.archive.search')}
                                </Label>
                                <Input
                                    id="q"
                                    placeholder={t(
                                        'master_data.archive.search_placeholder',
                                    )}
                                    value={form.q}
                                    onChange={(e) =>
                                        setForm({ ...form, q: e.target.value })
                                    }
                                />
                            </div>
                            <div className="flex items-end gap-2 lg:col-span-3">
                                <Button type="submit">
                                    {t('master_data.actions.filter')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={reset}
                                >
                                    {t('common.actions.reset')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'master_data.archive.column_trial_code',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'master_data.archive.column_product',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'master_data.archive.column_product_type',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'master_data.archive.column_archived_by',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'master_data.archive.column_archived_at',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.archive.column_status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('master_data.columns.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {trials.data.map((trial) => (
                                    <TableRow key={trial.id}>
                                        <TableCell className="font-medium">
                                            {trial.trial_code}
                                        </TableCell>
                                        <TableCell>
                                            {trial.product_name}
                                        </TableCell>
                                        <TableCell>
                                            {trial.product_type}
                                        </TableCell>
                                        <TableCell>
                                            {trial.archived_by_user?.name ??
                                                '-'}
                                        </TableCell>
                                        <TableCell>
                                            {trial.archived_at}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={trialStatusBadgeClassName(
                                                    trial.progress_status,
                                                    trial.final_decision,
                                                )}
                                            >
                                                {trialStatusLabel(
                                                    t,
                                                    trial.progress_status,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        {t(
                                                            'master_data.actions.unarchive',
                                                        )}
                                                    </Button>
                                                }
                                                title={t(
                                                    'master_data.archive.unarchive_title',
                                                )}
                                                description={t(
                                                    'master_data.archive.unarchive_description',
                                                    { code: trial.trial_code },
                                                )}
                                                confirmLabel={t(
                                                    'master_data.actions.unarchive',
                                                )}
                                                confirmVariant="default"
                                                formProps={TrialArchiveController.destroy.form(
                                                    trial.id,
                                                )}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {trials.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('master_data.archive.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={archiveIndex().url}
                            query={filters}
                            currentPage={trials.current_page}
                            lastPage={trials.last_page}
                            total={trials.total}
                            itemLabel={t('master_data.archive.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminArchiveIndex.layout = {
    breadcrumbs: [{ title: 'Archive', href: archiveIndex() }],
};
