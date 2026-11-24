import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ProjectPurchaseOrderController from '@/actions/App/Http/Controllers/ProjectPurchaseOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import { index as poIndex } from '@/routes/projects/purchase-orders';

type Signer = { label: string; name: string; title: string; image: string };

type Template = {
    company: string;
    doc_code: string;
    logo: string;
    address: string;
    contact: string;
    title: string;
    pattern: string;
    date_format: string;
    lang: string;
    tax: number;
    terms: string;
    lead_time: string;
    delivery: string;
    ship_name: string;
    ship_address: string;
    ship_tel: string;
    ship_attn: string;
    billing: string;
    notice: string;
    signers: Signer[];
};

type PageProps = {
    template: Template;
    dateFormats: string[];
    languages: string[];
    preview: { number: string };
};

/** Image picker that keeps the picture as a small data URL in a hidden input. */
function ImagePicker({
    name,
    value,
    onChange,
    maxKb,
    label,
}: {
    name: string;
    value: string;
    onChange: (value: string) => void;
    maxKb: number;
    label: string;
}) {
    const { t } = useTranslation();
    const [error, setError] = useState('');

    function pick(file: File | undefined) {
        setError('');

        if (!file) {
            return;
        }

        if (file.size > maxKb * 1024) {
            setError(
                t('purchase_orders.template.image_too_large', { kb: maxKb }),
            );

            return;
        }

        const reader = new FileReader();
        reader.onload = () => onChange(String(reader.result ?? ''));
        reader.readAsDataURL(file);
    }

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <input type="hidden" name={name} value={value} />
            {value ? (
                <img
                    src={value}
                    alt=""
                    className="max-h-16 w-fit rounded border bg-white p-1"
                />
            ) : null}
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    type="file"
                    accept="image/png,image/jpeg"
                    className="max-w-xs"
                    aria-label={t('purchase_orders.template.upload_image')}
                    onChange={(e) => pick(e.target.files?.[0])}
                />
                {value && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => onChange('')}
                    >
                        {t('purchase_orders.template.remove_image')}
                    </Button>
                )}
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function PurchaseOrderTemplate({
    template,
    dateFormats,
    languages,
    preview,
}: PageProps) {
    const { t } = useTranslation();
    const [logo, setLogo] = useState(template.logo);
    const [signers, setSigners] = useState<Signer[]>(template.signers);
    const [lang, setLang] = useState(template.lang);
    const [dateFormat, setDateFormat] = useState(template.date_format);

    function patchSigner(index: number, patch: Partial<Signer>) {
        setSigners((prev) =>
            prev.map((s, i) => (i === index ? { ...s, ...patch } : s)),
        );
    }

    const text = (
        id: keyof Template,
        label: string,
        errors: Record<string, string>,
        multiline = false,
        rows = 3,
    ) => (
        <div className="grid min-w-0 gap-2">
            <Label htmlFor={id}>{label}</Label>
            {multiline ? (
                <Textarea
                    id={id}
                    name={id}
                    rows={rows}
                    defaultValue={String(template[id] ?? '')}
                />
            ) : (
                <Input
                    id={id}
                    name={id}
                    defaultValue={String(template[id] ?? '')}
                />
            )}
            <InputError message={errors[id]} />
        </div>
    );

    return (
        <>
            <Head title={t('purchase_orders.template.head_title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={t('purchase_orders.template.title')}
                        description={t('purchase_orders.template.description')}
                    />
                    <Button variant="outline" asChild>
                        <Link href={poIndex()}>
                            {t('purchase_orders.back')}
                        </Link>
                    </Button>
                </div>

                <Form
                    {...ProjectPurchaseOrderController.updateTemplate.form()}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.template.company')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    {text(
                                        'company',
                                        t('purchase_orders.template.company'),
                                        errors,
                                    )}
                                    {text(
                                        'doc_code',
                                        t('purchase_orders.template.doc_code'),
                                        errors,
                                    )}
                                    {text(
                                        'title',
                                        t(
                                            'purchase_orders.template.document_title',
                                        ),
                                        errors,
                                    )}
                                    <ImagePicker
                                        name="logo"
                                        value={logo}
                                        onChange={setLogo}
                                        maxKb={200}
                                        label={`${t('purchase_orders.template.logo')} (${t('purchase_orders.template.logo_hint')})`}
                                    />
                                    {text(
                                        'address',
                                        t('purchase_orders.template.address'),
                                        errors,
                                        true,
                                    )}
                                    {text(
                                        'contact',
                                        t('purchase_orders.template.contact'),
                                        errors,
                                        true,
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.template.pattern')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="pattern">
                                            {t(
                                                'purchase_orders.template.pattern',
                                            )}
                                        </Label>
                                        <Input
                                            id="pattern"
                                            name="pattern"
                                            defaultValue={template.pattern}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'purchase_orders.template.pattern_hint',
                                            )}{' '}
                                            {t(
                                                'purchase_orders.template.next_number',
                                                { no: preview.number },
                                            )}
                                        </p>
                                        <InputError message={errors.pattern} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>
                                            {t(
                                                'purchase_orders.template.date_format',
                                            )}
                                        </Label>
                                        <Select
                                            value={dateFormat}
                                            onValueChange={setDateFormat}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {dateFormats.map((f) => (
                                                    <SelectItem
                                                        key={f}
                                                        value={f}
                                                    >
                                                        {f}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="date_format"
                                            value={dateFormat}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>
                                            {t(
                                                'purchase_orders.template.language',
                                            )}
                                        </Label>
                                        <Select
                                            value={lang}
                                            onValueChange={setLang}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {languages.map((l) => (
                                                    <SelectItem
                                                        key={l}
                                                        value={l}
                                                    >
                                                        {l.toUpperCase()}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="lang"
                                            value={lang}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.template.defaults')}
                                    </h3>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-3">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="tax">
                                            {t('purchase_orders.template.tax')}
                                        </Label>
                                        <Input
                                            id="tax"
                                            name="tax"
                                            type="number"
                                            step="any"
                                            min="0"
                                            max="100"
                                            defaultValue={template.tax}
                                        />
                                        <InputError message={errors.tax} />
                                    </div>
                                    {text(
                                        'terms',
                                        t('purchase_orders.fields.terms'),
                                        errors,
                                    )}
                                    {text(
                                        'lead_time',
                                        t('purchase_orders.fields.lead_time'),
                                        errors,
                                    )}
                                    {text(
                                        'delivery',
                                        t('purchase_orders.fields.delivery'),
                                        errors,
                                    )}
                                    {text(
                                        'ship_name',
                                        t('purchase_orders.fields.ship_name'),
                                        errors,
                                    )}
                                    {text(
                                        'ship_tel',
                                        t('purchase_orders.fields.ship_tel'),
                                        errors,
                                    )}
                                    {text(
                                        'ship_address',
                                        t(
                                            'purchase_orders.fields.ship_address',
                                        ),
                                        errors,
                                        true,
                                    )}
                                    {text(
                                        'ship_attn',
                                        t('purchase_orders.fields.ship_attn'),
                                        errors,
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="grid gap-4 pt-6 md:grid-cols-2">
                                    {text(
                                        'billing',
                                        t('purchase_orders.template.billing'),
                                        errors,
                                        true,
                                        6,
                                    )}
                                    <div className="grid gap-2">
                                        {text(
                                            'notice',
                                            t(
                                                'purchase_orders.template.notice',
                                            ),
                                            errors,
                                            true,
                                            6,
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'purchase_orders.template.notice_hint',
                                            )}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <h3 className="font-semibold">
                                        {t('purchase_orders.template.signers')}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'purchase_orders.template.signer_hint',
                                        )}
                                    </p>
                                </CardHeader>
                                <CardContent className="grid gap-4 lg:grid-cols-3">
                                    {signers.map((s, i) => (
                                        <div
                                            key={i}
                                            className="grid content-start gap-3 rounded-md border p-3"
                                        >
                                            <p className="text-sm font-medium">
                                                {t(
                                                    'purchase_orders.template.signer',
                                                    { n: i + 1 },
                                                )}
                                            </p>
                                            <div className="grid gap-2">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.template.signer_label',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`signers[${i}][label]`}
                                                    value={s.label}
                                                    onChange={(e) =>
                                                        patchSigner(i, {
                                                            label: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.template.signer_name',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`signers[${i}][name]`}
                                                    value={s.name}
                                                    onChange={(e) =>
                                                        patchSigner(i, {
                                                            name: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label>
                                                    {t(
                                                        'purchase_orders.template.signer_title',
                                                    )}
                                                </Label>
                                                <Input
                                                    name={`signers[${i}][title]`}
                                                    value={s.title}
                                                    onChange={(e) =>
                                                        patchSigner(i, {
                                                            title: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <ImagePicker
                                                name={`signers[${i}][image]`}
                                                value={s.image}
                                                onChange={(image) =>
                                                    patchSigner(i, { image })
                                                }
                                                maxKb={48}
                                                label={t(
                                                    'purchase_orders.template.signer_image',
                                                )}
                                            />
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {t('purchase_orders.template.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
