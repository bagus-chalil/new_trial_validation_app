import { cn } from '@/lib/utils';

// Three-colour semantic, shared by every checklist toggle in the app:
//   red    — a "Not ..." option (Not Available, Not Conform, Bulk Not Yet Release, ...): a real
//            finding that needs attention.
//   orange — N/A: the item doesn't apply to this product, so it's neither a pass nor a defect.
//            Requested by the user 2026-09-03, superseding the earlier "N/A stays blue" call
//            made when N/A existed on only one field.
//   blue   — everything else (Available, Conform, Complete, Passed, ...).
const NEGATIVE_OPTION_PATTERN = /\bnot\b/i;
const NOT_APPLICABLE_PATTERN = /^n\/?a$/i;

export function ChipToggleGroup({
    options,
    value,
    onChange,
    disabled,
    name,
}: {
    options: string[];
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    name?: string;
}) {
    return (
        <div className="flex flex-wrap gap-2" role="radiogroup" aria-label={name}>
            {options.map((option) => {
                const active = value === option;
                const isNotApplicable = NOT_APPLICABLE_PATTERN.test(option.trim());
                const isNegative = !isNotApplicable && NEGATIVE_OPTION_PATTERN.test(option);

                return (
                    <button
                        key={option}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        disabled={disabled}
                        onClick={() => onChange(option)}
                        className={cn(
                            'min-h-11 flex-1 rounded-xl border-[1.5px] px-2.5 py-2 text-center text-[13px] font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                            !active && 'border-border bg-background text-muted-foreground',
                            active && !isNegative && !isNotApplicable && 'border-primary bg-primary/[0.08] text-primary',
                            active && isNegative && 'border-red-500 bg-red-500/10 text-red-600 dark:text-red-400',
                            active && isNotApplicable && 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400',
                        )}
                    >
                        {option}
                    </button>
                );
            })}
        </div>
    );
}
