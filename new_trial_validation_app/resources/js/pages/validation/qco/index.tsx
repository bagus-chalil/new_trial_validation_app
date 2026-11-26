import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ValidationQcoController from '@/actions/App/Http/Controllers/ValidationQcoController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationFooter } from '@/components/pagination-footer';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { show as journeyShow } from '@/routes/journey';
import { index as qcoIndex } from '@/routes/validation/qco';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    number: string;
    product_name: string;
    client: string;
    sales_month: string | null;
    qty_po: number | null;
    notes: string | null;
    status: string;
    void_reason: string | null;
    issued_by_name: string;
    issued_at: string | null;
    case_id: number | null;
    case_no: string | null;
};

type Filters = { q: string; status: string };

type PageProps = {
    numbers: Paginated<Row>;
    filters: Filters;
    products: { id: number; label: string }[];
    ready: boolean;
    can: { issue: boolean };
};

function statusClassName(status: string): string {
    return status === 'Void'
        ? 'bg-muted text-muted-foreground line-through'
        : 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
}

function IssueDialog({
    products,
    open,
    onOpenChange,
}: {
    products: PageProps['products'];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [productId, setProductId] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('numbers.qco.issue')}</DialogTitle>
                </DialogHeader>
                <Form
                    {...ValidationQcoController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="product_id"
                                value={productId}
                            />
                            <div className="grid min-w-0 gap-2">
                                <Label>{t('numbers.qco.product')}</Label>
                                <Combobox
                                    options={products.map((p) => ({
                                        value: String(p.id),
                                        label: p.label,
                                    }))}
                                    value={productId}
                                    onChange={setProductId}
                                />
                                <InputError message={errors.product_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="product_name">
                                    {t('numbers.qco.product_name')}
                                </Label>
                                <Input id="product_name" name="product_name" />
                                <p className="text-xs text-muted-foreground">
                                    {t('numbers.qco.product_name_hint')}
                                </p>
                                <InputError message={errors.product_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="client">
                                    {t('numbers.qco.client')}
                                </Label>
                                <Input id="client" name="client" />
                                <InputError message={errors.client} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="sales_month">
                                        {t('numbers.qco.sales_month')}
                                    </Label>
                                    <Input
                                        id="sales_month"
                                        name="sales_month"
                                        type="month"
                                    />
                                    <InputError message={errors.sales_month} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="qty_po">
                                        {t('numbers.qco.qty_po')}
                                    </Label>
                                    <Input
                                        id="qty_po"
                                        name="qty_po"
                                        type="number"
                                        min={0}
                                    />
                                    <InputError message={errors.qty_po} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notes">
                                    {t('numbers.qco.notes')}
                                </Label>
                                <Textarea id="notes" name="notes" />
                                <InputError message={errors.notes} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('numbers.qco.issue')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function QcoIndex({
    numbers,
    filters,
    products,
    ready,
    can,
}: PageProps) {
    const { t, formatDateOnly } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const [issuing, setIssuing] = useState(false);
    const url = qcoIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={t('numbers.qco.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('numbers.qco.title')}
                        description={t('numbers.qco.description')}
                    />
                    {can.issue && ready && (
                        <Button
                            className="gap-1.5"
                            onClick={() => setIssuing(true)}
                        >
                            <Plus className="size-4" />
                            {t('numbers.qco.issue')}
                        </Button>
                    )}
                </div>

                {!ready && (
                    <Alert>
                        <AlertDescription>
                            {t('numbers.common.not_ready')}
                        </AlertDescription>
                    </Alert>
                )}
                {ready && !can.issue && (
                    <p className="text-sm text-muted-foreground">
                        {t('numbers.common.read_only')}
                    </p>
                )}

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('numbers.qco.search_placeholder')}
                    onSubmit={submit}
                    onReset={() => router.get(url)}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('numbers.common.status')}
                        value={form.status}
                        onChange={(status) => setForm({ ...form, status })}
                        placeholder={t('numbers.common.all_status')}
                        options={['Issued', 'Void'].map((status) => ({
                            value: status,
                            label: t(`numbers.status.${status}`),
                        }))}
                    />
                </FilterBar>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('numbers.qco.number')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.qco.product_name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.qco.client')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.qco.sales_month')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.qco.qty_po')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.common.case')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.common.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.common.issued_by')}
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {numbers.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            {row.number}
                                        </TableCell>
                                        <TableCell>
                                            {row.product_name}
                                        </TableCell>
                                        <TableCell>{row.client}</TableCell>
                                        <TableCell>
                                            {row.sales_month ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.qty_po ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.case_id ? (
                                                <Link
                                                    href={
                                                        journeyShow(row.case_id)
                                                            .url
                                                    }
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    {row.case_no}
                                                </Link>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="secondary"
                                                className={statusClassName(
                                                    row.status,
                                                )}
                                                title={
                                                    row.void_reason ?? undefined
                                                }
                                            >
                                                {t(
                                                    `numbers.status.${row.status}`,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {row.issued_by_name}
                                            {row.issued_at &&
                                                ` · ${formatDateOnly(row.issued_at)}`}
                                        </TableCell>
                                        <TableCell>
                                            {can.issue &&
                                                row.status !== 'Void' && (
                                                    <ConfirmDialog
                                                        trigger={
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                {t(
                                                                    'numbers.common.void',
                                                                )}
                                                            </Button>
                                                        }
                                                        title={t(
                                                            'numbers.qco.void_title',
                                                        )}
                                                        description={`${row.number} · ${t('numbers.common.void_hint')}`}
                                                        confirmLabel={t(
                                                            'numbers.common.void',
                                                        )}
                                                        formProps={ValidationQcoController.void.form(
                                                            row.id,
                                                        )}
                                                    >
                                                        {({ errors }) => (
                                                            <div className="grid gap-2">
                                                                <Label
                                                                    htmlFor={`reason_${row.id}`}
                                                                >
                                                                    {t(
                                                                        'numbers.common.void_reason',
                                                                    )}
                                                                </Label>
                                                                <Input
                                                                    id={`reason_${row.id}`}
                                                                    name="reason"
                                                                />
                                                                <InputError
                                                                    message={
                                                                        errors.reason
                                                                    }
                                                                />
                                                            </div>
                                                        )}
                                                    </ConfirmDialog>
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {numbers.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={9}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('numbers.qco.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={{ ...filters }}
                            currentPage={numbers.current_page}
                            lastPage={numbers.last_page}
                            total={numbers.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>

            {issuing && (
                <IssueDialog
                    products={products}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setIssuing(false);
                        }
                    }}
                />
            )}
        </>
    );
}

QcoIndex.layout = {
    breadcrumbs: [{ title: 'QCO', href: qcoIndex() }],
};
