import { cn } from '@/lib/utils';
import { type ReactNode } from 'react';

export function TwoPane({
    list,
    children,
    mobilePrimary = 'children',
}: {
    list: ReactNode;
    children: ReactNode;
    mobilePrimary?: 'list' | 'children';
}) {
    return (
        <div className="flex min-h-0 flex-1">
            <aside
                className={cn(
                    'bg-card min-h-0 w-full shrink-0 flex-col overflow-hidden border-r md:flex md:w-[280px] lg:w-[336px]',
                    mobilePrimary === 'list' ? 'flex' : 'hidden',
                )}
            >
                {list}
            </aside>
            {/* `relative` makes this scroll box the containing block for absolutely-positioned
                descendants (e.g. Radix Select's hidden native <select>) — without it they escape
                the overflow clip and stretch the whole document, leaving a blank band on scroll. */}
            <div className={cn('relative min-w-0 flex-1 overflow-y-auto', mobilePrimary === 'list' ? 'hidden md:block' : 'block')}>{children}</div>
        </div>
    );
}
