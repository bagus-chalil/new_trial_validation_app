import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import TrialReviewController from '@/actions/App/Http/Controllers/TrialReviewController';
import { Combobox } from '@/components/combobox';
import Heading from '@/components/heading';
import { TrialWizardSteps } from '@/components/trial-wizard-steps';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import {
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { formatDate } from '@/lib/utils';
import { dashboard } from '@/routes';
import attachments from '@/routes/trials/attachments';
import { show as reportShow } from '@/routes/trials/report';

type TrialData = {
    id: number;
    trial_code: string;
    current_step: string | null;
    attachments_count: number;
    progress_status: string;
    final_decision: string | null;
    product_type: string;
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

type ApproverOption = {
    id: number;
    label: string;
};

type ReviewerOption = {
    id: number;
    label: string;
};

type PageProps = {
    trial: TrialData;
    reviewerDepartments: string[];
    reviewersByDepartment: Record<string, ReviewerOption[]>;
    reviews: ReviewItem[];
    approvers: ApproverOption[];
    selectedApproverId: number | null;
    completeness: string[];
    canEdit: boolean;
};

// trials_review.status values (shared with the legacy app) → label keys.
const REVIEW_STATUS_KEYS: Record<string, string> = {
    Pending: 'report.review.statuses.pending',
    Reviewed: 'report.review.statuses.reviewed',
};

export default function TrialReview({
    trial,
    reviewerDepartments,
    reviewersByDepartment,
    reviews,
    approvers,
    selectedApproverId,
    completeness,
    canEdit,
}: PageProps) {
    const { t } = useTranslation();
    const [approverId, setApproverId] = useState(
        selectedApproverId ? String(selectedApproverId) : '',
    );
    const [departments, setDepartments] =
        useState<string[]>(reviewerDepartments);
    const [reviewerSelections, setReviewerSelections] = useState<
        Record<string, string>
    >({});

    const approverOptions = approvers.map((a) => ({
        value: String(a.id),
        label: a.label,
    }));

    function toggleDepartment(dept: string, checked: boolean) {
        setDepartments((prev) =>
            checked ? [...prev, dept] : prev.filter((d) => d !== dept),
        );
    }

    function setReviewerFor(dept: string, userId: string) {
        setReviewerSelections((prev) => ({ ...prev, [dept]: userId }));
    }

    const allReviewersAssigned = departments.every(
        (dept) => reviewerSelections[dept],
    );

    const backHref = attachments.edit({ trial: trial.id }).url;
    const alreadySubmitted = reviews.length > 0;

    return (
        <>
            <Head
                title={t('wizard.review.page_title', {
                    code: trial.trial_code,
                })}
            />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('wizard.review.title')}
                        description={t('wizard.review.description', {
                            code: trial.trial_code,
                        })}
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

                <TrialWizardSteps currentStep={6} trial={trial} />

                {alreadySubmitted && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('wizard.review.status_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
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
                                            {t('wizard.review.assigned_to')}
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
                                        <TableRow
                                            key={`${r.review_round}-${r.department}`}
                                        >
                                            <TableCell>
                                                {r.review_round}
                                            </TableCell>
                                            <TableCell>
                                                {r.department}
                                            </TableCell>
                                            <TableCell>
                                                {r.assigned_to ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {REVIEW_STATUS_KEYS[r.status]
                                                    ? t(
                                                          REVIEW_STATUS_KEYS[
                                                              r.status
                                                          ],
                                                      )
                                                    : r.status}
                                            </TableCell>
                                            <TableCell>
                                                {r.reviewer_name ?? '-'}
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
                        </CardContent>
                    </Card>
                )}

                {canEdit && completeness.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>{t('wizard.review.not_ready')}</AlertTitle>
                        <AlertDescription>
                            <ul className="list-inside list-disc">
                                {completeness.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {canEdit && completeness.length === 0 ? (
                    <Form
                        {...TrialReviewController.store.form(trial.id)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            {t('wizard.review.select_title')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {errors.completeness && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {errors.completeness}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        {errors.departments && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {errors.departments}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        {errors.reviewer_user_ids && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {errors.reviewer_user_ids}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        {errors.approver_user_id && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {errors.approver_user_id}
                                                </AlertDescription>
                                            </Alert>
                                        )}

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {reviewerDepartments.map((dept) => {
                                                const checked =
                                                    departments.includes(dept);
                                                const reviewerOptions = (
                                                    reviewersByDepartment[
                                                        dept
                                                    ] ?? []
                                                ).map((r) => ({
                                                    value: String(r.id),
                                                    label: r.label,
                                                }));

                                                return (
                                                    <div
                                                        key={dept}
                                                        className="space-y-2 rounded-md border p-3 text-sm"
                                                    >
                                                        <label className="flex items-center gap-2 font-medium">
                                                            <Checkbox
                                                                checked={
                                                                    checked
                                                                }
                                                                onCheckedChange={(
                                                                    isChecked,
                                                                ) =>
                                                                    toggleDepartment(
                                                                        dept,
                                                                        isChecked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            {checked && (
                                                                <input
                                                                    type="hidden"
                                                                    name="departments[]"
                                                                    value={dept}
                                                                />
                                                            )}
                                                            {dept}
                                                        </label>

                                                        {checked && (
                                                            <>
                                                                {reviewerOptions.length ===
                                                                0 ? (
                                                                    <p className="text-xs text-destructive">
                                                                        {t(
                                                                            'wizard.review.no_reviewer',
                                                                            {
                                                                                department:
                                                                                    dept,
                                                                            },
                                                                        )}
                                                                    </p>
                                                                ) : (
                                                                    <Combobox
                                                                        options={
                                                                            reviewerOptions
                                                                        }
                                                                        value={
                                                                            reviewerSelections[
                                                                                dept
                                                                            ] ??
                                                                            ''
                                                                        }
                                                                        onChange={(
                                                                            value,
                                                                        ) =>
                                                                            setReviewerFor(
                                                                                dept,
                                                                                value,
                                                                            )
                                                                        }
                                                                        placeholder={t(
                                                                            'wizard.review.reviewer_placeholder',
                                                                        )}
                                                                        searchPlaceholder={t(
                                                                            'wizard.review.reviewer_search',
                                                                        )}
                                                                    />
                                                                )}
                                                                <input
                                                                    type="hidden"
                                                                    name={`reviewer_user_ids[${dept}]`}
                                                                    value={
                                                                        reviewerSelections[
                                                                            dept
                                                                        ] ?? ''
                                                                    }
                                                                />
                                                            </>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        <div className="grid gap-2 sm:w-2/3">
                                            <Label htmlFor="approver_user_id">
                                                {t('wizard.review.approver')}
                                            </Label>
                                            <Combobox
                                                options={approverOptions}
                                                value={approverId}
                                                onChange={setApproverId}
                                                placeholder={t(
                                                    'wizard.review.approver_placeholder',
                                                )}
                                                searchPlaceholder={t(
                                                    'wizard.review.approver_search',
                                                )}
                                            />
                                            <input
                                                type="hidden"
                                                name="approver_user_id"
                                                value={approverId}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        asChild
                                    >
                                        <Link href={backHref}>
                                            {t('common.actions.back')}
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        asChild
                                    >
                                        <Link href={reportShow(trial.id).url}>
                                            {t('wizard.review.view_detail')}
                                        </Link>
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            departments.length === 0 ||
                                            !allReviewersAssigned ||
                                            !approverId
                                        }
                                    >
                                        {t('wizard.review.submit')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" asChild>
                            <Link href={backHref}>
                                {t('common.actions.back')}
                            </Link>
                        </Button>
                        <Button type="button" asChild>
                            <Link href={reportShow(trial.id).url}>
                                {t('wizard.review.view_detail')}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

TrialReview.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Review', href: '#' },
    ],
};
