import { BottomNav } from '@/components/ipc/bottom-nav';
import { NavRail } from '@/components/ipc/nav-rail';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type ReactNode } from 'react';

export function IpcShell({
    title,
    subtitle,
    backHref,
    headerActions,
    header,
    progressPct,
    children,
}: {
    title: string;
    subtitle?: string;
    backHref?: string;
    headerActions?: ReactNode;
    header?: ReactNode;
    progressPct?: number;
    children: ReactNode;
}) {
    const { props } = usePage<SharedData>();

    return (
        <div className="bg-background flex h-screen flex-col md:flex-row">
            <NavRail />

            <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                {header ?? (
                    <header className="flex shrink-0 items-center gap-3 px-5 py-5 md:px-6">
                        {backHref && (
                            <Link
                                href={backHref}
                                className="border-border-soft bg-card text-foreground flex size-11 shrink-0 items-center justify-center rounded-2xl border"
                            >
                                <ArrowLeft className="size-[18px]" strokeWidth={2.2} />
                            </Link>
                        )}
                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-[19px] font-bold tracking-tight">{title}</h1>
                            {subtitle && <p className="text-muted-foreground/70 mt-0.5 truncate text-[12.5px] font-medium">{subtitle}</p>}
                        </div>
                        {headerActions && <div className="flex shrink-0 items-center gap-2.5">{headerActions}</div>}
                    </header>
                )}

                {progressPct !== undefined && (
                    <div className="bg-border-soft mx-5 mb-4 h-1.5 shrink-0 overflow-hidden rounded-full md:mx-6">
                        <div className="bg-primary h-full rounded-full transition-[width] duration-200" style={{ width: `${progressPct}%` }} />
                    </div>
                )}

                {props.flash?.success && (
                    <div className="mx-5 mb-1 shrink-0 rounded-2xl border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-medium text-green-800 md:mx-6">
                        {props.flash.success}
                    </div>
                )}

                <div className="flex min-h-0 flex-1 flex-col overflow-hidden pb-16 md:pb-0">{children}</div>
            </div>

            <BottomNav />
        </div>
    );
}
