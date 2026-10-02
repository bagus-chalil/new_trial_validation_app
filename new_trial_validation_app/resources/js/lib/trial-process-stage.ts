export type TrialProcessTrial = {
    progress_status: string;
    final_decision?: string | null;
};

export type TrialProcessTone = 'active' | 'success' | 'warning' | 'danger';

export type TrialProcessStage = {
    index: number;
    tone: TrialProcessTone;
    /** progress_status to label this stage with (see trialStatusLabel()). */
    status: string;
};

/**
 * Big-picture workflow stages (Draft -> In Review -> Ready for Approval ->
 * Approved), distinct from TRIAL_WIZARD_STEPS in trial-wizard.ts — that one
 * tracks the 6 Staff-facing form steps *inside* Draft and reads "Wizard
 * selesai" for everything past Draft, which doesn't answer "how far along in
 * the review/approval process is this trial"
 */
export const PROCESS_STAGES = [
    'Draft',
    'In Review',
    'Ready for Approval',
    'Approved',
] as const;

export function resolveTrialProcessStage(
    trial: TrialProcessTrial | null | undefined,
): TrialProcessStage | null {
    if (!trial) {
        return null;
    }

    if (
        trial.progress_status === 'Rejected' ||
        trial.final_decision === 'Rejected'
    ) {
        return { index: 2, tone: 'danger', status: 'Rejected' };
    }

    switch (trial.progress_status) {
        case 'Draft':
            return { index: 0, tone: 'active', status: 'Draft' };
        case 'In Review':
            return { index: 1, tone: 'active', status: 'In Review' };
        case 'Ready for Approval':
            return { index: 2, tone: 'active', status: 'Ready for Approval' };
        case 'Approved':
            return { index: 3, tone: 'success', status: 'Approved' };
        case 'Need Revision':
            return { index: 0, tone: 'warning', status: 'Need Revision' };
        default:
            return { index: 0, tone: 'active', status: trial.progress_status };
    }
}
