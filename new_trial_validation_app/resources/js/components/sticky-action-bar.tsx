import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Wizard action row (Back / Save & Continue / ...) pinned to the bottom of the
 * viewport, so it stays reachable on long pages (big tables, many photos)
 * without scrolling to the very end. Sticks inside the page container, so it
 * rests in its normal spot once the content is short enough.
 */
export function StickyActionBar({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80 print:hidden',
                className,
            )}
        >
            {children}
        </div>
    );
}
