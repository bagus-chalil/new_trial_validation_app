export type TrialWizardStepKey =
    | 'header'
    | 'validation'
    | 'weighing-packaging'
    | 'weighing-filling'
    | 'attachments'
    | 'review';

export type TrialWizardStep = {
    key: TrialWizardStepKey;
    number: number;
    label: string;
};

export const TRIAL_WIZARD_STEPS: TrialWizardStep[] = [
    { key: 'header', number: 1, label: 'Informasi Header' },
    { key: 'validation', number: 2, label: 'Validation' },
    { key: 'weighing-packaging', number: 3, label: 'Weighing (Packaging)' },
    { key: 'weighing-filling', number: 4, label: 'Weighing (Filling)' },
    { key: 'attachments', number: 5, label: 'Attachments' },
    { key: 'review', number: 6, label: 'Review & Submit' },
];

export type TrialWizardTrial = {
    id: number;
    current_step: string | null;
    progress_status: string;
    attachments_count?: number;
};

/**
 * How many of the 6 Staff-facing wizard steps this trial has actually
 * completed, per its real current_step/progress_status in the DB — not per
 * which page of the (still partially-unbuilt) new app is open. This app
 * shares its database with the legacy PHP app, so a trial may already have
 * been progressed past Header/Validation/Weighing there.
 *
 * Mapping ported from the legacy current_step writes in public/index.php and
 * CreateTrial::__invoke() (sets current_step='Validation' immediately on
 * create, i.e. Header is complete the moment a trial exists).
 *
 * Attachments is the one step current_step can never signal completion of by
 * itself: uploads happen incrementally (no submit-to-advance action), so
 * current_step just stays 'Attachment' whether 0 or 10 photos have been
 * uploaded. attachments_count is the only way to tell those apart — at least
 * one photo means the step is done, matching legacy's own completeness check
 * (CheckTrialCompleteness never requires attachments either, but the wizard
 * nav should still reflect real progress, not just "not yet advanced").
 */
export function resolveTrialCompletedSteps(
    trial: TrialWizardTrial | null | undefined,
): number | null {
    if (!trial) {
        return null;
    }

    if (trial.progress_status !== 'Draft') {
        return 6;
    }

    switch (trial.current_step) {
        case 'Validation':
            return 1;
        case 'WeighingPackaging':
            return 2;
        case 'WeighingFilling':
            return 3;
        case 'Attachment':
            return (trial.attachments_count ?? 0) > 0 ? 5 : 4;
        default:
            return 1;
    }
}
