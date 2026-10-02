import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import TrialValidationController from '@/actions/App/Http/Controllers/TrialValidationController';
import Heading from '@/components/heading';
import { TrialWizardSteps } from '@/components/trial-wizard-steps';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import {
    trialListGroupFor,
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { edit as editTrial, index as trialsIndex } from '@/routes/trials';

type TrialData = {
    id: number;
    trial_code: string;
    current_step: string | null;
    attachments_count: number;
    progress_status: string;
    final_decision: string | null;
    product_type: string;
};

type Parameter = {
    id: number;
    parameter_name: string;
    specification: string | null;
};

type ResultRow = {
    parameter_id: number;
    result_value: string | null;
    decision: string | null;
    remark: string | null;
};

type PageProps = {
    trial: TrialData;
    parameters: Parameter[];
    results: Record<string, ResultRow>;
    canEdit: boolean;
};

const DECISIONS = ['OK', 'NOT OK', 'N/A'] as const;

function ValidationParameterRow({
    index,
    parameter,
    initial,
    canEdit,
}: {
    index: number;
    parameter: Parameter;
    initial: ResultRow | undefined;
    canEdit: boolean;
}) {
    const [decision, setDecision] = useState(initial?.decision ?? 'OK');
    const [result, setResult] = useState(initial?.result_value ?? 'Conform');
    const [remark, setRemark] = useState(initial?.remark ?? '');

    function handleDecisionChange(value: string) {
        setDecision(value);

        if (value === 'N/A') {
            setResult('N/A');
        } else if (value === 'OK') {
            setResult('Conform');
        } else if (value === 'NOT OK') {
            setResult('');
        }
    }

    return (
        <TableRow
            className={decision === 'NOT OK' ? 'bg-destructive/10' : undefined}
        >
            <TableCell className="font-medium">
                {parameter.parameter_name}
                <input
                    type="hidden"
                    name={`results[${index}][parameter_id]`}
                    value={parameter.id}
                />
            </TableCell>
            <TableCell className="whitespace-pre-line text-muted-foreground">
                {parameter.specification}
            </TableCell>
            <TableCell>
                <Select
                    name={`results[${index}][decision]`}
                    value={decision}
                    onValueChange={handleDecisionChange}
                    disabled={!canEdit}
                >
                    <SelectTrigger className="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {DECISIONS.map((d) => (
                            <SelectItem key={d} value={d}>
                                {d}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </TableCell>
            <TableCell>
                <Input
                    name={`results[${index}][result]`}
                    value={result}
                    onChange={(e) => setResult(e.target.value)}
                    disabled={!canEdit}
                    readOnly={!canEdit}
                />
            </TableCell>
            <TableCell>
                <Textarea
                    name={`results[${index}][remark]`}
                    value={remark}
                    onChange={(e) => setRemark(e.target.value)}
                    disabled={!canEdit}
                    readOnly={!canEdit}
                    rows={4}
                    className="w-full min-w-[200px]"
                />
            </TableCell>
        </TableRow>
    );
}

export default function TrialValidation({
    trial,
    parameters,
    results,
    canEdit,
}: PageProps) {
    const { t } = useTranslation();
    const backHref = canEdit
        ? editTrial(trial.id).url
        : trialsIndex(
              trialListGroupFor(trial.progress_status, trial.final_decision),
          ).url;

    const table = (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>{t('report.validation.parameter')}</TableHead>
                    <TableHead>
                        {t('report.validation.specification')}
                    </TableHead>
                    <TableHead>{t('report.validation.decision')}</TableHead>
                    <TableHead>{t('report.validation.result')}</TableHead>
                    <TableHead>{t('report.validation.remark')}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {parameters.map((parameter, index) => (
                    <ValidationParameterRow
                        key={parameter.id}
                        index={index}
                        parameter={parameter}
                        initial={results[String(parameter.id)]}
                        canEdit={canEdit}
                    />
                ))}
            </TableBody>
        </Table>
    );

    return (
        <>
            <Head
                title={t('wizard.validation.page_title', {
                    code: trial.trial_code,
                })}
            />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('wizard.validation.title', {
                            type: trial.product_type,
                        })}
                        description={t('wizard.validation.description')}
                    />
                    <Badge
                        variant="outline"
                        className={trialStatusBadgeClassName(
                            trial.progress_status,
                            trial.final_decision,
                        )}
                    >
                        {trialStatusLabel(t, trial.progress_status)}
                    </Badge>
                </div>

                <TrialWizardSteps currentStep={2} trial={trial} />

                {parameters.length === 0 ? (
                    <>
                        <Alert>
                            <AlertDescription>
                                {t('wizard.validation.no_parameters')}
                            </AlertDescription>
                        </Alert>
                        <div className="flex justify-end">
                            <Button type="button" variant="secondary" asChild>
                                <Link href={backHref}>
                                    {t('common.actions.back')}
                                </Link>
                            </Button>
                        </div>
                    </>
                ) : canEdit ? (
                    <Form
                        {...TrialValidationController.update.form(trial.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            {t('report.validation.title')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {errors.results && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {errors.results}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        {table}
                                    </CardContent>
                                </Card>

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        asChild
                                    >
                                        <Link href={backHref}>
                                            {t('common.actions.back')}
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {t('wizard.save_next')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('report.validation.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>{table}</CardContent>
                        </Card>

                        <div className="flex justify-end">
                            <Button type="button" variant="secondary" asChild>
                                <Link href={backHref}>
                                    {t('common.actions.back')}
                                </Link>
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

TrialValidation.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Validation', href: '#' },
    ],
};
