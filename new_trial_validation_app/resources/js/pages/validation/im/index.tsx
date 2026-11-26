import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ValidationImController from '@/actions/App/Http/Controllers/ValidationImController';
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
import { useTranslation } from '@/hooks/use-translation';
import { show as journeyShow } from '@/routes/journey';
import { index as imIndex } from '@/routes/validation/im';
import { index as mastersIndex } from '@/routes/validation/im-masters';
import { show as revisionShow } from '@/routes/validation/im-revisions';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    number: string;
    brand_code: string;
    version_no: number;
    replaces_number: string | null;
    revision_reason: string | null;
    issue_date: string | null;
    fg_codes: string[];
    line_machine: string;
    customer: string;
    customer_ref: string;
    status: string;
    void_reason: string | null;
    issued_by_name: string;
    case_id: number | null;
    case_no: string | null;
    sheet_revision_id: number | null;
};

type Filters = { q: string; status: string };

type Product = {
    id: number;
    label: string;
    name: string;
    fg: string | null;
};

type PageProps = {
    numbers: Paginated<Row>;
    filters: Filters;
    statuses: string[];
    brands: { code: string; name: string }[];
    products: Product[];
    ready: boolean;
    can: { issue: boolean; master: boolean };
};

function statusClassName(status: string): string {
    switch (status) {
        case 'Active':
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
        case 'Superseded':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
        default:
            return 'bg-muted text-muted-foreground line-through';
    }
}

// Mirrors App\Support\ImNumberFormat::compose for the live preview only; the
// server always builds the real number.
function previewNumber(brand: string, product: string): string {
    const part = product.toUpperCase().replace(/\s+/g, ' ').trim();

    return brand && part ? `IM-${brand}-${part}` : '';
}

function IssueDialog({
    brands,
    products,
    open,
    onOpenChange,
}: {
    brands: PageProps['brands'];
    products: Product[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [brand, setBrand] = useState('');
    const [productPart, setProductPart] = useState('');
    const [productId, setProductId] = useState('');
    const [fg, setFg] = useState('');
    const [adding, setAdding] = useState(false);

    function pickProduct(id: string) {
        setProductId(id);
        const product = products.find((p) => String(p.id) === id);

        if (product) {
            setProductPart(product.name);
            setFg(product.fg ?? '');
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('numbers.im.issue')}</DialogTitle>
                </DialogHeader>

                <div className="space-y-2 rounded-md border border-dashed p-3">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-sm font-medium">
                            {t('numbers.im.brand_add')}
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setAdding((v) => !v)}
                        >
                            {adding ? '−' : '+'}
                        </Button>
                    </div>
                    {adding && (
                        <Form
                            {...ValidationImController.storeBrand.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setAdding(false)}
                            className="grid gap-2 sm:grid-cols-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-1">
                                        <Label htmlFor="new_code">
                                            {t('numbers.im.brand_code')}
                                        </Label>
                                        <Input
                                            id="new_code"
                                            name="code"
                                            maxLength={10}
                                        />
                                        <InputError message={errors.code} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="new_name">
                                            {t('numbers.im.brand_name')}
                                        </Label>
                                        <Input id="new_name" name="name" />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                        className="sm:col-span-2"
                                    >
                                        {t('numbers.common.save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </div>

                <Form
                    {...ValidationImController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="brand_code"
                                value={brand}
                            />
                            <input
                                type="hidden"
                                name="product_id"
                                value={productId}
                            />
                            <div className="grid min-w-0 gap-2">
                                <Label>{t('numbers.im.brand')}</Label>
                                <Combobox
                                    options={brands.map((b) => ({
                                        value: b.code,
                                        label: `${b.code} · ${b.name}`,
                                    }))}
                                    value={brand}
                                    onChange={setBrand}
                                    aria-invalid={Boolean(errors.brand_code)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('numbers.im.brand_hint')}
                                </p>
                                <InputError message={errors.brand_code} />
                            </div>
                            <div className="grid min-w-0 gap-2">
                                <Label>{t('numbers.im.product')}</Label>
                                <Combobox
                                    options={products.map((p) => ({
                                        value: String(p.id),
                                        label: p.label,
                                    }))}
                                    value={productId}
                                    onChange={pickProduct}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="product_part">
                                    {t('numbers.im.product_part')}
                                </Label>
                                <Input
                                    id="product_part"
                                    name="product_part"
                                    value={productPart}
                                    onChange={(e) =>
                                        setProductPart(e.target.value)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('numbers.im.product_part_hint')}
                                </p>
                                <InputError message={errors.product_part} />
                            </div>
                            {previewNumber(brand, productPart) && (
                                <div className="rounded-md bg-muted p-3 text-sm">
                                    <span className="text-muted-foreground">
                                        {t('numbers.im.preview')}:{' '}
                                    </span>
                                    <span className="font-mono font-medium">
                                        {previewNumber(brand, productPart)}
                                    </span>
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="fg_codes_text">
                                    {t('numbers.im.fg_codes')}
                                </Label>
                                <Input
                                    id="fg_codes_text"
                                    value={fg}
                                    onChange={(e) => setFg(e.target.value)}
                                />
                                {fg
                                    .split(',')
                                    .map((c) => c.trim())
                                    .filter(Boolean)
                                    .map((code) => (
                                        <input
                                            key={code}
                                            type="hidden"
                                            name="fg_codes[]"
                                            value={code}
                                        />
                                    ))}
                                <p className="text-xs text-muted-foreground">
                                    {t('numbers.im.fg_codes_hint')}
                                </p>
                                <InputError message={errors.fg_codes} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="line_machine">
                                    {t('numbers.im.line_machine')}
                                </Label>
                                <Input id="line_machine" name="line_machine" />
                                <InputError message={errors.line_machine} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="customer">
                                        {t('numbers.im.customer')}
                                    </Label>
                                    <Input id="customer" name="customer" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="customer_ref">
                                        {t('numbers.im.customer_ref')}
                                    </Label>
                                    <Input
                                        id="customer_ref"
                                        name="customer_ref"
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="issue_date">
                                    {t('numbers.im.issue_date')}
                                </Label>
                                <Input
                                    id="issue_date"
                                    name="issue_date"
                                    type="date"
                                />
                                <InputError message={errors.issue_date} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('numbers.im.issue')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ImIndex({
    numbers,
    filters,
    statuses,
    brands,
    products,
    ready,
    can,
}: PageProps) {
    const { t, formatDateOnly } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const [issuing, setIssuing] = useState(false);
    const url = imIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={t('numbers.im.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('numbers.im.title')}
                        description={t('numbers.im.description')}
                    />
                    {can.issue && ready && (
                        <Button
                            className="gap-1.5"
                            onClick={() => setIssuing(true)}
                        >
                            <Plus className="size-4" />
                            {t('numbers.im.issue')}
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
                    searchPlaceholder={t('numbers.im.search_placeholder')}
                    onSubmit={submit}
                    onReset={() => router.get(url)}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('numbers.common.status')}
                        value={form.status}
                        onChange={(status) => setForm({ ...form, status })}
                        placeholder={t('numbers.common.all_status')}
                        options={statuses.map((status) => ({
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
                                        {t('numbers.im.number')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.im.customer')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.im.fg_codes')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.im.line_machine')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.im.replaces')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.im.issue_date')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.common.case')}
                                    </TableHead>
                                    <TableHead>
                                        {t('numbers.common.status')}
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
                                            {row.customer || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.fg_codes.join(', ') || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.line_machine || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.replaces_number ? (
                                                <span
                                                    title={
                                                        row.revision_reason ??
                                                        undefined
                                                    }
                                                >
                                                    {row.replaces_number}
                                                </span>
                                            ) : (
                                                t('numbers.im.first_version')
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {row.issue_date
                                                ? formatDateOnly(row.issue_date)
                                                : '-'}
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
                                            {(row.sheet_revision_id !== null ||
                                                (can.master &&
                                                    row.status ===
                                                        'Active')) && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    {row.sheet_revision_id !==
                                                    null ? (
                                                        <Link
                                                            href={revisionShow(
                                                                row.sheet_revision_id,
                                                            )}
                                                        >
                                                            {t(
                                                                'numbers.im.open_sheet',
                                                            )}
                                                        </Link>
                                                    ) : (
                                                        <Link
                                                            href={mastersIndex({
                                                                query: {
                                                                    number: row.id,
                                                                },
                                                            })}
                                                        >
                                                            {t(
                                                                'numbers.im.create_sheet',
                                                            )}
                                                        </Link>
                                                    )}
                                                </Button>
                                            )}
                                            {can.issue && (
                                                <div className="flex gap-2">
                                                    {row.status ===
                                                        'Active' && (
                                                        <ConfirmDialog
                                                            trigger={
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                >
                                                                    {t(
                                                                        'numbers.im.revise',
                                                                    )}
                                                                </Button>
                                                            }
                                                            title={t(
                                                                'numbers.im.revise_title',
                                                            )}
                                                            description={
                                                                row.number
                                                            }
                                                            confirmLabel={t(
                                                                'numbers.im.revise',
                                                            )}
                                                            confirmVariant="default"
                                                            formProps={ValidationImController.revise.form(
                                                                row.id,
                                                            )}
                                                        >
                                                            {({ errors }) => (
                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={`rev_${row.id}`}
                                                                    >
                                                                        {t(
                                                                            'numbers.im.revision_reason',
                                                                        )}
                                                                    </Label>
                                                                    <Input
                                                                        id={`rev_${row.id}`}
                                                                        name="revision_reason"
                                                                    />
                                                                    <InputError
                                                                        message={
                                                                            errors.revision_reason
                                                                        }
                                                                    />
                                                                    <Label
                                                                        htmlFor={`revdate_${row.id}`}
                                                                    >
                                                                        {t(
                                                                            'numbers.im.issue_date',
                                                                        )}
                                                                    </Label>
                                                                    <Input
                                                                        id={`revdate_${row.id}`}
                                                                        name="issue_date"
                                                                        type="date"
                                                                    />
                                                                </div>
                                                            )}
                                                        </ConfirmDialog>
                                                    )}
                                                    {row.status !== 'Void' && (
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
                                                                'numbers.im.void_title',
                                                            )}
                                                            description={`${row.number} · ${t('numbers.common.void_hint')}`}
                                                            confirmLabel={t(
                                                                'numbers.common.void',
                                                            )}
                                                            formProps={ValidationImController.void.form(
                                                                row.id,
                                                            )}
                                                        >
                                                            {({ errors }) => (
                                                                <div className="grid gap-2">
                                                                    <Label
                                                                        htmlFor={`void_${row.id}`}
                                                                    >
                                                                        {t(
                                                                            'numbers.common.void_reason',
                                                                        )}
                                                                    </Label>
                                                                    <Input
                                                                        id={`void_${row.id}`}
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
                                                </div>
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
                                            {t('numbers.im.empty')}
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
                    brands={brands}
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

ImIndex.layout = {
    breadcrumbs: [{ title: 'IM', href: imIndex() }],
};
