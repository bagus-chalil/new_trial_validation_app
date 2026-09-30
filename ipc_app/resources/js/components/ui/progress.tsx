import { cn } from '@/lib/utils';

/** Determinate when `value` (0-100) is given, otherwise an indeterminate sliding bar. */
export function Progress({ value, className, indicatorClassName }: { value?: number | null; className?: string; indicatorClassName?: string }) {
    const determinate = value !== undefined && value !== null;

    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={determinate ? Math.round(value) : undefined}
            className={cn('bg-accent relative h-2 w-full overflow-hidden rounded-full', className)}
        >
            {determinate ? (
                <div
                    className={cn('bg-primary h-full rounded-full transition-[width] duration-500 ease-out', indicatorClassName)}
                    style={{ width: `${Math.min(100, Math.max(0, value))}%` }}
                />
            ) : (
                <div className={cn('bg-primary animate-progress-indeterminate absolute inset-y-0 w-1/3 rounded-full', indicatorClassName)} />
            )}
        </div>
    );
}
