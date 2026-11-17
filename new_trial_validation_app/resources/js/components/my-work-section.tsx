import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleCheckBig,
    ClipboardCheck,
    Clock,
    FileEdit,
    FolderKanban,
    Lightbulb,
} from 'lucide-react';
import TrialController from '@/actions/App/Http/Controllers/TrialController';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { TrialStepProgress } from '@/components/trial-step-progress';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { improvementStatusLabel, projectStatusLabel } from '@/lib/project';
import {
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as projectsIndex, show as projectShow } from '@/routes/projects';
import {
    index as improvementsIndex,
    show as improvementShow,
} from '@/routes/projects/improvements';
import { index as reviewsIndex } from '@/routes/reviews';
import { edit as editTrial } from '@/routes/trials';

type MyTrial = {
    id: number;
    trial_code: string;
    product_name: string;
    progress_status: string;
    current_step: string | null;
    pending_with: string | null;
    can_delete?: boolean;
};

type PendingReview = {
    id: number;
    trial_id: number;
    trial_code: string;
    product_name: string;
    department: string;
};

type RecentlyReviewed = {
    id: number;
    trial_id: number;
    trial_code: string | null;
    product_name: string | null;
    reviewed_at: string | null;
};

type PendingApproval = {
    id: number;
    trial_code: string;
    product_name: string;
};

type RecentlyDecided = {
    id: number;
    trial_code: string;
    product_name: string;
    final_decision: string | null;
};

type MyProject = {
    id: number;
    name: string;
    code: string;
    status: string;
    progress: number;
    end_date: string | null;
};

type MyImprovement = {
    id: number;
    title: string;
    status: string;
};

export type MyWork = {
    myProjects: MyProject[];
    myProjectsTotal: number;
    myImprovements: MyImprovement[];
    myImprovementsTotal: number;
    canReviewImprovements: boolean;
    improvementsToReviewTotal: number;
    draftTrials: MyTrial[];
    draftTrialsTotal: number;
    needsRevisionTrials: MyTrial[];
    needsRevisionTrialsTotal: number;
    inProgressTrials: MyTrial[];
    inProgressTrialsTotal: number;
    pendingReviews: PendingReview[];
    pendingReviewsTotal: number;
    recentlyReviewed: RecentlyReviewed[];
    pendingApprovals: PendingApproval[];
    pendingApprovalsTotal: number;
    recentlyDecided: RecentlyDecided[];
};

function reportLink(id: number, children: React.ReactNode) {
    return (
        <Link
            href={TrialReportController.show(id).url}
            className="font-medium underline underline-offset-2"
        >
            {children}
        </Link>
    );
}

/**
 * A trial's own list item — its Trial ID links to the action the owner
 * actually needs to take (continue/fix the wizard form via `editTrial`),
 * not the read-only Report Summary the review/approval cards below link to.
 * Falls back to the report page when there's genuinely nothing to edit
 * (informational "Sedang Berjalan" card).
 */
function ownTrialLink(trial: MyTrial, editable: boolean) {
    const href = editable
        ? editTrial(trial.id).url
        : TrialReportController.show(trial.id).url;

    return (
        <Link href={href} className="font-medium underline underline-offset-2">
            {trial.trial_code}
        </Link>
    );
}

function OwnTrialCard({
    icon: Icon,
    title,
    emptyMessage,
    trials,
    total,
    editable,
    actionHint,
}: {
    icon: typeof FileEdit;
    title: string;
    emptyMessage: string;
    trials: MyTrial[];
    total: number;
    editable: boolean;
    actionHint?: string;
}) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader className="flex-row items-center gap-2 space-y-0">
                <Icon className="size-4 text-muted-foreground" />
                <CardTitle className="text-sm">
                    {title} ({total})
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {trials.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {emptyMessage}
                    </p>
                ) : (
                    trials.map((trial) => (
                        <div
                            key={trial.id}
                            className="space-y-1 border-b pb-2 last:border-b-0 last:pb-0"
                        >
                            <div className="flex items-center justify-between gap-2">
                                {ownTrialLink(trial, editable)}
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        className={trialStatusBadgeClassName(
                                            trial.progress_status,
                                            null,
                                        )}
                                    >
                                        {trialStatusLabel(
                                            t,
                                            trial.progress_status,
                                        )}
                                    </Badge>
                                    {trial.can_delete && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-auto px-2 py-0.5 text-xs text-destructive hover:text-destructive"
                                                >
                                                    {t('trials.table.delete')}
                                                </Button>
                                            }
                                            title={t(
                                                'trials.table.delete_title',
                                            )}
                                            description={t(
                                                'trials.table.delete_description',
                                                { code: trial.trial_code },
                                            )}
                                            confirmLabel={t(
                                                'trials.table.delete',
                                            )}
                                            formProps={TrialController.destroy.form(
                                                trial.id,
                                            )}
                                        />
                                    )}
                                </div>
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {trial.product_name}
                            </div>
                            <TrialStepProgress trial={trial} />
                            {trial.pending_with && (
                                <div className="text-xs text-muted-foreground">
                                    {t('dashboard.my_work.waiting', {
                                        name: trial.pending_with,
                                    })}
                                </div>
                            )}
                        </div>
                    ))
                )}
                {total > trials.length && (
                    <p className="text-xs text-muted-foreground">
                        {t('dashboard.my_work.more', {
                            count: total - trials.length,
                        })}
                    </p>
                )}
                {actionHint && trials.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {actionHint}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export function MyWorkSection({ myWork }: { myWork: MyWork }) {
    const { canReviewTrials, canApproveTrials } = usePage<{
        canReviewTrials: boolean;
        canApproveTrials: boolean;
    }>().props;
    const { t } = useTranslation();

    const showDrafts = myWork.draftTrialsTotal > 0;
    const showNeedsRevision = myWork.needsRevisionTrialsTotal > 0;
    const showInProgress = myWork.inProgressTrialsTotal > 0;
    const showReviews = canReviewTrials;
    const showApprovals = canApproveTrials;
    const showProjects = myWork.myProjectsTotal > 0;
    const showImprovements =
        myWork.myImprovementsTotal > 0 || myWork.improvementsToReviewTotal > 0;

    if (
        !showDrafts &&
        !showNeedsRevision &&
        !showInProgress &&
        !showReviews &&
        !showApprovals &&
        !showProjects &&
        !showImprovements
    ) {
        return null;
    }

    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">{t('common.nav.my_work')}</h2>
            <div className="grid gap-4 lg:grid-cols-3">
                {showDrafts && (
                    <OwnTrialCard
                        icon={FileEdit}
                        title={t('dashboard.my_work.drafts_title')}
                        emptyMessage={t('dashboard.my_work.drafts_empty')}
                        trials={myWork.draftTrials}
                        total={myWork.draftTrialsTotal}
                        editable
                        actionHint={t('dashboard.my_work.drafts_hint')}
                    />
                )}

                {showNeedsRevision && (
                    <OwnTrialCard
                        icon={AlertTriangle}
                        title={t('dashboard.my_work.revision_title')}
                        emptyMessage={t('dashboard.my_work.revision_empty')}
                        trials={myWork.needsRevisionTrials}
                        total={myWork.needsRevisionTrialsTotal}
                        editable
                        actionHint={t('dashboard.my_work.revision_hint')}
                    />
                )}

                {showInProgress && (
                    <OwnTrialCard
                        icon={Clock}
                        title={t('dashboard.my_work.in_progress_title')}
                        emptyMessage={t('dashboard.my_work.in_progress_empty')}
                        trials={myWork.inProgressTrials}
                        total={myWork.inProgressTrialsTotal}
                        editable={false}
                        actionHint={t('dashboard.my_work.in_progress_hint')}
                    />
                )}

                {showProjects && (
                    <Card>
                        <CardHeader className="flex-row items-center gap-2 space-y-0">
                            <FolderKanban className="size-4 text-muted-foreground" />
                            <CardTitle className="text-sm">
                                {t('projects.my_work.projects_title')} (
                                {myWork.myProjectsTotal})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {myWork.myProjects.map((project) => (
                                <div
                                    key={project.id}
                                    className="space-y-1 border-b pb-2 last:border-b-0 last:pb-0"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <Link
                                            href={projectShow(project.id).url}
                                            className="font-medium underline underline-offset-2"
                                        >
                                            {project.name}
                                        </Link>
                                        <Badge variant="outline">
                                            {projectStatusLabel(
                                                t,
                                                project.status,
                                            )}
                                        </Badge>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {project.progress}%
                                        {project.end_date
                                            ? ` · ${project.end_date}`
                                            : ''}
                                    </div>
                                </div>
                            ))}
                            {myWork.myProjectsTotal >
                                myWork.myProjects.length && (
                                <p className="text-xs text-muted-foreground">
                                    {t('dashboard.my_work.more', {
                                        count:
                                            myWork.myProjectsTotal -
                                            myWork.myProjects.length,
                                    })}
                                </p>
                            )}
                            <Link
                                href={projectsIndex().url}
                                className="text-xs underline underline-offset-2"
                            >
                                {t('projects.my_work.projects_view_all')}
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {showImprovements && (
                    <Card>
                        <CardHeader className="flex-row items-center gap-2 space-y-0">
                            <Lightbulb className="size-4 text-muted-foreground" />
                            <CardTitle className="text-sm">
                                {t('projects.my_work.improvements_title')} (
                                {myWork.myImprovementsTotal})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {myWork.myImprovements.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t('projects.my_work.improvements_empty')}
                                </p>
                            )}
                            {myWork.myImprovements.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between gap-2 border-b pb-2 last:border-b-0 last:pb-0"
                                >
                                    <Link
                                        href={improvementShow(item.id).url}
                                        className="font-medium underline underline-offset-2"
                                    >
                                        {item.title}
                                    </Link>
                                    <Badge variant="outline">
                                        {improvementStatusLabel(t, item.status)}
                                    </Badge>
                                </div>
                            ))}
                            {myWork.canReviewImprovements && (
                                <p className="text-xs text-muted-foreground">
                                    {t('projects.my_work.improvements_review', {
                                        count: myWork.improvementsToReviewTotal,
                                    })}
                                </p>
                            )}
                            <Link
                                href={improvementsIndex().url}
                                className="text-xs underline underline-offset-2"
                            >
                                {t('projects.my_work.improvements_view_all')}
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {showReviews && (
                    <Card>
                        <CardHeader className="flex-row items-center gap-2 space-y-0">
                            <ClipboardCheck className="size-4 text-muted-foreground" />
                            <CardTitle className="text-sm">
                                {t('dashboard.my_work.reviews_title')} (
                                {myWork.pendingReviewsTotal})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {myWork.pendingReviews.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('dashboard.my_work.reviews_empty')}
                                </p>
                            ) : (
                                myWork.pendingReviews.map((item) => (
                                    <div
                                        key={item.id}
                                        className="space-y-1 border-b pb-2 last:border-b-0 last:pb-0"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            {reportLink(
                                                item.trial_id,
                                                item.trial_code,
                                            )}
                                            <span className="text-xs text-muted-foreground">
                                                {item.department}
                                            </span>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {item.product_name}
                                        </div>
                                    </div>
                                ))
                            )}
                            <div className="flex items-center justify-between gap-2 pt-1">
                                <Link
                                    href={reviewsIndex().url}
                                    className="text-xs underline underline-offset-2"
                                >
                                    {t('dashboard.my_work.reviews_view_all')}
                                </Link>
                                {myWork.recentlyReviewed.length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        {t('dashboard.my_work.last', {
                                            code:
                                                myWork.recentlyReviewed[0]
                                                    .trial_code ?? '-',
                                        })}
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {showApprovals && (
                    <Card>
                        <CardHeader className="flex-row items-center gap-2 space-y-0">
                            <CircleCheckBig className="size-4 text-muted-foreground" />
                            <CardTitle className="text-sm">
                                {t('dashboard.my_work.approvals_title')} (
                                {myWork.pendingApprovalsTotal})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {myWork.pendingApprovals.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('dashboard.my_work.approvals_empty')}
                                </p>
                            ) : (
                                myWork.pendingApprovals.map((item) => (
                                    <div
                                        key={item.id}
                                        className="space-y-1 border-b pb-2 last:border-b-0 last:pb-0"
                                    >
                                        {reportLink(item.id, item.trial_code)}
                                        <div className="text-sm text-muted-foreground">
                                            {item.product_name}
                                        </div>
                                    </div>
                                ))
                            )}
                            <div className="flex items-center justify-between gap-2 pt-1">
                                <Link
                                    href={approvalsIndex().url}
                                    className="text-xs underline underline-offset-2"
                                >
                                    {t('dashboard.my_work.approvals_view_all')}
                                </Link>
                                {myWork.recentlyDecided.length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        {t('dashboard.my_work.last_decided', {
                                            code: myWork.recentlyDecided[0]
                                                .trial_code,
                                            decision: trialStatusLabel(
                                                t,
                                                myWork.recentlyDecided[0]
                                                    .final_decision ?? '-',
                                            ),
                                        })}
                                    </span>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </section>
    );
}
