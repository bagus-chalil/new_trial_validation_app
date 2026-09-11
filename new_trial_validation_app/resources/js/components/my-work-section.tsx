import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CircleCheckBig,
    ClipboardCheck,
    Clock,
    FileEdit,
} from 'lucide-react';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { TrialStepProgress } from '@/components/trial-step-progress';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { trialStatusBadgeClassName } from '@/lib/trial-status';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as reviewsIndex } from '@/routes/reviews';
import { edit as editTrial } from '@/routes/trials';

type MyTrial = {
    id: number;
    trial_code: string;
    product_name: string;
    progress_status: string;
    current_step: string | null;
    pending_with: string | null;
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

export type MyWork = {
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
                                <Badge
                                    variant="outline"
                                    className={trialStatusBadgeClassName(
                                        trial.progress_status,
                                        null,
                                    )}
                                >
                                    {trial.progress_status}
                                </Badge>
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {trial.product_name}
                            </div>
                            <TrialStepProgress trial={trial} />
                            {trial.pending_with && (
                                <div className="text-xs text-muted-foreground">
                                    Menunggu: {trial.pending_with}
                                </div>
                            )}
                        </div>
                    ))
                )}
                {total > trials.length && (
                    <p className="text-xs text-muted-foreground">
                        +{total - trials.length} trial lainnya.
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

    const showDrafts = myWork.draftTrialsTotal > 0;
    const showNeedsRevision = myWork.needsRevisionTrialsTotal > 0;
    const showInProgress = myWork.inProgressTrialsTotal > 0;
    const showReviews = canReviewTrials;
    const showApprovals = canApproveTrials;

    if (
        !showDrafts &&
        !showNeedsRevision &&
        !showInProgress &&
        !showReviews &&
        !showApprovals
    ) {
        return null;
    }

    return (
        <section className="space-y-3">
            <h2 className="text-lg font-semibold">My Work</h2>
            <div className="grid gap-4 lg:grid-cols-3">
                {showDrafts && (
                    <OwnTrialCard
                        icon={FileEdit}
                        title="Draft Saya (Lanjutkan)"
                        emptyMessage="Tidak ada draft yang perlu dilanjutkan."
                        trials={myWork.draftTrials}
                        total={myWork.draftTrialsTotal}
                        editable
                        actionHint="Klik Trial ID untuk melanjutkan pengisian form."
                    />
                )}

                {showNeedsRevision && (
                    <OwnTrialCard
                        icon={AlertTriangle}
                        title="Perlu Direvisi"
                        emptyMessage="Tidak ada trial yang perlu direvisi."
                        trials={myWork.needsRevisionTrials}
                        total={myWork.needsRevisionTrialsTotal}
                        editable
                        actionHint="Klik Trial ID untuk memperbaiki dan submit ulang."
                    />
                )}

                {showInProgress && (
                    <OwnTrialCard
                        icon={Clock}
                        title="Sedang Berjalan"
                        emptyMessage="Tidak ada trial Anda yang sedang berjalan."
                        trials={myWork.inProgressTrials}
                        total={myWork.inProgressTrialsTotal}
                        editable={false}
                        actionHint="Sedang menunggu review/approval — tidak ada aksi Anda saat ini."
                    />
                )}

                {showReviews && (
                    <Card>
                        <CardHeader className="flex-row items-center gap-2 space-y-0">
                            <ClipboardCheck className="size-4 text-muted-foreground" />
                            <CardTitle className="text-sm">
                                Perlu Review Saya ({myWork.pendingReviewsTotal})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {myWork.pendingReviews.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Tidak ada review yang menunggu Anda saat
                                    ini.
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
                                    Lihat semua Need Review
                                </Link>
                                {myWork.recentlyReviewed.length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        Terakhir:{' '}
                                        {myWork.recentlyReviewed[0]
                                            .trial_code ?? '-'}
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
                                Perlu Approval Saya (
                                {myWork.pendingApprovalsTotal})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {myWork.pendingApprovals.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Tidak ada trial yang menunggu approval Anda
                                    saat ini.
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
                                    Lihat semua Need Approval
                                </Link>
                                {myWork.recentlyDecided.length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        Terakhir:{' '}
                                        {myWork.recentlyDecided[0].trial_code} (
                                        {
                                            myWork.recentlyDecided[0]
                                                .final_decision
                                        }
                                        )
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
