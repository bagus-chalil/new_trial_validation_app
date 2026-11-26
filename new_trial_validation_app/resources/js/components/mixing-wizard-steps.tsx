import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Fragment } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import {
    MIXING_STEPS,
    mixingUrl,
    resolveMixingCompletedSteps,
} from '@/lib/validation-steps';

type Props = {
    currentStep: number;
    mixing?: {
        id: number;
        progress_status: string;
        current_step: string | null;
        files_count: number;
    } | null;
};

export function MixingWizardSteps({ currentStep, mixing }: Props) {
    const { t } = useTranslation();
    const completed = Math.max(
        mixing
            ? resolveMixingCompletedSteps(
                  mixing.progress_status,
                  mixing.current_step,
                  mixing.files_count,
              )
            : 0,
        currentStep - 1,
    );

    return (
        <nav className="mb-2">
            <p className="mb-3 text-sm font-medium text-muted-foreground">
                {t('mixing.step_of', {
                    current: currentStep,
                    total: MIXING_STEPS.length,
                })}
            </p>
            <ol className="flex flex-wrap items-start gap-x-1 gap-y-4">
                {MIXING_STEPS.map((step, i) => {
                    const state =
                        step.number === currentStep
                            ? 'current'
                            : step.number <= completed
                              ? 'complete'
                              : 'upcoming';
                    const body = (
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
                                {t(step.labelKey)}
                            </span>
                        </>
                    );
                    const cls = cn(
                        'flex w-20 flex-col items-center gap-1 text-center',
                        state === 'upcoming' && 'opacity-50',
                    );

                    return (
                        <Fragment key={step.key}>
                            <li className="flex items-center">
                                {i > 0 && (
                                    <span
                                        aria-hidden
                                        className="mx-2 h-px w-6 shrink-0 bg-border sm:w-10"
                                    />
                                )}
                                {mixing ? (
                                    <Link
                                        href={mixingUrl.step(
                                            step.key,
                                            mixing.id,
                                        )}
                                        className={cls}
                                    >
                                        {body}
                                    </Link>
                                ) : (
                                    <div className={cls}>{body}</div>
                                )}
                            </li>
                        </Fragment>
                    );
                })}
            </ol>
        </nav>
    );
}
