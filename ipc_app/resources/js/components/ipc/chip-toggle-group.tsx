import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle2, Circle, MinusCircle } from 'lucide-react';

// Three-colour semantic, shared by every checklist toggle in the app:
//   red    — a "Not ..." option (Not Available, Not Conform, Bulk Not Yet Release, ...): a real
//            finding that needs attention.
//   orange — N/A: the item doesn't apply to this product, so it's neither a pass nor a defect.
//            Requested by the user 2026-09-03, superseding the earlier "N/A stays blue" call
//            made when N/A existed on only one field.
//   blue   — everything else (Available, Conform, Complete, Passed, ...).
const NEGATIVE_OPTION_PATTERN = /\b(not|rejected|failed)\b/i;
const NOT_APPLICABLE_PATTERN = /^n\/?a$/i;

/** Read-only badge for a single already-decided value, sharing the same red/orange/blue semantic as ChipToggleGroup — used in report/approval views where the value can't be changed inline. */
export function StatusChip({ value }: { value: string | null | undefined }) {
    if (!value) {
        return <span className="text-muted-foreground/60 text-[13px] font-medium italic">Belum diisi</span>;
    }

    const isNotApplicable = NOT_APPLICABLE_PATTERN.test(value.trim());
    const isNegative = !isNotApplicable && NEGATIVE_OPTION_PATTERN.test(value);

    return (
        <span
            className={cn(
                'inline-flex rounded-lg border-[1.5px] px-2.5 py-1 text-[12.5px] font-bold',
                !isNegative && !isNotApplicable && 'border-primary/30 bg-primary/[0.08] text-primary',
                isNegative && 'border-red-500/40 bg-red-500/10 text-red-600 dark:text-red-400',
                isNotApplicable && 'border-orange-500/40 bg-orange-500/10 text-orange-600 dark:text-orange-400',
            )}
        >
            {value}
        </span>
    );
}

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
        <div className="grid gap-1.5">
            <div className="bg-muted/50 flex flex-wrap gap-2 rounded-2xl p-1.5" role="radiogroup" aria-label={name}>
                {options.map((option, index) => {
                    const active = value === option;
                    const isNotApplicable = NOT_APPLICABLE_PATTERN.test(option.trim());
                    const isNegative = !isNotApplicable && NEGATIVE_OPTION_PATTERN.test(option);

                    return (
                        <button
                            key={option}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            tabIndex={active || (!value && index === 0) ? 0 : -1}
                            disabled={disabled}
                            onClick={() => onChange(option)}
                            onKeyDown={(event) => {
                                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;
                                event.preventDefault();
                                const next =
                                    event.key === 'Home'
                                        ? 0
                                        : event.key === 'End'
                                          ? options.length - 1
                                          : (index + (event.key === 'ArrowLeft' || event.key === 'ArrowUp' ? -1 : 1) + options.length) %
                                            options.length;
                                onChange(options[next]);
                                event.currentTarget.parentElement?.querySelectorAll<HTMLButtonElement>('[role="radio"]')[next]?.focus();
                            }}
                            className={cn(
                                'focus-visible:outline-primary flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl border-[1.5px] px-2.5 py-2 text-center text-[13px] font-bold transition-all focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60',
                                !active && 'bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground border-transparent',
                                active && !isNegative && !isNotApplicable && 'border-primary bg-primary/[0.08] text-primary',
                                active && isNegative && 'border-red-500 bg-red-500/10 text-red-600 dark:text-red-400',
                                active && isNotApplicable && 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400',
                            )}
                        >
                            {active ? (
                                isNegative ? (
                                    <AlertCircle className="size-4 shrink-0" />
                                ) : isNotApplicable ? (
                                    <MinusCircle className="size-4 shrink-0" />
                                ) : (
                                    <CheckCircle2 className="size-4 shrink-0" />
                                )
                            ) : (
                                <Circle className="size-3.5 shrink-0 opacity-40" />
                            )}
                            {option}
                        </button>
                    );
                })}
            </div>
            {value && NEGATIVE_OPTION_PATTERN.test(value) && (
                <p className="flex items-start gap-1.5 text-[11px] leading-relaxed text-red-600 dark:text-red-400">
                    <AlertCircle className="mt-0.5 size-3 shrink-0" />
                    Temuan tercatat. Periksa kembali kondisi aktual dan isi catatan jika diperlukan.
                </p>
            )}
        </div>
    );
}
