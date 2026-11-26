import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ValidationImMasterController from '@/actions/App/Http/Controllers/ValidationImMasterController';
import { Combobox } from '@/components/combobox';
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
import { imStatusClassName } from '@/lib/im-master';
import {
    index as mastersIndex,
    show as masterShow,
} from '@/routes/validation/im-masters';
import { show as revisionShow } from '@/routes/validation/im-revisions';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    fg_code: string;
    product_name: string;
    customer: string;
    revisions_count: number;
    latest: {
        id: number;
        im_number: string;
        revision_no: number;
        status: string;
        pending_with: string;
    } | null;
    effective: {
        id: number;
        im_number: string;
        revision_no: number;
        effective_from: string | null;
    } | null;
};

type PageProps = {
    masters: Paginated<Row>;
    filters: { q: string; status: string };
    statuses: string[];
    ready: boolean;
    can: { create: boolean };
    brands: { code: string; name: string }[];
    products: { id: number; label: string; fg: string | null }[];
    issuedNumbers: { id: number; number: string; fg: string }[];
};

function CreateDialog({
    brands,
    products,
    issuedNumbers,
    onClose,
}: {
    brands: PageProps['brands'];
    products: PageProps['products'];
    issuedNumbers: PageProps['issuedNumbers'];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<'new' | 'existing'>('new');
    const [brand, setBrand] = useState('');
    const [productId, setProductId] = useState('');
    const [productPart, setProductPart] = useState('');
    const [fg, setFg] = useState('');
    const [existingId, setExistingId] = useState('');

    function pickProduct(id: string) {
        setProductId(id);
        const product = products.find((p) => String(p.id) === id);

        if (product) {
            setProductPart(product.label.split(' · ')[0] ?? product.label);
            setFg(product.fg ?? '');
        }
    }

    function pickExisting(id: string) {
        setExistingId(id);
        const number = issuedNumbers.find((n) => String(n.id) === id);

        if (number && number.fg && fg === '') {
            setFg(number.fg);
        }
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('im_master.create.title')}</DialogTitle>
                </DialogHeader>

                <Form
                    {...ValidationImMasterController.store.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm text-muted-foreground">
                                {t('im_master.create.hint')}
                            </p>

                            <div className="grid gap-2">
                                <Label>{t('im_master.create.product')}</Label>
                                <Combobox
                                    options={products.map((p) => ({
                                        value: String(p.id),
                                        label: p.label,
                                    }))}
                                    value={productId}
                                    onChange={pickProduct}
                                />
                                <input
                                    type="hidden"
                                    name="product_id"
                                    value={productId}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="fg_code">
                                    {t('im_master.fields.fg_code')}
                                </Label>
                                <Input
                                    id="fg_code"
                                    name="fg_code"
                                    value={fg}
                                    onChange={(e) => setFg(e.target.value)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('im_master.create.fg_hint')}
                                </p>
                                <InputError message={errors.fg_code} />
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        mode === 'new' ? 'default' : 'outline'
                                    }
                                    onClick={() => setMode('new')}
                                >
                                    {t('im_master.create.mode_new')}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        mode === 'existing'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setMode('existing')}
                                >
                                    {t('im_master.create.mode_existing')}
                                </Button>
                            </div>

                            {mode === 'new' ? (
                                <>
                                    <div className="grid min-w-0 gap-2">
                                        <Label>{t('numbers.im.brand')}</Label>
                                        <Combobox
                                            options={brands.map((b) => ({
                                                value: b.code,
                                                label: `${b.code} · ${b.name}`,
                                            }))}
                                            value={brand}
                                            onChange={setBrand}
                                        />
                                        <input
                                            type="hidden"
                                            name="brand_code"
                                            value={brand}
                                        />
                                        <InputError
                                            message={errors.brand_code}
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
                                        <InputError
                                            message={errors.product_part}
                                        />
                                    </div>
                                </>
                            ) : (
                                <div className="grid min-w-0 gap-2">
                                    <Label>
                                        {t('im_master.create.existing_number')}
                                    </Label>
                                    <Combobox
                                        options={issuedNumbers.map((n) => ({
                                            value: String(n.id),
                                            label: n.number,
                                        }))}
                                        value={existingId}
                                        onChange={pickExisting}
                                    />
                                    <input
                                        type="hidden"
                                        name="existing_im_number_id"
                                        value={existingId}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {t('im_master.create.existing_hint')}
                                    </p>
                                    <InputError
                                        message={errors.existing_im_number_id}
                                    />
                                </div>
                            )}

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="customer">
                                        {t('im_master.fields.customer')}
                                    </Label>
                                    <Input id="customer" name="customer" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="legacy_reference">
                                        {t('im_master.fields.legacy_reference')}
                                    </Label>
                                    <Input
                                        id="legacy_reference"
                                        name="legacy_reference"
                                    />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('im_master.create.submit')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ImMastersIndex({
    masters,
    filters,
    statuses,
    ready,
    can,
    brands,
    products,
    issuedNumbers,
}: PageProps) {
    const { t } = useTranslation();
    const url = mastersIndex().url;
    const [form, setForm] = useState(filters);
    const [creating, setCreating] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, { ...form }, { preserveState: true, replace: true });
    }

    return (
        <>
            <Head title={t('im_master.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('im_master.title')}
                        description={t('im_master.subtitle')}
                    />
                    {can.create && ready && (
                        <Button
                            className="gap-1.5"
                            onClick={() => setCreating(true)}
                        >
                            <Plus className="size-4" />
                            {t('im_master.create.title')}
                        </Button>
                    )}
                </div>

                {!ready && (
                    <Alert>
                        <AlertDescription>
                            {t('im_master.not_ready')}
                        </AlertDescription>
                    </Alert>
                )}

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setForm({ ...form, q: value })}
                    searchPlaceholder={t('im_master.list.search')}
                    onSubmit={submit}
                    onReset={() => router.get(url)}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                >
                    <FilterSelect
                        label={t('im_master.list.status')}
                        value={form.status}
                        onChange={(status) => setForm({ ...form, status })}
                        placeholder={t('im_master.list.all_status')}
                        options={statuses.map((status) => ({
                            value: status,
                            label: t(`im_master.status.${status}`),
                        }))}
                    />
                </FilterBar>

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('im_master.fields.fg_code')}
                                    </TableHead>
                                    <TableHead>
                                        {t('im_master.list.product')}
                                    </TableHead>
                                    <TableHead>
                                        {t('im_master.list.effective')}
                                    </TableHead>
                                    <TableHead>
                                        {t('im_master.list.latest')}
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {masters.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-center text-muted-foreground"
                                        >
                                            {t('im_master.list.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                                {masters.data.map((m) => (
                                    <TableRow key={m.id}>
                                        <TableCell className="font-mono text-xs">
                                            {m.fg_code}
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {m.product_name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {m.customer}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {m.effective ? (
                                                <Link
                                                    href={revisionShow(
                                                        m.effective.id,
                                                    )}
                                                    className="font-mono text-xs hover:underline"
                                                >
                                                    {m.effective.im_number}
                                                </Link>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">
                                                    {t(
                                                        'im_master.list.no_effective',
                                                    )}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {m.latest && (
                                                <div className="space-y-1">
                                                    <Badge
                                                        className={imStatusClassName(
                                                            m.latest.status,
                                                        )}
                                                    >
                                                        {t(
                                                            `im_master.status.${m.latest.status}`,
                                                        )}
                                                    </Badge>
                                                    <div className="text-xs text-muted-foreground">
                                                        Rev.{' '}
                                                        {m.latest.revision_no}
                                                        {m.latest.pending_with
                                                            ? ` · ${m.latest.pending_with}`
                                                            : ''}
                                                    </div>
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={masterShow(m.id)}>
                                                    {t('im_master.list.open')}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <PaginationFooter
                            url={url}
                            query={{ ...filters }}
                            currentPage={masters.current_page}
                            lastPage={masters.last_page}
                            total={masters.total}
                            itemLabel={t('trials.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>

            {creating && (
                <CreateDialog
                    brands={brands}
                    products={products}
                    issuedNumbers={issuedNumbers}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}

ImMastersIndex.layout = {
    breadcrumbs: [{ title: 'IM Master', href: mastersIndex() }],
};
