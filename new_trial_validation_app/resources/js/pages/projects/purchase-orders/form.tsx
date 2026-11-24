import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProjectPurchaseOrderController from '@/actions/App/Http/Controllers/ProjectPurchaseOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/project';
import { show as projectShow } from '@/routes/projects';
import { show as poShow } from '@/routes/projects/purchase-orders';

type ItemRow = {
    description: string;
    qty: number | string;
    unit: string;
    unit_price: number | string;
    due_date: string | null;
};

type Order = {
    id: number | null;
    po_no: string;
    po_date: string;
    quotation_id: number | null;
    vendor_name: string;
    currency: string;
    vendor_address: string;
    attn: string;
    tel: string;
    email: string;
    quotation_no: string;
    subject: string;
    tax_percent: number;
    terms: string;
    delivery: string;
    lead_time: string;
    note: string;
    ship_name: string;
    ship_address: string;
    ship_tel: string;
    ship_attn: string;
    use_signature: boolean;
    items: ItemRow[];
};

type Vendor = {
    name: string;
    contact: string;
    phone: string;
    email: string;
    address: string;
};

type PageProps = {
    project: { id: number; code: string; name: string; pic_name: string };
    order: Order;
    options: {
        quotations: {
            id: number;
            vendor_name: string;
            quotation_no: string;
            amount: number;
            currency: string;
        }[];
        vendors: Vendor[];
    };
};

const NO_QUOTATION = '__none__';

export default function PurchaseOrderForm({
    project,
    order,
    options,
}: PageProps) {
    const { t, intlLocale } = useTranslation();
    const editing = order.id !== null;

    // Vendor block is controlled so a saved vendor / quotation can pre-fill it.
    const [vendor, setVendor] = useState({
        name: order.vendor_name,
        address: order.vendor_address,
        attn: order.attn,
        tel: order.tel,
        email: order.email,
        quotationNo: order.quotation_no,
        currency: order.currency,
    });
    const [quotationId, setQuotationId] = useState(
        order.quotation_id ? String(order.quotation_id) : NO_QUOTATION,
    );
    const [tax, setTax] = useState(String(order.tax_percent));
    const [useSignature, setUseSignature] = useState(order.use_signature);
    const [items, setItems] = useState<ItemRow[]>(
        order.items.length > 0
            ? order.items
            : [
                  {
                      description: '',
                      qty: 1,
                      unit: 'Unit',
                      unit_price: 0,
                      due_date: null,
                  },
              ],
    );

    const sub = items.reduce(
        (a, i) => a + (Number(i.qty) || 0) * (Number(i.unit_price) || 0),
        0,
    );
    const taxAmount = Math.round((sub * (Number(tax) || 0)) / 100);

    function patchItem(index: number, patch: Partial<ItemRow>) {
        setItems((prev) =>
            prev.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    function pickVendor(name: string) {
        const found = options.vendors.find((v) => v.name === name);
        setVendor((prev) => ({
            ...prev,
            name,
            ...(found
                ? {
                      address: found.address,
                      attn: found.contact,
                      tel: found.phone,
                      email: found.email,
                  }
                : {}),
        }));
    }

    function pickQuotation(value: string) {
        setQuotationId(value);
        const q = options.quotations.find((x) => String(x.id) === value);

        if (!q) {
            return;
        }

        pickVendor(q.vendor_name);
        setVendor((prev) => ({
            ...prev,
            name: q.vendor_name,
            quotationNo: q.quotation_no,
            currency: q.currency,
        }));
    }

    const formProps = editing
        ? ProjectPurchaseOrderController.update.form(order.id as number)
        : ProjectPurchaseOrderController.store.form(project.id);

    const title = t(
        editing
            ? 'purchase_orders.form.edit_title'
            : 'purchase_orders.form.create_title',
    );

    return (
        <>
            <Head title={title} />

            <div className="space-y-6 p-4">
                <Heading
                    title={title}
                    description={
                        editing
                            ? `${project.code} ${project.name}`
                            : t('purchase_orders.form.create_description', {
                                  project: `${project.code} ${project.name}`,
                              })
                    }
                />

                <Form {...formProps} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.sections.header')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="po_no">
                                            {t('purchase_orders.fields.po_no')}
                                        </Label>
                                        <Input
                                            id="po_no"
                                            name="po_no"
                                            defaultValue={order.po_no}
                                            required
                                        />
                                        <InputError message={errors.po_no} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="po_date">
                                            {t(
                                                'purchase_orders.fields.po_date',
                                            )}
                                        </Label>
                                        <Input
                                            id="po_date"
                                            name="po_date"
                                            type="date"
                                            defaultValue={order.po_date}
                                            required
                                        />
                                        <InputError message={errors.po_date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>
                                            {t(
                                                'purchase_orders.fields.quotation',
                                            )}
                                        </Label>
                                        <Select
                                            value={quotationId}
                                            onValueChange={pickQuotation}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    value={NO_QUOTATION}
                                                >
                                                    {t(
                                                        'purchase_orders.fields.quotation_none',
                                                    )}
                                                </SelectItem>
                                                {options.quotations.map((q) => (
                                                    <SelectItem
                                                        key={q.id}
                                                        value={String(q.id)}
                                                    >
                                                        {q.vendor_name}
                                                        {q.quotation_no
                                                            ? ` · ${q.quotation_no}`
                                                            : ''}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="quotation_id"
                                            value={
                                                quotationId === NO_QUOTATION
                                                    ? ''
                                                    : quotationId
                                            }
                                        />
                                        <InputError
                                            message={errors.quotation_id}
                                        />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="subject">
                                            {t(
                                                'purchase_orders.fields.subject',
                                            )}
                                        </Label>
                                        <Input
                                            id="subject"
                                            name="subject"
                                            defaultValue={order.subject}
                                        />
                                        <InputError message={errors.subject} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">
                                            {t(
                                                'purchase_orders.fields.currency',
                                            )}
                                        </Label>
                                        <Input
                                            id="currency"
                                            name="currency"
                                            value={vendor.currency}
                                            onChange={(e) =>
                                                setVendor({
                                                    ...vendor,
                                                    currency: e.target.value,
                                                })
                                            }
                                            required
                                        />
                                        <InputError message={errors.currency} />
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="grid gap-6 lg:grid-cols-2">
                                <Card>
                                    <CardHeader>
                                        <h3 className="font-semibold">
                                            {t(
                                                'purchase_orders.sections.vendor',
                                            )}
                                        </h3>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_name">
                                                {t(
                                                    'purchase_orders.fields.vendor',
                                                )}
                                            </Label>
                                            <Input
                                                id="vendor_name"
                                                name="vendor_name"
                                                list="po-vendors"
                                                value={vendor.name}
                                                onChange={(e) =>
                                                    pickVendor(e.target.value)
                                                }
                                                placeholder={t(
                                                    'purchase_orders.form.pick_vendor',
                                                )}
                                                required
                                            />
                                            <datalist id="po-vendors">
                                                {options.vendors.map((v) => (
                                                    <option
                                                        key={v.name}
                                                        value={v.name}
                                                    />
                                                ))}
                                            </datalist>
                                            <InputError
                                                message={errors.vendor_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_address">
                                                {t(
                                                    'purchase_orders.fields.address',
                                                )}
                                            </Label>
                                            <Textarea
                                                id="vendor_address"
                                                name="vendor_address"
                                                rows={3}
                                                value={vendor.address}
                                                onChange={(e) =>
                                                    setVendor({
                                                        ...vendor,
                                                        address: e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="attn">
                                                    {t(
                                                        'purchase_orders.fields.attn',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="attn"
                                                    name="attn"
                                                    value={vendor.attn}
                                                    onChange={(e) =>
                                                        setVendor({
                                                            ...vendor,
                                                            attn: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="tel">
                                                    {t(
                                                        'purchase_orders.fields.tel',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="tel"
                                                    name="tel"
                                                    value={vendor.tel}
                                                    onChange={(e) =>
                                                        setVendor({
                                                            ...vendor,
                                                            tel: e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="email">
                                                    {t(
                                                        'purchase_orders.fields.email',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="email"
                                                    name="email"
                                                    value={vendor.email}
                                                    onChange={(e) =>
                                                        setVendor({
                                                            ...vendor,
                                                            email: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="quotation_no">
                                                {t(
                                                    'purchase_orders.fields.quotation_no',
                                                )}
                                            </Label>
                                            <Input
                                                id="quotation_no"
                                                name="quotation_no"
                                                value={vendor.quotationNo}
                                                onChange={(e) =>
                                                    setVendor({
                                                        ...vendor,
                                                        quotationNo:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <h3 className="font-semibold">
                                            {t('purchase_orders.sections.ship')}
                                        </h3>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="ship_name">
                                                {t(
                                                    'purchase_orders.fields.ship_name',
                                                )}
                                            </Label>
                                            <Input
                                                id="ship_name"
                                                name="ship_name"
                                                defaultValue={order.ship_name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="ship_address">
                                                {t(
                                                    'purchase_orders.fields.ship_address',
                                                )}
                                            </Label>
                                            <Textarea
                                                id="ship_address"
                                                name="ship_address"
                                                rows={3}
                                                defaultValue={
                                                    order.ship_address
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="ship_tel">
                                                    {t(
                                                        'purchase_orders.fields.ship_tel',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="ship_tel"
                                                    name="ship_tel"
                                                    defaultValue={
                                                        order.ship_tel
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2">
                                                <Label htmlFor="ship_attn">
                                                    {t(
                                                        'purchase_orders.fields.ship_attn',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="ship_attn"
                                                    name="ship_attn"
                                                    defaultValue={
                                                        order.ship_attn
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <Card>
                                <CardHeader className="flex-row items-center justify-between">
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.sections.items')}
                                    </h3>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setItems([
                                                ...items,
                                                {
                                                    description: '',
                                                    qty: 1,
                                                    unit: 'Unit',
                                                    unit_price: 0,
                                                    due_date: null,
                                                },
                                            ])
                                        }
                                    >
                                        <Plus />
                                        {t('purchase_orders.items.add')}
                                    </Button>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <InputError message={errors.items} />
                                    {items.map((item, i) => (
                                        <div
                                            key={i}
                                            className="grid gap-3 rounded-md border p-3 md:grid-cols-12"
                                        >
                                            <div className="grid min-w-0 gap-2 md:col-span-12">
                                                <Label>
                                                    {i + 1}.{' '}
                                                    {t(
                                                        'purchase_orders.items.description',
                                                    )}
                                                </Label>
                                                <Textarea
                                                    name={`items[${i}][description]`}
                                                    rows={2}
                                                    value={item.description}
                                                    onChange={(e) =>
                                                        patchItem(i, {
                                                            description:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `items.${i}.description`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2 md:col-span-2">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.items.qty',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`items[${i}][qty]`}
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    value={item.qty}
                                                    onChange={(e) =>
                                                        patchItem(i, {
                                                            qty: e.target.value,
                                                        })
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[`items.${i}.qty`]
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2 md:col-span-2">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.items.unit',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`items[${i}][unit]`}
                                                    value={item.unit}
                                                    onChange={(e) =>
                                                        patchItem(i, {
                                                            unit: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2 md:col-span-3">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.items.unit_price',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`items[${i}][unit_price]`}
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    value={item.unit_price}
                                                    onChange={(e) =>
                                                        patchItem(i, {
                                                            unit_price:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `items.${i}.unit_price`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="grid min-w-0 gap-2 md:col-span-3">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.items.due_date',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`items[${i}][due_date]`}
                                                    type="date"
                                                    value={item.due_date ?? ''}
                                                    onChange={(e) =>
                                                        patchItem(i, {
                                                            due_date:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-end justify-end md:col-span-2">
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={t(
                                                        'purchase_orders.items.remove',
                                                    )}
                                                    disabled={
                                                        items.length === 1
                                                    }
                                                    onClick={() =>
                                                        setItems(
                                                            items.filter(
                                                                (_, n) =>
                                                                    n !== i,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}

                                    <div className="ml-auto grid max-w-sm gap-1 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                {t('purchase_orders.items.sub')}
                                            </span>
                                            <span>
                                                {formatMoney(
                                                    sub,
                                                    vendor.currency || 'IDR',
                                                    intlLocale,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="text-muted-foreground">
                                                {t(
                                                    'purchase_orders.fields.tax',
                                                )}
                                            </span>
                                            <Input
                                                name="tax_percent"
                                                type="number"
                                                step="any"
                                                min="0"
                                                max="100"
                                                className="w-24 text-right"
                                                value={tax}
                                                onChange={(e) =>
                                                    setTax(e.target.value)
                                                }
                                            />
                                        </div>
                                        <InputError
                                            message={errors.tax_percent}
                                        />
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                {t('purchase_orders.items.tax')}
                                            </span>
                                            <span>
                                                {formatMoney(
                                                    taxAmount,
                                                    vendor.currency || 'IDR',
                                                    intlLocale,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex justify-between border-t pt-1 font-semibold">
                                            <span>
                                                {t(
                                                    'purchase_orders.items.grand',
                                                )}
                                            </span>
                                            <span>
                                                {formatMoney(
                                                    sub + taxAmount,
                                                    vendor.currency || 'IDR',
                                                    intlLocale,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.sections.terms')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-3">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="terms">
                                            {t('purchase_orders.fields.terms')}
                                        </Label>
                                        <Input
                                            id="terms"
                                            name="terms"
                                            defaultValue={order.terms}
                                        />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="lead_time">
                                            {t(
                                                'purchase_orders.fields.lead_time',
                                            )}
                                        </Label>
                                        <Input
                                            id="lead_time"
                                            name="lead_time"
                                            defaultValue={order.lead_time}
                                        />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="delivery">
                                            {t(
                                                'purchase_orders.fields.delivery',
                                            )}
                                        </Label>
                                        <Input
                                            id="delivery"
                                            name="delivery"
                                            defaultValue={order.delivery}
                                        />
                                    </div>
                                    <div className="grid gap-2 md:col-span-3">
                                        <Label htmlFor="note">
                                            {t('purchase_orders.fields.note')}
                                        </Label>
                                        <Textarea
                                            id="note"
                                            name="note"
                                            rows={2}
                                            defaultValue={order.note}
                                        />
                                    </div>
                                    <div className="flex items-start gap-2 md:col-span-3">
                                        <Checkbox
                                            id="use_signature"
                                            checked={useSignature}
                                            onCheckedChange={(v) =>
                                                setUseSignature(v === true)
                                            }
                                        />
                                        <input
                                            type="hidden"
                                            name="use_signature"
                                            value={useSignature ? '1' : '0'}
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor="use_signature">
                                                {t(
                                                    'purchase_orders.fields.use_signature',
                                                )}
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                {t(
                                                    'purchase_orders.fields.use_signature_hint',
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <div className="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            editing
                                                ? poShow(order.id as number)
                                                : projectShow(project.id)
                                        }
                                    >
                                        {t('purchase_orders.cancel')}
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('purchase_orders.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
