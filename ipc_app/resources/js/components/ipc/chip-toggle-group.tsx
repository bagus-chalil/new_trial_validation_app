import { cn } from '@/lib/utils';

// A "Not ..." option (Not Available, Not Conform, Bulk Not Yet Release, Not Yet Complete, ...)
// highlights red instead of the default accent blue when selected — a quick visual flag that
// something needs attention. Neutral/positive options (Available, Conform, Complete, N/A, ...)
// keep the default blue. Confirmed with the user 2026-09-03; applies to every checklist toggle
// across the app going forward, not just this screen.
const NEGATIVE_OPTION_PATTERN = /\bnot\b/i;

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
                const isNegative = NEGATIVE_OPTION_PATTERN.test(option);

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
                            active && !isNegative && 'border-primary bg-primary/[0.08] text-primary',
                            active && isNegative && 'border-red-500 bg-red-500/10 text-red-600 dark:text-red-400',
                        )}
                    >
                        {option}
                    </button>
                );
            })}
        </div>
    );
}
