import {
    PROCESS_STAGE_LABELS,
    resolveTrialProcessStage,
} from '@/lib/trial-process-stage';
import type { TrialProcessTrial } from '@/lib/trial-process-stage';
import { cn } from '@/lib/utils';

const DOT_CLASSNAMES: Record<string, string> = {
    active: 'bg-brand',
    success: 'bg-green-600 dark:bg-green-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-600 dark:bg-red-500',
};

const LABEL_CLASSNAMES: Record<string, string> = {
    active: 'text-foreground',
    success: 'text-green-700 dark:text-green-400',
    warning: 'text-amber-700 dark:text-amber-400',
    danger: 'text-red-700 dark:text-red-400',
};

/**
 * Big-picture stage tracker (Draft -> In Review -> Ready for Approval ->
 * Approved) for use wherever a viewer needs to know how far a trial has
 * gotten in the overall approval flow, not just its wizard-form progress
 * (see TrialStepProgress for that, which only means something during Draft).
 */
export function TrialProcessProgress({ trial }: { trial: TrialProcessTrial }) {
    const stage = resolveTrialProcessStage(trial);

    if (!stage) {
        return null;
    }

    const dotClass = DOT_CLASSNAMES[stage.tone];

    return (
        <div className="w-40 space-y-1.5">
            <div className="flex items-center">
                {PROCESS_STAGE_LABELS.map((label, i) => (
                    <div
                        key={label}
                        className="flex flex-1 items-center last:flex-none"
                    >
                        <div
                            className={cn(
                                'h-2.5 w-2.5 shrink-0 rounded-full border-2',
                                i <= stage.index
                                    ? cn(dotClass, 'border-transparent')
                                    : 'border-muted-foreground/30 bg-muted',
                            )}
                        />
                        {i < PROCESS_STAGE_LABELS.length - 1 && (
                            <div
                                className={cn(
                                    'h-0.5 flex-1',
                                    i < stage.index ? dotClass : 'bg-muted',
                                )}
                            />
                        )}
                    </div>
                ))}
            </div>
            <div className={cn('text-xs font-medium', LABEL_CLASSNAMES[stage.tone])}>
                {stage.label}
            </div>
        </div>
    );
}
