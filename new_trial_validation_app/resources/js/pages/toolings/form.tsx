import { Form, Head, Link } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
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
import { cn } from '@/lib/utils';
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
    uploadLimitMb?: number;
};

const STEP_1_FIELDS = [
    'npd_no',
    'request_date',
    'requested_by_name',
    'product_name',
    'client_code',
    'product_type',
    'marketing',
    'packdev',
    'quantity_po',
    'launch_date',
];
const STEP_2_FIELDS = [
    'packaging_type',
    'is_critical',
    'dimension_packaging',
    'material_packaging',
    'bom_primary',
    'pm_ref_detail',
];

/** Fields that must be filled before the user may leave each step. */
const REQUIRED_BY_STEP: Record<number, string[]> = {
    1: [
        'npd_no',
        'request_date',
        'requested_by_name',
        'client_code',
        'product_name',
        'product_type',
    ],
    2: ['packaging_type', 'bom_primary'],
};
const REQUIRED_FIELDS = Object.values(REQUIRED_BY_STEP).flat();
const ATTACHMENT_DEFAULT = 'please see attachment';

/** After a failed save, jump to the first step that holds an invalid field. */
function StepJump({
    errors,
    onJump,
}: {
    errors: Record<string, string>;
    onJump: (step: number) => void;
}) {
    const keys = Object.keys(errors).join('|');

    useEffect(() => {
        if (keys === '') {
            return;
        }

        const list = keys.split('|');
        const has = (fields: string[]) => list.some((k) => fields.includes(k));

        onJump(has(STEP_1_FIELDS) ? 1 : has(STEP_2_FIELDS) ? 2 : 3);
    }, [keys, onJump]);

    return null;
}

function Stepper({
    step,
    labels,
    onSelect,
}: {
    step: number;
    labels: string[];
    onSelect: (step: number) => void;
}) {
    return (
        <ol className="grid grid-cols-3 gap-2">
            {labels.map((label, i) => {
                const n = i + 1;
                const done = n < step;
                const active = n === step;

                return (
                    <li key={label}>
                        <button
                            type="button"
                            onClick={() => onSelect(n)}
                            aria-current={active ? 'step' : undefined}
                            className={cn(
                                'flex w-full items-center gap-2 rounded-lg border p-2 text-left text-sm transition-colors sm:p-3',
                                active
                                    ? 'border-brand bg-brand/5 font-medium'
                                    : 'hover:bg-muted/50',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs',
                                    active &&
                                        'border-brand bg-brand text-white',
                                    done &&
                                        'border-emerald-500 bg-emerald-500 text-white',
                                )}
                            >
                                {done ? <Check className="size-3.5" /> : n}
                            </span>
                            <span className="min-w-0 truncate">{label}</span>
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}

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

export default function ToolingForm({
    tooling,
    defaults,
    options,
    uploadLimitMb = 100,
}: PageProps) {
    const { t } = useTranslation();
    const isEdit = tooling !== null;
    const [links, setLinks] = useState<LinkItem[]>(tooling?.links ?? []);
    const [step, setStep] = useState(1);

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

    const [filled, setFilled] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            REQUIRED_FIELDS.map((key) => [
                key,
                key === 'product_type'
                    ? value('product_type') || 'skin_care'
                    : value(key as keyof ToolingData),
            ]),
        ),
    );

    const stepComplete = (n: number): boolean =>
        (REQUIRED_BY_STEP[n] ?? []).every(
            (key) => (filled[key] ?? '').trim() !== '',
        );
    const canReach = (n: number): boolean =>
        [1, 2].filter((s) => s < n).every(stepComplete);

    const trackRequired = (name: string, val: string) => {
        if (REQUIRED_FIELDS.includes(name)) {
            setFilled((prev) => ({ ...prev, [name]: val }));
        }
    };

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

                <Form
                    {...formProps}
                    noValidate
                    className="space-y-6"
                    onInput={(e) => {
                        const el = e.target as HTMLInputElement;
                        trackRequired(el.name, el.value);
                    }}
                    onKeyDown={(e) => {
                        if (
                            e.key === 'Enter' &&
                            (e.target as HTMLElement).tagName === 'INPUT'
                        ) {
                            e.preventDefault();
                        }
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <StepJump errors={errors} onJump={setStep} />
                            <Stepper
                                step={step}
                                onSelect={(n) => {
                                    if (n <= step || canReach(n)) {
                                        setStep(n);
                                    }
                                }}
                                labels={[
                                    t('toolings.form.step_request'),
                                    t('toolings.form.step_packaging'),
                                    t('toolings.form.step_attachments'),
                                ]}
                            />

                            <div
                                className={cn(
                                    'space-y-6',
                                    step !== 1 && 'hidden',
                                )}
                            >
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            {t('toolings.form.section_request')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <Field
                                            label={`${t('toolings.fields.npd_no')} *`}
                                            error={errors.npd_no}
                                        >
                                            <Input
                                                name="npd_no"
                                                required
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
                                                defaultValue={value(
                                                    'request_date',
                                                )}
                                            />
                                        </Field>
                                        <Field
                                            label={`${t('toolings.fields.requested_by_name')} *`}
                                            error={errors.requested_by_name}
                                        >
                                            <Input
                                                name="requested_by_name"
                                                required
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
                                                defaultValue={value(
                                                    'product_name',
                                                )}
                                            />
                                        </Field>
                                        <Field
                                            label={`${t('toolings.fields.client_code')} *`}
                                            error={errors.client_code}
                                        >
                                            <Input
                                                name="client_code"
                                                required
                                                defaultValue={value(
                                                    'client_code',
                                                )}
                                            />
                                        </Field>
                                        <Field
                                            label={`${t('toolings.fields.product_type')} *`}
                                            error={errors.product_type}
                                        >
                                            <Select
                                                name="product_type"
                                                onValueChange={(v) =>
                                                    trackRequired(
                                                        'product_type',
                                                        v,
                                                    )
                                                }
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
                                            label={t(
                                                'toolings.fields.marketing',
                                            )}
                                            error={errors.marketing}
                                        >
                                            <Input
                                                name="marketing"
                                                defaultValue={value(
                                                    'marketing',
                                                )}
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
                                            label={t(
                                                'toolings.fields.quantity_po',
                                            )}
                                            error={errors.quantity_po}
                                        >
                                            <Input
                                                name="quantity_po"
                                                defaultValue={value(
                                                    'quantity_po',
                                                )}
                                            />
                                        </Field>
                                        <Field
                                            label={t(
                                                'toolings.fields.launch_date',
                                            )}
                                            error={errors.launch_date}
                                        >
                                            <Input
                                                name="launch_date"
                                                defaultValue={value(
                                                    'launch_date',
                                                )}
                                            />
                                        </Field>
                                    </CardContent>
                                </Card>
                            </div>

                            <div
                                className={cn(
                                    'space-y-6',
                                    step !== 2 && 'hidden',
                                )}
                            >
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            {t(
                                                'toolings.form.section_packaging',
                                            )}
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
                                                {t(
                                                    'toolings.fields.is_critical',
                                                )}
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
                                                label={
                                                    t(
                                                        'toolings.fields.' +
                                                            field,
                                                    ) +
                                                    (field === 'bom_primary'
                                                        ? ' *'
                                                        : '')
                                                }
                                                error={errors[field]}
                                            >
                                                <Textarea
                                                    name={field}
                                                    rows={3}
                                                    required={
                                                        field === 'bom_primary'
                                                    }
                                                    defaultValue={
                                                        value(field) ||
                                                        (!isEdit &&
                                                        (field ===
                                                            'dimension_packaging' ||
                                                            field ===
                                                                'material_packaging')
                                                            ? ATTACHMENT_DEFAULT
                                                            : '')
                                                    }
                                                />
                                            </Field>
                                        ))}
                                    </CardContent>
                                </Card>
                            </div>

                            <div
                                className={cn(
                                    'space-y-6',
                                    step !== 3 && 'hidden',
                                )}
                            >
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
                                                                prev.map(
                                                                    (l, j) =>
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
                                                                (_, j) =>
                                                                    j !== i,
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

                                {!isEdit && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                {t(
                                                    'toolings.form.section_drawings',
                                                )}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'toolings.form.drawings_hint',
                                                    {
                                                        max: uploadLimitMb,
                                                    },
                                                )}
                                            </p>
                                            <Input
                                                type="file"
                                                name="drawings[]"
                                                multiple
                                            />
                                            <InputError
                                                message={
                                                    errors.drawings ??
                                                    errors['drawings.0']
                                                }
                                            />
                                        </CardContent>
                                    </Card>
                                )}
                            </div>

                            {step < 3 && !stepComplete(step) && (
                                <p className="text-sm text-muted-foreground">
                                    {t('toolings.form.complete_to_continue')}
                                </p>
                            )}

                            <div className="flex flex-wrap items-center justify-between gap-2">
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
                                <div className="flex gap-2">
                                    {step > 1 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setStep(step - 1)}
                                        >
                                            <ChevronLeft />
                                            {t('toolings.form.back')}
                                        </Button>
                                    )}
                                    {step < 3 ? (
                                        <Button
                                            type="button"
                                            disabled={!stepComplete(step)}
                                            onClick={() => setStep(step + 1)}
                                        >
                                            {t('toolings.form.next')}
                                            <ChevronRight />
                                        </Button>
                                    ) : (
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing || !canReach(3)
                                            }
                                        >
                                            {t('toolings.form.submit')}
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
