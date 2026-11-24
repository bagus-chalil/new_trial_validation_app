import { cn } from '@/lib/utils';

/** Thin horizontal progress bar with the percentage next to it. */
export function ProgressBar({
    value,
    className,
}: {
    readonly value: number;
    readonly className?: string;
}) {
    const clamped = Math.max(0, Math.min(100, value));

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div
                className="h-2 min-w-16 flex-1 overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuenow={clamped}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className="h-full rounded-full bg-brand"
                    style={{ width: `${clamped}%` }}
                />
            </div>
            <span className="w-9 text-right text-xs text-muted-foreground tabular-nums">
                {clamped}%
            </span>
        </div>
    );
}
