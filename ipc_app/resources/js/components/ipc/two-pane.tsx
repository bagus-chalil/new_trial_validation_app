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
                    'bg-card w-full shrink-0 flex-col overflow-y-auto border-r md:flex md:w-[280px] lg:w-[336px]',
                    mobilePrimary === 'list' ? 'flex' : 'hidden',
                )}
            >
                {list}
            </aside>
            <div className={cn('min-w-0 flex-1 overflow-y-auto', mobilePrimary === 'list' ? 'hidden md:block' : 'block')}>{children}</div>
        </div>
    );
}
