import { Form, Link } from '@inertiajs/react';
import { Unlink } from 'lucide-react';
import { useState } from 'react';
import ValidationCaseToolingController from '@/actions/App/Http/Controllers/ValidationCaseToolingController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { show as toolingShow } from '@/routes/projects/toolings';
import { index as plansIndex } from '@/routes/validation/plans';

export type CaseAssessment = {
    id: number;
    receive_date: string | null;
    client: string;
    sales_month: string | null;
    qty_po: number | null;
    packaging_type: string;
    validation_scope: string[];
    recommended_machine: string;
    qco_no: string;
    qco_link: string;
    pm_received_date: string | null;
    pm_na: boolean;
    bulk_received_date: string | null;
    bulk_na: boolean;
    risk_level: string;
    created_by_name: string;
};

export type CaseTooling = {
    id: number;
    npd_no: string;
    product_name: string;
    status: string;
};

export type CasePlan = {
    id: number;
    week_start: string;
    status: string;
    ppic_note: string | null;
};

type Props = {
    caseId: number;
    assessment: CaseAssessment | null;
    toolings: CaseTooling[];
    availableToolings: { id: number; label: string }[];
    plans: CasePlan[];
    canManageCase: boolean;
};

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-muted-foreground">{label}</p>
            <p>{value || '-'}</p>
        </div>
    );
}

export function CaseExtras({
    caseId,
    assessment,
    toolings,
    availableToolings,
    plans,
    canManageCase,
}: Props) {
    const { t, formatDateOnly } = useTranslation();
    const [toolingId, setToolingId] = useState('');
    const na = t('assessment.na');
    const dateOrNa = (isNa: boolean, date: string | null) =>
        isNa ? na : date ? formatDateOnly(date) : '-';

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {t('assessment.journey.assessment_title')}
                    </CardTitle>
                </CardHeader>
                <CardContent className="text-sm">
                    {assessment === null ? (
                        <p className="text-muted-foreground">
                            {t('assessment.journey.assessment_empty')}
                        </p>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Field
                                label={t('assessment.form.client')}
                                value={assessment.client}
                            />
                            <Field
                                label={t('assessment.form.receive_date')}
                                value={
                                    assessment.receive_date
                                        ? formatDateOnly(
                                              assessment.receive_date,
                                          )
                                        : ''
                                }
                            />
                            <Field
                                label={t('assessment.form.sales_month')}
                                value={assessment.sales_month ?? ''}
                            />
                            <Field
                                label={t('assessment.form.qty_po')}
                                value={
                                    assessment.qty_po === null
                                        ? ''
                                        : String(assessment.qty_po)
                                }
                            />
                            <Field
                                label={t('assessment.form.packaging_type')}
                                value={assessment.packaging_type}
                            />
                            <Field
                                label={t('assessment.form.validation_scope')}
                                value={assessment.validation_scope.join(', ')}
                            />
                            <Field
                                label={t('assessment.form.recommended_machine')}
                                value={assessment.recommended_machine}
                            />
                            <div>
                                <p className="text-muted-foreground">
                                    {t('assessment.form.risk_level')}
                                </p>
                                <Badge variant="secondary">
                                    {t(
                                        `assessment.risk.${assessment.risk_level}`,
                                    )}
                                </Badge>
                            </div>
                            <Field
                                label={t('assessment.form.pm_received_date')}
                                value={dateOrNa(
                                    assessment.pm_na,
                                    assessment.pm_received_date,
                                )}
                            />
                            <Field
                                label={t('assessment.form.bulk_received_date')}
                                value={dateOrNa(
                                    assessment.bulk_na,
                                    assessment.bulk_received_date,
                                )}
                            />
                            <div>
                                <p className="text-muted-foreground">
                                    {t('assessment.form.qco_no')}
                                </p>
                                {assessment.qco_link ? (
                                    <a
                                        href={assessment.qco_link}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary underline-offset-4 hover:underline"
                                    >
                                        {assessment.qco_no || 'QCO'}
                                    </a>
                                ) : (
                                    <p>{assessment.qco_no || '-'}</p>
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('assessment.tooling.title')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    {toolings.map((tooling) => (
                        <div
                            key={tooling.id}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <Link
                                href={toolingShow(tooling.id).url}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                {tooling.npd_no || `#${tooling.id}`}
                            </Link>
                            <span>{tooling.product_name}</span>
                            <Badge variant="secondary">{tooling.status}</Badge>
                            {canManageCase && (
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="gap-1"
                                        >
                                            <Unlink className="size-4" />
                                            {t('assessment.tooling.unlink')}
                                        </Button>
                                    }
                                    title={t('assessment.tooling.unlink')}
                                    description={tooling.npd_no}
                                    confirmLabel={t(
                                        'assessment.tooling.unlink',
                                    )}
                                    formProps={ValidationCaseToolingController.destroy.form(
                                        { case: caseId, tooling: tooling.id },
                                    )}
                                />
                            )}
                        </div>
                    ))}
                    {toolings.length === 0 && (
                        <p className="text-muted-foreground">
                            {t('assessment.tooling.empty')}
                        </p>
                    )}

                    {canManageCase &&
                        (availableToolings.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                {t('assessment.tooling.none_available')}
                            </p>
                        ) : (
                            <Form
                                {...ValidationCaseToolingController.store.form(
                                    caseId,
                                )}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setToolingId('')}
                                className="flex flex-wrap items-start gap-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="tooling_id"
                                            value={toolingId}
                                        />
                                        <div className="grid min-w-64 gap-1">
                                            <Combobox
                                                options={availableToolings.map(
                                                    (item) => ({
                                                        value: String(item.id),
                                                        label: item.label,
                                                    }),
                                                )}
                                                value={toolingId}
                                                onChange={setToolingId}
                                                placeholder={t(
                                                    'assessment.tooling.pick',
                                                )}
                                            />
                                            <InputError
                                                message={errors.tooling_id}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={
                                                processing || toolingId === ''
                                            }
                                        >
                                            {t('assessment.tooling.link')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{t('assessment.journey.plans_title')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    {plans.map((plan) => (
                        <p key={plan.id} className="flex flex-wrap gap-2">
                            <span>
                                {t('assessment.journey.week_of', {
                                    date: formatDateOnly(plan.week_start),
                                })}
                            </span>
                            <Badge variant="secondary">
                                {t(`assessment.plan.status.${plan.status}`)}
                            </Badge>
                            {plan.ppic_note && (
                                <span className="text-muted-foreground">
                                    {plan.ppic_note}
                                </span>
                            )}
                        </p>
                    ))}
                    {plans.length === 0 && (
                        <p className="text-muted-foreground">
                            {t('assessment.journey.plans_empty')}
                        </p>
                    )}
                    <Link
                        href={plansIndex().url}
                        className="inline-block text-primary underline-offset-4 hover:underline"
                    >
                        {t('assessment.journey.request_plan')}
                    </Link>
                </CardContent>
            </Card>
        </>
    );
}
