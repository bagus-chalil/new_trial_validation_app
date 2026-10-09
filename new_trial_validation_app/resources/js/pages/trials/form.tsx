import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import TrialController from '@/actions/App/Http/Controllers/TrialController';
import { Combobox } from '@/components/combobox';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MultiSelect } from '@/components/multi-select';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { TrialWizardSteps } from '@/components/trial-wizard-steps';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { dashboard } from '@/routes';

type Product = {
    id: number;
    product_name: string;
    finish_good_code: string;
};

type TrialData = {
    id: number;
    trial_code: string;
    current_step: string | null;
    attachments_count: number;
    product_id: number | null;
    product_type: string;
    validation_date: string;
    validation_category: string;
    risk_level: string;
    validation_scope: string[];
    machine_used: string[];
    estimate_qty: string;
    batch_number: string | null;
    bulk_code: string | null;
    support_team: string | null;
    initiated_person_team: string | null;
    reason: string | null;
    bom: string | null;
    progress_status: string;
    final_decision: string | null;
};

type PageProps = {
    mode: 'create' | 'edit';
    trial: TrialData | null;
    products: Product[];
    masterOptions: {
        product_type: string[];
        validation_category: string[];
        validation_scope: string[];
        machine_used: string[];
    };
    riskLevels: string[];
};

export default function TrialForm({
    mode,
    trial,
    products,
    masterOptions,
    riskLevels,
}: PageProps) {
    const { t } = useTranslation();
    const title =
        mode === 'edit'
            ? t('wizard.form.title_edit', { code: trial?.trial_code ?? '' })
            : t('wizard.form.title_new');
    const [productId, setProductId] = useState(
        trial?.product_id ? String(trial.product_id) : '',
    );
    const [validationScope, setValidationScope] = useState<string[]>(
        trial?.validation_scope ?? [],
    );
    const [machineUsed, setMachineUsed] = useState<string[]>(
        trial?.machine_used ?? [],
    );

    const selectedProduct = products.find((p) => String(p.id) === productId);

    const formProps =
        mode === 'edit' && trial
            ? TrialController.update.form(trial.id)
            : TrialController.store.form();

    return (
        <>
            <Head title={title} />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={title}
                        description={t('wizard.form.description')}
                    />
                    {mode === 'edit' && trial && (
                        <Badge
                            variant="outline"
                            className={trialStatusBadgeClassName(
                                trial.progress_status,
                                trial.final_decision,
                            )}
                        >
                            {trialStatusLabel(t, trial.progress_status)}
                        </Badge>
                    )}
                </div>

                <TrialWizardSteps currentStep={1} trial={trial} />

                <Form
                    {...formProps}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="product_id"
                                value={productId}
                            />
                            {validationScope.map((value) => (
                                <input
                                    key={value}
                                    type="hidden"
                                    name="validation_scope[]"
                                    value={value}
                                />
                            ))}
                            {machineUsed.map((value) => (
                                <input
                                    key={value}
                                    type="hidden"
                                    name="machine_used[]"
                                    value={value}
                                />
                            ))}

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('wizard.form.sections.product')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="product">
                                            {t('wizard.form.product')}
                                        </Label>
                                        <Combobox
                                            options={products.map((p) => ({
                                                value: String(p.id),
                                                label: p.product_name,
                                            }))}
                                            value={productId}
                                            onChange={setProductId}
                                            placeholder={t(
                                                'wizard.form.placeholders.product',
                                            )}
                                            searchPlaceholder={t(
                                                'wizard.form.placeholders.product_search',
                                            )}
                                            aria-invalid={Boolean(
                                                errors.product_id,
                                            )}
                                        />
                                        <InputError
                                            message={errors.product_id}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="finish_good_code">
                                            {t('report.info.fg_code')}
                                        </Label>
                                        <Input
                                            id="finish_good_code"
                                            value={
                                                selectedProduct?.finish_good_code ??
                                                ''
                                            }
                                            disabled
                                            readOnly
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="product_type">
                                            {t('report.info.product_type')}
                                        </Label>
                                        <Select
                                            name="product_type"
                                            defaultValue={trial?.product_type}
                                        >
                                            <SelectTrigger
                                                id="product_type"
                                                aria-invalid={Boolean(
                                                    errors.product_type,
                                                )}
                                                className="w-full"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        'wizard.form.placeholders.product_type',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {masterOptions.product_type.map(
                                                    (type) => (
                                                        <SelectItem
                                                            key={type}
                                                            value={type}
                                                        >
                                                            {type}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.product_type}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="validation_date">
                                            {t('report.info.validation_date')}
                                        </Label>
                                        <Input
                                            id="validation_date"
                                            name="validation_date"
                                            type="date"
                                            defaultValue={trial?.validation_date?.slice(
                                                0,
                                                10,
                                            )}
                                            aria-invalid={Boolean(
                                                errors.validation_date,
                                            )}
                                        />
                                        <InputError
                                            message={errors.validation_date}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="validation_category">
                                            {t(
                                                'report.info.validation_category',
                                            )}
                                        </Label>
                                        <Select
                                            name="validation_category"
                                            defaultValue={
                                                trial?.validation_category
                                            }
                                        >
                                            <SelectTrigger
                                                id="validation_category"
                                                aria-invalid={Boolean(
                                                    errors.validation_category,
                                                )}
                                                className="w-full"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        'wizard.form.placeholders.validation_category',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {masterOptions.validation_category.map(
                                                    (category) => (
                                                        <SelectItem
                                                            key={category}
                                                            value={category}
                                                        >
                                                            {category}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.validation_category}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="risk_level">
                                            {t('report.info.risk_level')}
                                        </Label>
                                        <Select
                                            name="risk_level"
                                            defaultValue={trial?.risk_level}
                                        >
                                            <SelectTrigger
                                                id="risk_level"
                                                aria-invalid={Boolean(
                                                    errors.risk_level,
                                                )}
                                                className="w-full"
                                            >
                                                <SelectValue
                                                    placeholder={t(
                                                        'wizard.form.placeholders.risk_level',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {riskLevels.map((level) => (
                                                    <SelectItem
                                                        key={level}
                                                        value={level}
                                                    >
                                                        {level}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.risk_level}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('wizard.form.sections.scope')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="validation_scope">
                                            {t('report.info.validation_scope')}
                                        </Label>
                                        <MultiSelect
                                            options={masterOptions.validation_scope.map(
                                                (value) => ({
                                                    value,
                                                    label: value,
                                                }),
                                            )}
                                            value={validationScope}
                                            onChange={setValidationScope}
                                            placeholder={t(
                                                'wizard.form.placeholders.validation_scope',
                                            )}
                                            searchPlaceholder={t(
                                                'wizard.form.placeholders.validation_scope_search',
                                            )}
                                            aria-invalid={Boolean(
                                                errors.validation_scope,
                                            )}
                                        />
                                        <InputError
                                            message={errors.validation_scope}
                                        />
                                    </div>

                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="machine_used">
                                            {t('report.info.machine_used')}
                                        </Label>
                                        <MultiSelect
                                            options={masterOptions.machine_used.map(
                                                (value) => ({
                                                    value,
                                                    label: value,
                                                }),
                                            )}
                                            value={machineUsed}
                                            onChange={setMachineUsed}
                                            placeholder={t(
                                                'wizard.form.placeholders.machine_used',
                                            )}
                                            searchPlaceholder={t(
                                                'wizard.form.placeholders.machine_used_search',
                                            )}
                                            aria-invalid={Boolean(
                                                errors.machine_used,
                                            )}
                                        />
                                        <InputError
                                            message={errors.machine_used}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('wizard.form.sections.batch')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="estimate_qty">
                                            {t('report.header.estimate_qty')}
                                        </Label>
                                        <Input
                                            id="estimate_qty"
                                            name="estimate_qty"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            defaultValue={trial?.estimate_qty}
                                            aria-invalid={Boolean(
                                                errors.estimate_qty,
                                            )}
                                        />
                                        <InputError
                                            message={errors.estimate_qty}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="batch_number">
                                            {t('report.header.batch_number')}
                                        </Label>
                                        <Input
                                            id="batch_number"
                                            name="batch_number"
                                            defaultValue={
                                                trial?.batch_number ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.batch_number,
                                            )}
                                        />
                                        <InputError
                                            message={errors.batch_number}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="bulk_code">
                                            {t('report.header.bulk_code')}
                                        </Label>
                                        <Input
                                            id="bulk_code"
                                            name="bulk_code"
                                            defaultValue={
                                                trial?.bulk_code ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.bulk_code,
                                            )}
                                        />
                                        <InputError
                                            message={errors.bulk_code}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="support_team">
                                            {t('report.header.support_team')}
                                        </Label>
                                        <Input
                                            id="support_team"
                                            name="support_team"
                                            defaultValue={
                                                trial?.support_team ?? ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.support_team,
                                            )}
                                        />
                                        <InputError
                                            message={errors.support_team}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="initiated_person_team">
                                            {t(
                                                'report.header.initiated_person_team',
                                            )}
                                        </Label>
                                        <Input
                                            id="initiated_person_team"
                                            name="initiated_person_team"
                                            defaultValue={
                                                trial?.initiated_person_team ??
                                                ''
                                            }
                                            aria-invalid={Boolean(
                                                errors.initiated_person_team,
                                            )}
                                        />
                                        <InputError
                                            message={
                                                errors.initiated_person_team
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('wizard.form.sections.reason')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">
                                            {t('report.header.reason')}
                                        </Label>
                                        <Textarea
                                            id="reason"
                                            name="reason"
                                            defaultValue={trial?.reason ?? ''}
                                            aria-invalid={Boolean(
                                                errors.reason,
                                            )}
                                        />
                                        <InputError message={errors.reason} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="bom">
                                            {t('report.header.bom')}
                                        </Label>
                                        <Textarea
                                            id="bom"
                                            name="bom"
                                            defaultValue={trial?.bom ?? ''}
                                            aria-invalid={Boolean(errors.bom)}
                                        />
                                        <InputError message={errors.bom} />
                                    </div>
                                </CardContent>
                            </Card>

                            <StickyActionBar>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    asChild
                                >
                                    <Link href={dashboard().url}>
                                        {t('common.actions.cancel')}
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {mode === 'edit'
                                        ? t('wizard.form.save_changes')
                                        : t('wizard.save_next')}
                                </Button>
                            </StickyActionBar>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

TrialForm.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'New Trial', href: '#' },
    ],
};
