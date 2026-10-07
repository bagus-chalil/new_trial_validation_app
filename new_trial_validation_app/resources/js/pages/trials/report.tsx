import { Form, Head, Link } from '@inertiajs/react';
import ApprovalController from '@/actions/App/Http/Controllers/ApprovalController';
import ReviewController from '@/actions/App/Http/Controllers/ReviewController';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { AttachmentImagePreview } from '@/components/attachment-image-preview';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import type { AdditionalAttachment } from '@/components/trials/additional-attachments-section';
import { AdditionalAttachmentsSection } from '@/components/trials/additional-attachments-section';
import type {
    LineConfigurationApproverOption,
    LineConfigurationReportData,
    LineConfigurationReportVersion,
    LineConfigurationReturnNote,
} from '@/components/trials/line-configuration-report-section';
import { LineConfigurationReportSection } from '@/components/trials/line-configuration-report-section';
import { ReportPdfDownloadDialog } from '@/components/trials/report-pdf-download-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslateFn } from '@/hooks/use-translation';
import {
    trialListGroupFor,
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { dashboard } from '@/routes';
import { edit as editTrial, index as trialsIndex } from '@/routes/trials';
import { edit as reviewEdit } from '@/routes/trials/review';

type TrialData = {
    id: number;
    trial_code: string;
    product_name: string;
    finish_good_code: string;
    validation_category: string;
    validation_scope: string[] | null;
    product_type: string;
    validation_date: string | null;
    risk_level: string;
    machine_used: string[] | null;
    created_by: string | null;
    progress_status: string;
    final_decision: string | null;
    current_step: string | null;
    estimate_qty: string | null;
    batch_number: string | null;
    bulk_code: string | null;
    support_team: string | null;
    initiated_person_team: string | null;
    reason: string | null;
    bom: string | null;
    pending_with: string | null;
    revision_no: number;
    approval_comment: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    approver: { id: number; name: string; email: string } | null;
};

type ResultItem = {
    parameter_name: string;
    specification: string | null;
    decision: string | null;
    result_value: string | null;
    remark: string | null;
};

type WeighingSection = {
    section: 'Packaging' | 'Filling';
    stats: {
        values: string[];
        count: number;
        min: number | null;
        max: number | null;
        avg: number | null;
    };
};

type AttachmentFile = {
    id: number;
    file_name: string;
    caption: string | null;
    url: string;
};

type ReviewItem = {
    department: string;
    review_round: number;
    status: string;
    reviewer_name: string | null;
    assigned_to: string | null;
    reviewed_at: string | null;
    comment: string | null;
};

type PendingReview = {
    id: number;
    department: string;
};

type EditableReview = {
    id: number;
    department: string;
    comment: string | null;
    editsRemaining: number;
};

type PageProps = {
    trial: TrialData;
    additionalAttachments: AdditionalAttachment[];
    canUploadAdditionalAttachment: boolean;
    additionalAttachmentLimit: number;
    results: ResultItem[];
    weighingSections: WeighingSection[];
    attachments: Record<string, AttachmentFile[]>;
    reviews: ReviewItem[];
    approvedByName: string | null;
    rejectedByName: string | null;
    completeness: string[];
    canEdit: boolean;
    canApprove: boolean;
    pendingReviews: PendingReview[];
    editableReviews: EditableReview[];
    approvalBlockedNote: string | null;
    reviewCompletedNote: string | null;
    lineConfigurationReport: LineConfigurationReportData;
    canEditLineConfigurationReport: boolean;
    lineConfigurationReportLocked: boolean;
    lineConfigurationReturnNote: LineConfigurationReturnNote;
    lineConfigurationReportVersions: LineConfigurationReportVersion[];
    lineConfigurationApprovers: LineConfigurationApproverOption[];
    lineConfigurationProdApprovers: LineConfigurationApproverOption[];
    lineConfigurationLanes: { approved_pie: string; checked_prod: string };
    canApprovePieLineConfigurationReport: boolean;
    canCheckProdLineConfigurationReport: boolean;
    canReturnLineConfigurationReport: boolean;
};

// `value` is the decision sent to the server and must never be translated.
const APPROVAL_DECISIONS = [
    {
        value: 'Approved',
        labelKey: 'report.approval.approve',
        variant: 'default' as const,
    },
    {
        value: 'Need Revision',
        labelKey: 'report.approval.need_revision',
        variant: 'outline' as const,
    },
    {
        value: 'Rejected',
        labelKey: 'report.approval.reject',
        variant: 'destructive' as const,
    },
];

const DECISION_LABEL_KEYS: Record<string, { by: string; at: string }> = {
    Approved: {
        by: 'report.decision.approved_by',
        at: 'report.decision.approved_at',
    },
    'Need Revision': {
        by: 'report.decision.revision_by',
        at: 'report.decision.revision_at',
    },
    Rejected: {
        by: 'report.decision.rejected_by',
        at: 'report.decision.rejected_at',
    },
};

const REVIEW_STATUS_KEYS: Record<string, string> = {
    Pending: 'report.review.statuses.pending',
    Reviewed: 'report.review.statuses.reviewed',
};

const WEIGHING_SECTION_KEYS: Record<string, string> = {
    Packaging: 'report.weighing.sections.packaging',
    Filling: 'report.weighing.sections.filling',
};

// Display label for a stored value; unknown values are shown as-is.
function labelFor(
    t: TranslateFn,
    keys: Record<string, string>,
    value: string,
): string {
    return keys[value] ? t(keys[value]) : value;
}

function formatNumber(value: number | null): string {
    return value === null ? '-' : value.toFixed(2);
}

export default function TrialReport({
    trial,
    additionalAttachments,
    canUploadAdditionalAttachment,
    additionalAttachmentLimit,
    results,
    weighingSections,
    attachments,
    reviews,
    approvedByName,
    rejectedByName,
    completeness,
    canEdit,
    canApprove,
    pendingReviews,
    editableReviews,
    approvalBlockedNote,
    reviewCompletedNote,
    lineConfigurationReport,
    canEditLineConfigurationReport,
    lineConfigurationReportLocked,
    lineConfigurationReturnNote,
    lineConfigurationReportVersions,
    lineConfigurationApprovers,
    lineConfigurationProdApprovers,
    lineConfigurationLanes,
    canApprovePieLineConfigurationReport,
    canCheckProdLineConfigurationReport,
    canReturnLineConfigurationReport,
}: PageProps) {
    const { t, formatDate, formatDateOnly } = useTranslation();
    const managerDecision = trial.final_decision ?? trial.progress_status;
    const hasDecision =
        Boolean(trial.approval_comment) ||
        Boolean(approvedByName) ||
        Boolean(rejectedByName);
    const decisionBy =
        managerDecision === 'Approved' ? approvedByName : rejectedByName;
    const decisionAt =
        managerDecision === 'Approved' ? trial.approved_at : trial.rejected_at;
    const decisionLabelKeys = DECISION_LABEL_KEYS[managerDecision] ?? {
        by: 'report.decision.decision_by',
        at: 'report.decision.decision_at',
    };
    const displayDecision =
        trial.progress_status === 'Approved' ||
        trial.progress_status === 'Rejected'
            ? (trial.final_decision ?? trial.progress_status)
            : trial.progress_status;
    const approvalAuthority = approvedByName ?? rejectedByName ?? '-';

    return (
        <>
            <Head title={t('report.page_title', { code: trial.trial_code })} />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        title={t('report.title')}
                        description={t('report.description')}
                    />
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={TrialReportController.excel(trial.id).url}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {t('report.actions.export_excel')}
                            </a>
                        </Button>
                        <ReportPdfDownloadDialog
                            trialId={trial.id}
                            hasLineConfigurationReport={
                                lineConfigurationReport != null ||
                                lineConfigurationReportVersions.length > 0
                            }
                        />
                    </div>
                </div>

                {approvalBlockedNote && (
                    <Alert className="print:hidden">
                        <AlertTitle>
                            {t('report.notes.not_your_turn_title')}
                        </AlertTitle>
                        <AlertDescription>
                            {approvalBlockedNote}
                        </AlertDescription>
                    </Alert>
                )}

                {reviewCompletedNote && (
                    <Alert className="print:hidden">
                        <AlertTitle>
                            {t('report.notes.review_done_title')}
                        </AlertTitle>
                        <AlertDescription>
                            {reviewCompletedNote}
                        </AlertDescription>
                    </Alert>
                )}

                {canEdit && (
                    <div className="flex flex-wrap items-center gap-3 print:hidden">
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    trialsIndex(
                                        trialListGroupFor(
                                            trial.progress_status,
                                            trial.final_decision,
                                        ),
                                    ).url
                                }
                            >
                                {t('common.actions.back')}
                            </Link>
                        </Button>
                        <Button variant="secondary" asChild>
                            <Link href={editTrial({ trial: trial.id }).url}>
                                {t('report.actions.edit_trial')}
                            </Link>
                        </Button>
                        {completeness.length > 0 ? (
                            <Alert variant="destructive" className="flex-1">
                                <AlertTitle>
                                    {t('report.notes.not_ready_title')}
                                </AlertTitle>
                                <AlertDescription>
                                    <ul className="list-inside list-disc">
                                        {completeness.map((item) => (
                                            <li key={item}>{item}</li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        ) : (
                            trial.progress_status === 'Draft' && (
                                <Button asChild>
                                    <Link
                                        href={
                                            reviewEdit({ trial: trial.id }).url
                                        }
                                    >
                                        {t('report.actions.submit_for_review')}
                                    </Link>
                                </Button>
                            )
                        )}
                    </div>
                )}

                <Card className="print:border-none print:shadow-none">
                    <CardContent className="space-y-6 pt-6">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-4">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    {t('report.document_title')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    FR.QSE.074.04
                                </p>
                            </div>
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

                        <div className="grid gap-4 sm:grid-cols-3">
                            {[
                                ['trial_id', trial.trial_code],
                                ['product_name', trial.product_name],
                                ['fg_code', trial.finish_good_code],
                                [
                                    'validation_category',
                                    trial.validation_category,
                                ],
                                [
                                    'validation_scope',
                                    (trial.validation_scope ?? []).join(', '),
                                ],
                                ['product_type', trial.product_type],
                                [
                                    'validation_date',
                                    formatDateOnly(trial.validation_date),
                                ],
                                ['risk_level', trial.risk_level],
                                [
                                    'machine_used',
                                    (trial.machine_used ?? []).join(', '),
                                ],
                                ['created_by', trial.created_by ?? '-'],
                                ['estimate_qty', trial.estimate_qty ?? '-'],
                                [
                                    'approval_status',
                                    trialStatusLabel(t, displayDecision),
                                ],
                                ['approval_authority', approvalAuthority],
                            ].map(([labelKey, value]) => (
                                <div
                                    key={labelKey}
                                    className="rounded-md border p-3"
                                >
                                    <div className="text-xs tracking-wide text-muted-foreground uppercase">
                                        {t(`report.info.${labelKey}`)}
                                    </div>
                                    <div className="font-medium">
                                        {value || '-'}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div>
                            <h3 className="mb-2 text-base font-semibold">
                                {t('report.header.title')}
                            </h3>
                            <Table>
                                <TableBody>
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.batch_number')}
                                        </TableCell>
                                        <TableCell>
                                            {trial.batch_number ?? '-'}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {t('report.header.bulk_code')}
                                        </TableCell>
                                        <TableCell>
                                            {trial.bulk_code ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.estimate_qty')}
                                        </TableCell>
                                        <TableCell colSpan={3}>
                                            {trial.estimate_qty ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.support_team')}
                                        </TableCell>
                                        <TableCell>
                                            {trial.support_team ?? '-'}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {t(
                                                'report.header.initiated_person_team',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {trial.initiated_person_team ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.reason')}
                                        </TableCell>
                                        <TableCell colSpan={3}>
                                            {trial.reason ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.bom')}
                                        </TableCell>
                                        <TableCell
                                            colSpan={3}
                                            className="whitespace-pre-line"
                                        >
                                            {trial.bom ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.status')}
                                        </TableCell>
                                        <TableCell>
                                            {trialStatusLabel(
                                                t,
                                                trial.progress_status,
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {t('report.header.pending_with')}
                                        </TableCell>
                                        <TableCell>
                                            {trial.pending_with ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                    {trial.approver && (
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                {t(
                                                    'report.header.selected_approver',
                                                )}
                                            </TableCell>
                                            <TableCell colSpan={3}>
                                                {trial.approver.name ||
                                                    trial.approver.email}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    <TableRow>
                                        <TableCell className="font-medium">
                                            {t('report.header.revision_no')}
                                        </TableCell>
                                        <TableCell>
                                            {trial.revision_no ?? 0}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {t('report.header.final_decision')}
                                        </TableCell>
                                        <TableCell>
                                            {trial.final_decision
                                                ? trialStatusLabel(
                                                      t,
                                                      trial.final_decision,
                                                  )
                                                : '-'}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <div>
                            <h3 className="mb-2 text-base font-semibold">
                                {t('report.validation.title')}
                            </h3>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('report.validation.parameter')}
                                        </TableHead>
                                        <TableHead>
                                            {t(
                                                'report.validation.specification',
                                            )}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.validation.decision')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.validation.result')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.validation.remark')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {results.map((r, i) => (
                                        <TableRow
                                            key={i}
                                            className={
                                                r.decision === 'NOT OK'
                                                    ? 'bg-red-50 dark:bg-red-950/30'
                                                    : undefined
                                            }
                                        >
                                            <TableCell>
                                                {r.parameter_name}
                                            </TableCell>
                                            <TableCell className="whitespace-pre-line">
                                                {r.specification ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {r.decision ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {r.result_value ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {r.remark ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {results.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="text-center text-muted-foreground"
                                            >
                                                {t('report.validation.empty')}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="space-y-4">
                            <h3 className="text-base font-semibold">
                                {t('report.weighing.title')}
                            </h3>
                            {weighingSections.map((section) => (
                                <Card key={section.section}>
                                    <CardHeader>
                                        <CardTitle className="text-sm">
                                            {t(
                                                'report.weighing.section_title',
                                                {
                                                    section: labelFor(
                                                        t,
                                                        WEIGHING_SECTION_KEYS,
                                                        section.section,
                                                    ),
                                                },
                                            )}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {section.stats.count === 0 ? (
                                            <p className="font-medium">
                                                {t(
                                                    'report.weighing.not_available',
                                                    {
                                                        section: labelFor(
                                                            t,
                                                            WEIGHING_SECTION_KEYS,
                                                            section.section,
                                                        ),
                                                    },
                                                )}
                                            </p>
                                        ) : (
                                            <>
                                                <div className="mb-3 flex flex-wrap gap-2 text-sm">
                                                    {section.stats.values.map(
                                                        (v, i) => (
                                                            <span
                                                                key={i}
                                                                className="rounded border px-2 py-0.5"
                                                            >
                                                                {v}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                                    <div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {t(
                                                                'report.weighing.total_sample',
                                                            )}
                                                        </div>
                                                        <div className="font-medium">
                                                            {
                                                                section.stats
                                                                    .count
                                                            }
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {t(
                                                                'report.weighing.average',
                                                            )}
                                                        </div>
                                                        <div className="font-medium">
                                                            {formatNumber(
                                                                section.stats
                                                                    .avg,
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {t(
                                                                'report.weighing.minimum',
                                                            )}
                                                        </div>
                                                        <div className="font-medium">
                                                            {formatNumber(
                                                                section.stats
                                                                    .min,
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {t(
                                                                'report.weighing.maximum',
                                                            )}
                                                        </div>
                                                        <div className="font-medium">
                                                            {formatNumber(
                                                                section.stats
                                                                    .max,
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <div>
                            <h3 className="mb-2 text-base font-semibold">
                                {t('report.attachments.title')}
                            </h3>
                            {Object.keys(attachments).length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('report.attachments.empty')}
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {Object.entries(attachments).map(
                                        ([category, files]) => (
                                            <div key={category}>
                                                <h4 className="mb-2 text-sm font-semibold">
                                                    {category}
                                                </h4>
                                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6">
                                                    {files.map((file) => (
                                                        <figure
                                                            key={file.id}
                                                            className="space-y-1 rounded-md border p-2"
                                                        >
                                                            <AttachmentImagePreview
                                                                src={file.url}
                                                                alt={
                                                                    file.file_name
                                                                }
                                                                fileName={
                                                                    file.file_name
                                                                }
                                                                caption={
                                                                    file.caption
                                                                }
                                                            />
                                                            <figcaption className="space-y-0.5 text-xs text-muted-foreground">
                                                                {file.caption && (
                                                                    <span className="block font-medium break-words whitespace-pre-wrap text-foreground">
                                                                        {
                                                                            file.caption
                                                                        }
                                                                    </span>
                                                                )}
                                                                <span className="block truncate">
                                                                    {
                                                                        file.file_name
                                                                    }
                                                                </span>
                                                            </figcaption>
                                                        </figure>
                                                    ))}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>

                        <div>
                            <h3 className="mb-2 text-base font-semibold">
                                {t('report.review.title')}
                            </h3>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('report.review.round')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.review.department')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.review.status')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.review.reviewer_name')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.review.reviewed_at')}
                                        </TableHead>
                                        <TableHead>
                                            {t('report.review.comment')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reviews.map((r) => (
                                        <TableRow key={r.department}>
                                            <TableCell>
                                                {r.review_round}
                                            </TableCell>
                                            <TableCell>
                                                {r.department}
                                            </TableCell>
                                            <TableCell>
                                                {labelFor(
                                                    t,
                                                    REVIEW_STATUS_KEYS,
                                                    r.status,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {r.reviewer_name ??
                                                    (r.assigned_to ? (
                                                        <span>
                                                            {r.assigned_to}{' '}
                                                            <span className="text-xs text-muted-foreground">
                                                                {t(
                                                                    'report.review.assigned',
                                                                )}
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        '-'
                                                    ))}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(r.reviewed_at)}
                                            </TableCell>
                                            <TableCell>
                                                {r.comment ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {pendingReviews.length > 0 && (
                            <div className="print:hidden">
                                <h3 className="mb-2 text-base font-semibold">
                                    {t('report.review.my_review_title')}
                                </h3>
                                <div className="space-y-4">
                                    {pendingReviews.map((pending) => (
                                        <Form
                                            key={pending.id}
                                            {...ReviewController.update.form(
                                                pending.id,
                                            )}
                                        >
                                            {({ processing, errors }) => (
                                                <Card>
                                                    <CardHeader>
                                                        <CardTitle className="text-sm">
                                                            {t(
                                                                'report.review.submit_for_department',
                                                                {
                                                                    department:
                                                                        pending.department,
                                                                },
                                                            )}
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="space-y-3">
                                                        {errors.comment && (
                                                            <Alert variant="destructive">
                                                                <AlertDescription>
                                                                    {
                                                                        errors.comment
                                                                    }
                                                                </AlertDescription>
                                                            </Alert>
                                                        )}
                                                        <Textarea
                                                            name="comment"
                                                            required
                                                            placeholder={t(
                                                                'report.review.comment_placeholder',
                                                            )}
                                                        />
                                                        <div className="flex justify-end">
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {t(
                                                                    'report.review.submit',
                                                                )}
                                                            </Button>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            )}
                                        </Form>
                                    ))}
                                </div>
                            </div>
                        )}

                        {editableReviews.length > 0 && (
                            <div className="print:hidden">
                                <h3 className="mb-2 text-base font-semibold">
                                    {t('report.review.edit_title')}
                                </h3>
                                <div className="space-y-4">
                                    {editableReviews.map((review) => (
                                        <Form
                                            key={review.id}
                                            {...ReviewController.update.form(
                                                review.id,
                                            )}
                                        >
                                            {({ processing, errors }) => (
                                                <Card>
                                                    <CardHeader>
                                                        <CardTitle className="text-sm">
                                                            {t(
                                                                'report.review.edit_card_title',
                                                                {
                                                                    department:
                                                                        review.department,
                                                                },
                                                            )}
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="space-y-3">
                                                        {errors.comment && (
                                                            <Alert variant="destructive">
                                                                <AlertDescription>
                                                                    {
                                                                        errors.comment
                                                                    }
                                                                </AlertDescription>
                                                            </Alert>
                                                        )}
                                                        <Textarea
                                                            name="comment"
                                                            required
                                                            defaultValue={
                                                                review.comment ??
                                                                ''
                                                            }
                                                            placeholder={t(
                                                                'report.review.comment_placeholder',
                                                            )}
                                                        />
                                                        <div className="flex items-center justify-between">
                                                            <p className="text-xs text-muted-foreground">
                                                                {t(
                                                                    'report.review.edits_remaining',
                                                                    {
                                                                        count: review.editsRemaining,
                                                                    },
                                                                )}
                                                            </p>
                                                            <Button
                                                                type="submit"
                                                                disabled={
                                                                    processing
                                                                }
                                                            >
                                                                {t(
                                                                    'report.review.save_changes',
                                                                )}
                                                            </Button>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            )}
                                        </Form>
                                    ))}
                                </div>
                            </div>
                        )}

                        {canApprove && (
                            <div className="print:hidden">
                                <h3 className="mb-2 text-base font-semibold">
                                    {t('report.approval.title')}
                                </h3>
                                <Card>
                                    <CardContent className="flex flex-wrap gap-2 pt-6">
                                        {APPROVAL_DECISIONS.map((decision) => (
                                            <ConfirmDialog
                                                key={decision.value}
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        variant={
                                                            decision.variant
                                                        }
                                                    >
                                                        {t(decision.labelKey)}
                                                    </Button>
                                                }
                                                title={t(
                                                    'report.approval.dialog_title',
                                                    {
                                                        decision: t(
                                                            decision.labelKey,
                                                        ),
                                                        code: trial.trial_code,
                                                    },
                                                )}
                                                description={t(
                                                    'report.approval.dialog_description',
                                                )}
                                                confirmLabel={t(
                                                    decision.labelKey,
                                                )}
                                                confirmVariant={
                                                    decision.variant
                                                }
                                                formProps={ApprovalController.update.form(
                                                    trial.id,
                                                )}
                                            >
                                                {({ errors }) => (
                                                    <div className="space-y-3">
                                                        <input
                                                            type="hidden"
                                                            name="decision"
                                                            value={
                                                                decision.value
                                                            }
                                                        />
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor={`approval_comment_${decision.value}`}
                                                            >
                                                                {t(
                                                                    'report.approval.comment',
                                                                )}
                                                            </Label>
                                                            <Textarea
                                                                id={`approval_comment_${decision.value}`}
                                                                name="approval_comment"
                                                                required
                                                                placeholder={t(
                                                                    'report.approval.comment_placeholder',
                                                                )}
                                                            />
                                                            {errors.approval_comment && (
                                                                <p className="text-sm text-destructive">
                                                                    {
                                                                        errors.approval_comment
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="grid gap-2">
                                                            <Label
                                                                htmlFor={`signature_password_${decision.value}`}
                                                            >
                                                                {t(
                                                                    'report.approval.password',
                                                                )}
                                                            </Label>
                                                            <Input
                                                                id={`signature_password_${decision.value}`}
                                                                type="password"
                                                                name="signature_password"
                                                                required
                                                                autoComplete="current-password"
                                                            />
                                                            {errors.signature_password && (
                                                                <p className="text-sm text-destructive">
                                                                    {
                                                                        errors.signature_password
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        {errors.decision && (
                                                            <p className="text-sm text-destructive">
                                                                {
                                                                    errors.decision
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </ConfirmDialog>
                                        ))}
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        {hasDecision && (
                            <div>
                                <h3 className="mb-2 text-base font-semibold">
                                    {t('report.decision.title')}
                                </h3>
                                <Table>
                                    <TableBody>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                {t('report.decision.decision')}
                                            </TableCell>
                                            <TableCell>
                                                {trialStatusLabel(
                                                    t,
                                                    managerDecision,
                                                )}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {t('report.decision.status')}
                                            </TableCell>
                                            <TableCell>
                                                {trialStatusLabel(
                                                    t,
                                                    trial.progress_status,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                {t(decisionLabelKeys.by)}
                                            </TableCell>
                                            <TableCell>
                                                {decisionBy ?? '-'}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {t(decisionLabelKeys.at)}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(decisionAt)}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                {t('report.decision.comment')}
                                            </TableCell>
                                            <TableCell colSpan={3}>
                                                {trial.approval_comment ?? '-'}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        <AdditionalAttachmentsSection
                            trialId={trial.id}
                            attachments={additionalAttachments}
                            canUpload={canUploadAdditionalAttachment}
                            limit={additionalAttachmentLimit}
                        />

                        <LineConfigurationReportSection
                            trialId={trial.id}
                            report={lineConfigurationReport}
                            canEdit={canEditLineConfigurationReport}
                            locked={lineConfigurationReportLocked}
                            returnNote={lineConfigurationReturnNote}
                            versions={lineConfigurationReportVersions}
                            approvers={lineConfigurationApprovers}
                            prodApprovers={lineConfigurationProdApprovers}
                            lanes={lineConfigurationLanes}
                            canApprovePie={canApprovePieLineConfigurationReport}
                            canCheckProd={canCheckProdLineConfigurationReport}
                            canReturn={canReturnLineConfigurationReport}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TrialReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Report', href: '#' },
    ],
};
