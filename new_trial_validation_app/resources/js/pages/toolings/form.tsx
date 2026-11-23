import { Form, Head, Link } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import ToolingController from '@/actions/App/Http/Controllers/ToolingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { toolingProductTypeLabel } from '@/lib/tooling';
import { index as toolingsIndex, show } from '@/routes/projects/toolings';

type LinkItem = { title: string; url: string };

type ToolingData = {
    id: number;
    npd_no: string;
    request_date: string | null;
    requested_by_name: string;
    client_code: string;
    product_name: string;
    marketing: string;
    packdev: string;
    product_type: string;
    packaging_type: string;
    quantity_po: string;
    launch_date: string;
    dimension_packaging: string | null;
    material_packaging: string | null;
    bom_primary: string | null;
    pm_ref_detail: string | null;
    is_critical: boolean;
    links: LinkItem[];
};

type PageProps = {
    tooling: ToolingData | null;
    defaults: {
        npd_no: string;
        request_date: string;
        requested_by_name: string;
        packdev: string;
    } | null;
    options: { product_types: string[] };
};

function Field({
    label,
    error,
    children,
    className,
}: {
    readonly label: string;
    readonly error?: string;
    readonly children: ReactNode;
    readonly className?: string;
}) {
    return (
        <div className={`grid min-w-0 gap-2 ${className ?? ''}`}>
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function ToolingForm({ tooling, defaults, options }: PageProps) {
    const { t } = useTranslation();
    const isEdit = tooling !== null;
    const [links, setLinks] = useState<LinkItem[]>(tooling?.links ?? []);

    const value = (key: keyof ToolingData): string => {
        const v = tooling?.[key];

        if (typeof v === 'string') {
            return v;
        }

        if (!isEdit && defaults && key in defaults) {
            return defaults[key as keyof typeof defaults];
        }

        return '';
    };

    const formProps = isEdit
        ? ToolingController.update.form(tooling.id)
        : ToolingController.store.form();

    const textFields = [
        'dimension_packaging',
        'material_packaging',
        'bom_primary',
        'pm_ref_detail',
    ] as const;

    return (
        <>
            <Head
                title={t(
                    isEdit
                        ? 'toolings.form.edit_title'
                        : 'toolings.form.create_title',
                )}
            />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <Heading
                    title={t(
                        isEdit
                            ? 'toolings.form.edit_title'
                            : 'toolings.form.create_title',
                    )}
                    description={
                        isEdit
                            ? undefined
                            : t('toolings.form.after_create_hint')
                    }
                />

                <Form {...formProps} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('toolings.form.section_request')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <Field
                                        label={t('toolings.fields.npd_no')}
                                        error={errors.npd_no}
                                    >
                                        <Input
                                            name="npd_no"
                                            defaultValue={value('npd_no')}
                                        />
                                    </Field>
                                    <Field
                                        label={`${t('toolings.fields.request_date')} *`}
                                        error={errors.request_date}
                                    >
                                        <Input
                                            type="date"
                                            name="request_date"
                                            required
                                            defaultValue={value('request_date')}
                                        />
                                    </Field>
                                    <Field
                                        label={t(
                                            'toolings.fields.requested_by_name',
                                        )}
                                        error={errors.requested_by_name}
                                    >
                                        <Input
                                            name="requested_by_name"
                                            defaultValue={value(
                                                'requested_by_name',
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        label={`${t('toolings.fields.product_name')} *`}
                                        error={errors.product_name}
                                        className="sm:col-span-2"
                                    >
                                        <Input
                                            name="product_name"
                                            required
                                            defaultValue={value('product_name')}
                                        />
                                    </Field>
                                    <Field
                                        label={t('toolings.fields.client_code')}
                                        error={errors.client_code}
                                    >
                                        <Input
                                            name="client_code"
                                            defaultValue={value('client_code')}
                                        />
                                    </Field>
                                    <Field
                                        label={`${t('toolings.fields.product_type')} *`}
                                        error={errors.product_type}
                                    >
                                        <Select
                                            name="product_type"
                                            defaultValue={
                                                value('product_type') ||
                                                'skin_care'
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {options.product_types.map(
                                                    (type) => (
                                                        <SelectItem
                                                            key={type}
                                                            value={type}
                                                        >
                                                            {toolingProductTypeLabel(
                                                                t,
                                                                type,
                                                            )}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field
                                        label={t('toolings.fields.marketing')}
                                        error={errors.marketing}
                                    >
                                        <Input
                                            name="marketing"
                                            defaultValue={value('marketing')}
                                        />
                                    </Field>
                                    <Field
                                        label={t('toolings.fields.packdev')}
                                        error={errors.packdev}
                                    >
                                        <Input
                                            name="packdev"
                                            defaultValue={value('packdev')}
                                        />
                                    </Field>
                                    <Field
                                        label={t('toolings.fields.quantity_po')}
                                        error={errors.quantity_po}
                                    >
                                        <Input
                                            name="quantity_po"
                                            defaultValue={value('quantity_po')}
                                        />
                                    </Field>
                                    <Field
                                        label={t('toolings.fields.launch_date')}
                                        error={errors.launch_date}
                                    >
                                        <Input
                                            name="launch_date"
                                            defaultValue={value('launch_date')}
                                        />
                                    </Field>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('toolings.form.section_packaging')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label={`${t('toolings.fields.packaging_type')} *`}
                                        error={errors.packaging_type}
                                    >
                                        <Input
                                            name="packaging_type"
                                            required
                                            defaultValue={value(
                                                'packaging_type',
                                            )}
                                        />
                                    </Field>
                                    <div className="grid content-start gap-2">
                                        <Label className="flex items-center gap-2">
                                            <Checkbox
                                                name="is_critical"
                                                value="1"
                                                defaultChecked={
                                                    tooling?.is_critical ??
                                                    false
                                                }
                                            />
                                            {t('toolings.fields.is_critical')}
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            {t(
                                                'toolings.fields.is_critical_hint',
                                            )}
                                        </p>
                                    </div>
                                    {textFields.map((field) => (
                                        <Field
                                            key={field}
                                            label={t(
                                                `toolings.fields.${field}`,
                                            )}
                                            error={errors[field]}
                                        >
                                            <Textarea
                                                name={field}
                                                rows={3}
                                                defaultValue={value(field)}
                                            />
                                        </Field>
                                    ))}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('toolings.form.section_refs')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {links.map((link, i) => (
                                        <div
                                            key={i}
                                            className="grid gap-2 sm:grid-cols-[1fr_2fr_auto] sm:items-start"
                                        >
                                            <Input
                                                name={`links[${i}][title]`}
                                                placeholder={t(
                                                    'toolings.fields.link_title',
                                                )}
                                                value={link.title}
                                                onChange={(e) =>
                                                    setLinks((prev) =>
                                                        prev.map((l, j) =>
                                                            j === i
                                                                ? {
                                                                      ...l,
                                                                      title: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : l,
                                                        ),
                                                    )
                                                }
                                            />
                                            <div className="grid gap-1">
                                                <Input
                                                    name={`links[${i}][url]`}
                                                    type="url"
                                                    placeholder="https://"
                                                    value={link.url}
                                                    onChange={(e) =>
                                                        setLinks((prev) =>
                                                            prev.map((l, j) =>
                                                                j === i
                                                                    ? {
                                                                          ...l,
                                                                          url: e
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : l,
                                                            ),
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `links.${i}.url` as keyof typeof errors
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t(
                                                    'toolings.form.remove_link',
                                                )}
                                                onClick={() =>
                                                    setLinks((prev) =>
                                                        prev.filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setLinks((prev) => [
                                                ...prev,
                                                { title: '', url: '' },
                                            ])
                                        }
                                    >
                                        <Plus />
                                        {t('toolings.form.add_link')}
                                    </Button>
                                </CardContent>
                            </Card>

                            <div className="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            isEdit
                                                ? show(tooling.id).url
                                                : toolingsIndex().url
                                        }
                                    >
                                        {t('toolings.cancel')}
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('toolings.form.submit')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
