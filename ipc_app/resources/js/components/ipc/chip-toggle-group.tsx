import { cn } from '@/lib/utils';

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
                            active ? 'border-primary bg-primary/[0.08] text-primary' : 'border-border bg-background text-muted-foreground',
                        )}
                    >
                        {option}
                    </button>
                );
            })}
        </div>
    );
}
