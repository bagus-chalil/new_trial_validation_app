import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import ValidationJourneyController from '@/actions/App/Http/Controllers/ValidationJourneyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CaseAlignment } from '@/components/validation/case-alignment';
import type {
    CaseClassification,
    ClassificationOptions,
    FgMapping,
} from '@/components/validation/case-alignment';
import { CaseExtras } from '@/components/validation/case-extras';
import type {
    CaseAssessment,
    CaseImprovement,
    CasePlan,
    CaseTooling,
} from '@/components/validation/case-extras';
import { CaseMixings } from '@/components/validation/case-mixings';
import type { CaseMixing } from '@/components/validation/case-mixings';
import {
    CaseIpcBatches,
    CaseNumbers,
} from '@/components/validation/case-numbers';
import type {
    AvailableNumbers,
    CaseNumbersData,
    IpcData,
} from '@/components/validation/case-numbers';
import { useTranslation } from '@/hooks/use-translation';
import {
    journeyResultClassName,
    journeyResultLabel,
    journeyTypeLabel,
} from '@/lib/journey';
import {
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { index as journeyIndex } from '@/routes/journey';
import { show as projectShow } from '@/routes/projects';
import { create as trialCreate } from '@/routes/trials';
import { show as reportShow } from '@/routes/trials/report';

type Attempt = {
    attempt_no: number;
    id: number;
    trial_code: string;
    status: string;
    validation_date: string | null;
    created_at: string | null;
    decided_at: string | null;
    revision_no: number;
    pending_with: string | null;
    creator: string | null;
    result: string;
    has_not_ok: boolean;
    client_approved: boolean;
    client_approval_note: string | null;
    pm_ready_date: string | null;
    bulk_ready_date: string | null;
    dates_defaulted: boolean;
    lead_time_days: number | null;
    fg_snapshot_stale: boolean;
    line_config: string | null;
    can_view: boolean;
};

type PageProps = {
    case: {
        id: number;
        case_no: string;
        case_type: string;
        created_at: string | null;
        product_name: string;
        finish_good_code: string | null;
    };
    summary: {
        total: number;
        counts: Record<string, number>;
        last_result: string | null;
        avg_lead_time_days: number | null;
        lead_time_samples: number;
        first_trial_date: string | null;
    };
    attempts: Attempt[];
    projects: {
        id: number;
        code: string;
        name: string;
        status: string;
        progress: number;
    }[];
    canRecordClientApproval: boolean;
    assessment: CaseAssessment | null;
    improvements: CaseImprovement[];
    availableImprovements: { id: number; label: string }[];
    canEditAssessment: boolean;
    toolings: CaseTooling[];
    availableToolings: { id: number; label: string }[];
    plans: CasePlan[];
    canCreateTrial: boolean;
    canManageCase: boolean;
    numbers: CaseNumbersData;
    availableNumbers: AvailableNumbers;
    canLinkNumbers: { qco: boolean; im: boolean };
    ipc: IpcData;
    mixings: CaseMixing[];
    canCreateMixing: boolean;
    classification: CaseClassification;
    classificationOptions: ClassificationOptions;
    canClassify: boolean;
    cohort: string;
    cohortCutoff: string;
    fgMapping: FgMapping;
    canVerifyFg: boolean;
};

function ClientApprovalDialog({
    caseId,
    attempt,
    open,
    onOpenChange,
}: {
    caseId: number;
    attempt: Attempt;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('journey.client.title')} · {attempt.trial_code}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...ValidationJourneyController.clientApproval.form({
                        case: caseId,
                        trial: attempt.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="approved" value="0" />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="approved"
                                    value="1"
                                    defaultChecked={attempt.client_approved}
                                />
                                {t('journey.client.approved')}
                            </label>
                            <div className="grid gap-2">
                                <Label htmlFor="client-note">
                                    {t('journey.client.note')}
                                </Label>
                                <Textarea
                                    id="client-note"
                                    name="note"
                                    rows={3}
                                    defaultValue={
                                        attempt.client_approval_note ?? ''
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {t('journey.client.evidence_hint')}
                                </p>
                                <InputError message={errors.note} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('journey.client.save')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function JourneyShow({
    case: caseData,
    summary,
    attempts,
    projects,
    canRecordClientApproval,
    assessment,
    improvements,
    availableImprovements,
    canEditAssessment,
    toolings,
    availableToolings,
    plans,
    canCreateTrial,
    canManageCase,
    numbers,
    availableNumbers,
    canLinkNumbers,
    ipc,
    mixings,
    canCreateMixing,
    classification,
    classificationOptions,
    canClassify,
    cohort,
    cohortCutoff,
    fgMapping,
    canVerifyFg,
}: PageProps) {
    const { t, formatDateOnly } = useTranslation();
    const [editing, setEditing] = useState<Attempt | null>(null);

    const breakdown = Object.entries(summary.counts)
        .map(([result, count]) => `${count} ${journeyResultLabel(t, result)}`)
        .join(', ');

    return (
        <>
            <Head title={caseData.case_no} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={`${caseData.case_no} · ${caseData.product_name}`}
                        description={`${journeyTypeLabel(t, caseData.case_type)} · ${t('journey.columns.fg')}: ${caseData.finish_good_code ?? '-'}`}
                    />
                    {canCreateTrial && (
                        <Button asChild>
                            <Link
                                href={
                                    trialCreate({
                                        query: { case: caseData.id },
                                    }).url
                                }
                            >
                                {t('assessment.trial_from_case.button')}
                            </Link>
                        </Button>
                    )}
                </div>

                <CaseAlignment
                    caseId={caseData.id}
                    classification={classification}
                    options={classificationOptions}
                    canClassify={canClassify}
                    cohort={cohort}
                    cohortCutoff={cohortCutoff}
                    fgMapping={fgMapping}
                    canVerifyFg={canVerifyFg}
                />

                <CaseExtras
                    caseId={caseData.id}
                    assessment={assessment}
                    improvements={improvements}
                    availableImprovements={availableImprovements}
                    canEditAssessment={canEditAssessment}
                    toolings={toolings}
                    availableToolings={availableToolings}
                    plans={plans}
                    canManageCase={canManageCase}
                />

                <CaseNumbers
                    caseId={caseData.id}
                    numbers={numbers}
                    available={availableNumbers}
                    canLink={canLinkNumbers}
                />

                <CaseMixings
                    caseId={caseData.id}
                    mixings={mixings}
                    canCreate={canCreateMixing}
                />

                <CaseIpcBatches ipc={ipc} />

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {summary.total > 0
                                ? t('journey.summary.attempts', {
                                      count: summary.total,
                                  })
                                : t('journey.summary.none')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <p className="text-muted-foreground">
                                {t('journey.summary.last_result')}
                            </p>
                            {summary.last_result ? (
                                <Badge
                                    variant="secondary"
                                    className={journeyResultClassName(
                                        summary.last_result,
                                    )}
                                >
                                    {journeyResultLabel(t, summary.last_result)}
                                </Badge>
                            ) : (
                                '-'
                            )}
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                {t('journey.summary.breakdown')}
                            </p>
                            <p>{breakdown || '-'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                {t('journey.summary.lead_time')}
                            </p>
                            <p>
                                {summary.avg_lead_time_days === null
                                    ? t('journey.summary.lead_time_none')
                                    : t('journey.summary.lead_time_value', {
                                          days: summary.avg_lead_time_days,
                                          samples: summary.lead_time_samples,
                                      })}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">
                                {t('journey.summary.first_trial')}
                            </p>
                            <p>{formatDateOnly(summary.first_trial_date)}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('journey.attempt.timeline')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="space-y-4 border-l pl-6">
                            {attempts.map((attempt) => (
                                <li key={attempt.id} className="relative">
                                    <span className="absolute top-1.5 -left-[1.9rem] size-3 rounded-full bg-brand" />
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {t('journey.attempt.title', {
                                                no: attempt.attempt_no,
                                            })}
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className={journeyResultClassName(
                                                attempt.result,
                                            )}
                                        >
                                            {journeyResultLabel(
                                                t,
                                                attempt.result,
                                            )}
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            className={trialStatusBadgeClassName(
                                                attempt.status,
                                                null,
                                            )}
                                        >
                                            {trialStatusLabel(
                                                t,
                                                attempt.status,
                                            )}
                                        </Badge>
                                        {attempt.revision_no > 0 && (
                                            <span className="text-xs text-muted-foreground">
                                                {t('journey.attempt.revision', {
                                                    no: attempt.revision_no,
                                                })}
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-1 space-y-0.5 text-sm text-muted-foreground">
                                        <p>
                                            {attempt.can_view ? (
                                                <Link
                                                    href={
                                                        reportShow(attempt.id)
                                                            .url
                                                    }
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    {attempt.trial_code}
                                                </Link>
                                            ) : (
                                                <span
                                                    title={t(
                                                        'journey.attempt.private',
                                                    )}
                                                >
                                                    {attempt.trial_code}
                                                </span>
                                            )}
                                            {' · '}
                                            {t(
                                                'journey.attempt.trial_date',
                                            )}:{' '}
                                            {formatDateOnly(
                                                attempt.validation_date,
                                            )}
                                            {attempt.decided_at &&
                                                ` · ${t('journey.attempt.decided_at')}: ${formatDateOnly(attempt.decided_at)}`}
                                            {attempt.pending_with &&
                                                ` · ${t('journey.attempt.pending_with')}: ${attempt.pending_with}`}
                                        </p>
                                        <p>
                                            {t('journey.attempt.pm_ready')}:{' '}
                                            {formatDateOnly(
                                                attempt.pm_ready_date,
                                            )}
                                            {' · '}
                                            {t(
                                                'journey.attempt.bulk_ready',
                                            )}:{' '}
                                            {formatDateOnly(
                                                attempt.bulk_ready_date,
                                            )}
                                            {attempt.lead_time_days !== null &&
                                                ` · ${t('journey.attempt.lead_time', { days: attempt.lead_time_days })}`}
                                        </p>
                                        {attempt.dates_defaulted && (
                                            <p className="text-xs">
                                                {t(
                                                    'journey.attempt.dates_defaulted',
                                                )}
                                            </p>
                                        )}
                                        {attempt.fg_snapshot_stale && (
                                            <p className="text-xs text-amber-700 dark:text-amber-300">
                                                {t('journey.attempt.stale_fg')}
                                            </p>
                                        )}
                                        {attempt.line_config && (
                                            <p>
                                                {t(
                                                    'journey.attempt.line_config',
                                                    {
                                                        status: t(
                                                            `journey.line_config_status.${attempt.line_config}`,
                                                        ),
                                                    },
                                                )}
                                            </p>
                                        )}
                                        {attempt.client_approved && (
                                            <p>
                                                {t('journey.client.title')}:{' '}
                                                {attempt.client_approval_note}
                                            </p>
                                        )}
                                    </div>
                                    {canRecordClientApproval &&
                                        attempt.has_not_ok && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="mt-2"
                                                onClick={() =>
                                                    setEditing(attempt)
                                                }
                                            >
                                                {t('journey.client.edit')}
                                            </Button>
                                        )}
                                </li>
                            ))}
                            {attempts.length === 0 && (
                                <li className="text-sm text-muted-foreground">
                                    {t('journey.summary.none')}
                                </li>
                            )}
                        </ol>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('journey.projects.title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {projects.map((project) => (
                            <p key={project.id}>
                                <Link
                                    href={projectShow(project.id).url}
                                    className="text-primary underline-offset-4 hover:underline"
                                >
                                    {project.code}
                                </Link>
                                {' · '}
                                {project.name} · {project.progress}%
                            </p>
                        ))}
                        {projects.length === 0 && (
                            <p className="text-muted-foreground">
                                {t('journey.projects.empty')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {editing && (
                <ClientApprovalDialog
                    key={editing.id}
                    caseId={caseData.id}
                    attempt={editing}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                />
            )}
        </>
    );
}

JourneyShow.layout = {
    breadcrumbs: [{ title: 'Product Journey', href: journeyIndex() }],
};
