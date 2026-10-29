import { BottomNav } from '@/components/ipc/bottom-nav';
import { NavRail } from '@/components/ipc/nav-rail';
import { returnToFromUrl } from '@/lib/return-to';
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
    const { props, url } = usePage<SharedData>();
    // A `?return_to=` from the page that sent the user here beats the fixed parent.
    const backTarget = returnToFromUrl(url) ?? backHref;

    return (
        <div className="bg-background flex h-screen flex-col supports-[height:100dvh]:h-dvh md:flex-row">
            <NavRail />

            <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                {header ?? (
                    <header className="border-border-soft bg-card flex shrink-0 items-center gap-3 border-b px-5 py-4 md:px-6">
                        {backTarget && (
                            <Link
                                href={backTarget}
                                aria-label="Kembali"
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

                {props.flash?.error && (
                    <div className="mx-5 mb-1 shrink-0 rounded-2xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-800 md:mx-6">
                        {props.flash.error}
                    </div>
                )}

                {/* `relative` keeps absolutely-positioned descendants (e.g. Radix's hidden native
                    <select>) inside this clip instead of stretching the document below the shell. */}
                <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden">{children}</div>
                <footer className="border-border-soft bg-card text-muted-foreground mb-[calc(82px+env(safe-area-inset-bottom,0px))] flex shrink-0 flex-wrap items-center justify-center gap-x-2 border-t px-4 py-2 text-center text-[10px] md:mb-0 md:justify-between md:px-6 md:text-[11px]">
                    <span className="font-semibold">IPC App versi 1.5.0</span>
                    <span>created by IT Cosmax IDN</span>
                </footer>
            </div>

            <BottomNav />
        </div>
    );
}
