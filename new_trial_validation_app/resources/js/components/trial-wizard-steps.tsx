import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import {
    resolveTrialCompletedSteps,
    TRIAL_WIZARD_STEPS,
} from '@/lib/trial-wizard';
import type { TrialWizardStepKey, TrialWizardTrial } from '@/lib/trial-wizard';
import { cn } from '@/lib/utils';
import { edit as editTrial } from '@/routes/trials';
import { edit as attachmentsEdit } from '@/routes/trials/attachments';
import { show as reportShow } from '@/routes/trials/report';
import { edit as reviewEdit } from '@/routes/trials/review';
import { edit as validationEdit } from '@/routes/trials/validation';
import { edit as weighingEdit } from '@/routes/trials/weighing';

type StepState = 'complete' | 'current' | 'upcoming';

// Every wizard step's page only gates on `view` (canEdit is a page-local prop
// that renders read-only when editing isn't allowed — e.g. TrialPolicy::update()
// locks a trial once its review round has started), so anyone viewing this
// nav can safely jump to any other step: worst case that page renders
// read-only. Step 6 goes to the Report Summary once the trial has left Draft,
// since that's where the real review status/actions live by then — the
// Submit-for-Review form (trials.review.edit) is a Draft-only concept.
function stepHref(key: TrialWizardStepKey, trial: TrialWizardTrial): string {
    switch (key) {
        case 'header':
            return editTrial(trial.id).url;
        case 'validation':
            return validationEdit(trial.id).url;
        case 'weighing-packaging':
            return weighingEdit({ trial: trial.id, section: 'Packaging' }).url;
        case 'weighing-filling':
            return weighingEdit({ trial: trial.id, section: 'Filling' }).url;
        case 'attachments':
            return attachmentsEdit(trial.id).url;
        case 'review':
            return trial.progress_status === 'Draft'
                ? reviewEdit(trial.id).url
                : reportShow(trial.id).url;
    }
}

export function TrialWizardSteps({
    currentStep,
    trial,
}: {
    currentStep: number;
    trial?: TrialWizardTrial | null;
}) {
    const completedSteps = resolveTrialCompletedSteps(trial);
    const showProgressNote =
        completedSteps !== null && completedSteps > currentStep;
    // Attachments (unlike Validation/Weighing) never advances current_step
    // past itself on save — photos are uploaded incrementally, not in one
    // step-completing submit — so the backend has no way to signal "step 5
    // is done" short of the trial leaving Draft entirely. Reaching a later
    // step in this render (e.g. via the wizard's own "Continue" link) is
    // itself proof every earlier step is behind you, so it always floors the
    // complete-count.
    const displayCompletedSteps = Math.max(
        completedSteps ?? 0,
        currentStep - 1,
    );

    return (
        <nav aria-label="Trial progress" className="mb-2">
            <p className="mb-3 text-sm font-medium text-muted-foreground">
                Step {currentStep} of {TRIAL_WIZARD_STEPS.length}
            </p>
            <ol className="flex flex-wrap items-start gap-x-1 gap-y-4">
                {TRIAL_WIZARD_STEPS.map((step, i) => {
                    const state: StepState =
                        step.number === currentStep
                            ? 'current'
                            : step.number <= displayCompletedSteps
                              ? 'complete'
                              : 'upcoming';

                    return (
                        <li key={step.key} className="flex items-center">
                            {i > 0 && (
                                <span
                                    aria-hidden
                                    className="mx-2 h-px w-6 shrink-0 bg-border sm:w-10"
                                />
                            )}
                            {(() => {
                                const stepClassName = cn(
                                    'flex w-16 flex-col items-center gap-1 text-center',
                                    state === 'upcoming' && 'opacity-50',
                                );
                                const content = (
                                    <>
                                        <span
                                            className={cn(
                                                'flex size-8 items-center justify-center rounded-full border text-sm font-medium',
                                                state === 'current' &&
                                                    'border-brand bg-brand text-white',
                                                state === 'complete' &&
                                                    'border-brand bg-brand/10 text-brand',
                                                state === 'upcoming' &&
                                                    'border-border bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {state === 'complete' ? (
                                                <Check className="size-4" />
                                            ) : (
                                                step.number
                                            )}
                                        </span>
                                        <span className="text-xs leading-tight text-muted-foreground">
                                            {step.label}
                                        </span>
                                    </>
                                );

                                return trial ? (
                                    <Link
                                        href={stepHref(step.key, trial)}
                                        className={stepClassName}
                                    >
                                        {content}
                                    </Link>
                                ) : (
                                    <div className={stepClassName}>
                                        {content}
                                    </div>
                                );
                            })()}
                        </li>
                    );
                })}
            </ol>
            {showProgressNote && trial && completedSteps !== null && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Trial ini sudah berjalan sampai tahap{' '}
                    <strong>
                        {TRIAL_WIZARD_STEPS[completedSteps - 1].label}
                    </strong>{' '}
                    di sistem lama; layar ini hanya mengedit data header.
                </p>
            )}
        </nav>
    );
}
