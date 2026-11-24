import { Head, Link } from '@inertiajs/react';
import { FileText, Pencil, Trash2 } from 'lucide-react';
import ProjectPurchaseOrderController from '@/actions/App/Http/Controllers/ProjectPurchaseOrderController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/project';
import { show as projectShow } from '@/routes/projects';
import {
    edit as poEdit,
    index as poIndex,
    pdf as poPdf,
} from '@/routes/projects/purchase-orders';

type Order = {
    id: number;
    po_no: string;
    po_date: string | null;
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
    created_by_name: string;
    items: {
        description: string;
        qty: number;
        unit: string;
        unit_price: number;
        due_date: string | null;
    }[];
};

type PageProps = {
    project: { id: number; code: string; name: string; pic_name: string };
    order: Order;
    totals: { sub: number; tax: number; total: number };
    documentLanguage: string;
    canEdit: boolean;
};

function Field({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm break-words whitespace-pre-line">
                {value || '-'}
            </dd>
        </div>
    );
}

export default function PurchaseOrderShow({
    project,
    order,
    totals,
    documentLanguage,
    canEdit,
}: PageProps) {
    const { t, formatDateOnly, intlLocale } = useTranslation();
    const money = (n: number) => formatMoney(n, order.currency, intlLocale);

    return (
        <>
            <Head title={order.po_no} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={order.po_no}
                        description={t('purchase_orders.show.description', {
                            project: `${project.code} ${project.name}`,
                        })}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={poIndex()}>
                                {t('purchase_orders.back')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={projectShow(project.id)}>
                                {project.code}
                            </Link>
                        </Button>
                        {canEdit && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={poEdit(order.id)}>
                                        <Pencil />
                                        {t('purchase_orders.edit')}
                                    </Link>
                                </Button>
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="outline">
                                            <Trash2 />
                                            {t('purchase_orders.delete')}
                                        </Button>
                                    }
                                    title={t(
                                        'purchase_orders.show.delete_title',
                                    )}
                                    description={t(
                                        'purchase_orders.show.delete_description',
                                        { no: order.po_no },
                                    )}
                                    confirmLabel={t('purchase_orders.delete')}
                                    confirmVariant="destructive"
                                    formProps={ProjectPurchaseOrderController.destroy.form(
                                        order.id,
                                    )}
                                />
                            </>
                        )}
                        <Button asChild>
                            <a
                                href={poPdf(order.id).url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText />
                                {t('purchase_orders.print_pdf')}
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('purchase_orders.sections.header')}
                            </h3>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label={t('purchase_orders.fields.po_no')}
                                    value={order.po_no}
                                />
                                <Field
                                    label={t('purchase_orders.fields.po_date')}
                                    value={
                                        order.po_date
                                            ? formatDateOnly(order.po_date)
                                            : null
                                    }
                                />
                                <Field
                                    label={t(
                                        'purchase_orders.fields.created_by',
                                    )}
                                    value={order.created_by_name}
                                />
                                <Field
                                    label={t('purchase_orders.fields.subject')}
                                    value={order.subject}
                                />
                                <Field
                                    label={t(
                                        'purchase_orders.fields.quotation_no',
                                    )}
                                    value={order.quotation_no}
                                />
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        {t(
                                            'purchase_orders.fields.document_language',
                                        )}
                                    </dt>
                                    <dd>
                                        <Badge variant="secondary">
                                            {documentLanguage.toUpperCase()}
                                        </Badge>
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('purchase_orders.items.grand')}
                            </h3>
                        </CardHeader>
                        <CardContent className="grid gap-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    {t('purchase_orders.items.sub')}
                                </span>
                                <span>{money(totals.sub)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    {t('purchase_orders.items.tax')}{' '}
                                    {order.tax_percent}%
                                </span>
                                <span>{money(totals.tax)}</span>
                            </div>
                            <div className="flex justify-between border-t pt-1 text-base font-semibold">
                                <span>{t('purchase_orders.items.grand')}</span>
                                <span>{money(totals.total)}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('purchase_orders.sections.vendor')}
                            </h3>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('purchase_orders.fields.vendor')}
                                    value={order.vendor_name}
                                />
                                <Field
                                    label={t('purchase_orders.fields.address')}
                                    value={order.vendor_address}
                                />
                                <Field
                                    label={t('purchase_orders.fields.attn')}
                                    value={order.attn}
                                />
                                <Field
                                    label={t('purchase_orders.fields.tel')}
                                    value={order.tel}
                                />
                                <Field
                                    label={t('purchase_orders.fields.email')}
                                    value={order.email}
                                />
                            </dl>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('purchase_orders.sections.ship')}
                            </h3>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t(
                                        'purchase_orders.fields.ship_name',
                                    )}
                                    value={order.ship_name}
                                />
                                <Field
                                    label={t(
                                        'purchase_orders.fields.ship_address',
                                    )}
                                    value={order.ship_address}
                                />
                                <Field
                                    label={t('purchase_orders.fields.ship_tel')}
                                    value={order.ship_tel}
                                />
                                <Field
                                    label={t(
                                        'purchase_orders.fields.ship_attn',
                                    )}
                                    value={order.ship_attn}
                                />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <h3 className="font-semibold">
                            {t('purchase_orders.sections.items')}
                        </h3>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>#</TableHead>
                                    <TableHead>
                                        {t('purchase_orders.items.description')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('purchase_orders.items.qty')}
                                    </TableHead>
                                    <TableHead>
                                        {t('purchase_orders.items.unit')}
                                    </TableHead>
                                    <TableHead>
                                        {t('purchase_orders.items.due_date')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('purchase_orders.items.unit_price')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('purchase_orders.items.line_total')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {order.items.map((item, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{i + 1}</TableCell>
                                        <TableCell className="whitespace-pre-line">
                                            {item.description}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {item.qty}
                                        </TableCell>
                                        <TableCell>{item.unit}</TableCell>
                                        <TableCell>
                                            {item.due_date
                                                ? formatDateOnly(item.due_date)
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {money(item.unit_price)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {money(item.qty * item.unit_price)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <h3 className="font-semibold">
                            {t('purchase_orders.sections.terms')}
                        </h3>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label={t('purchase_orders.fields.terms')}
                                value={order.terms}
                            />
                            <Field
                                label={t('purchase_orders.fields.lead_time')}
                                value={order.lead_time}
                            />
                            <Field
                                label={t('purchase_orders.fields.delivery')}
                                value={order.delivery}
                            />
                            <div className="sm:col-span-3">
                                <Field
                                    label={t('purchase_orders.fields.note')}
                                    value={order.note}
                                />
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
