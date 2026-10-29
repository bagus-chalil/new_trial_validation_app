import { AppsDrawer } from '@/components/ipc/apps-drawer';
import { CosmaxLogo } from '@/components/ipc/cosmax-logo';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Archive, ClipboardCheck, ClipboardList, FlaskConical, Grid2X2, LayoutGrid } from 'lucide-react';
import { useState } from 'react';

function RailLink({ href, icon: Icon, active, label }: { href: string; icon: typeof LayoutGrid; active: boolean; label: string }) {
    return (
        <Link
            href={href}
            aria-label={label}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'focus-visible:ring-primary flex min-h-14 w-16 flex-col items-center justify-center gap-1 rounded-xl transition-colors focus-visible:ring-2 focus-visible:outline-none',
                active ? 'bg-primary/[0.08] text-primary' : 'text-muted-foreground/70 hover:bg-muted',
            )}
        >
            <Icon className="size-[21px]" strokeWidth={2} />
            <span className="text-[10px] font-semibold">{label}</span>
        </Link>
    );
}

export function NavRail() {
    const { url, props } = usePage<SharedData>();
    const getInitials = useInitials();
    const [drawerOpen, setDrawerOpen] = useState(false);

    const isDashboard = url === '/dashboard';
    const isBatches = url.startsWith('/batches');
    const isApprovals = url.startsWith('/approvals');
    const isArchive = url.startsWith('/archive');
    const isMicroChecks = url.startsWith('/micro-checks');

    return (
        <>
            <aside className="border-border-soft bg-card hidden w-[76px] shrink-0 flex-col items-center border-r py-5 md:flex">
                <Link
                    href="/dashboard"
                    aria-label="COSMAX IPC Home"
                    className="focus-visible:ring-primary rounded-lg focus-visible:ring-2 focus-visible:outline-none"
                >
                    <CosmaxLogo />
                </Link>

                <nav className="flex flex-1 flex-col items-center justify-center gap-3.5">
                    <RailLink href="/dashboard" icon={LayoutGrid} active={isDashboard} label="Home" />
                    <RailLink href="/batches" icon={ClipboardList} active={isBatches} label="Batch" />
                    {props.canApproveIpc && <RailLink href="/approvals" icon={ClipboardCheck} active={isApprovals} label="Approval" />}
                    {props.canRecordMicroCheck && <RailLink href="/micro-checks" icon={FlaskConical} active={isMicroChecks} label="Micro" />}
                    {props.canManageMaster && <RailLink href="/archive" icon={Archive} active={isArchive} label="Arsip" />}
                    <button
                        type="button"
                        onClick={() => setDrawerOpen(true)}
                        className={cn(
                            'focus-visible:ring-primary flex min-h-14 w-16 flex-col items-center justify-center gap-1 rounded-xl transition-colors focus-visible:ring-2 focus-visible:outline-none',
                            drawerOpen ? 'bg-primary/[0.08] text-primary' : 'text-muted-foreground/70 hover:bg-muted',
                        )}
                        aria-label="Apps"
                    >
                        <Grid2X2 className="size-[21px]" strokeWidth={2} />
                        <span className="text-[10px] font-semibold">Apps</span>
                    </button>
                </nav>

                <button
                    type="button"
                    aria-label="Buka profil dan pengaturan"
                    onClick={() => setDrawerOpen(true)}
                    className="bg-primary/[0.1] text-primary flex size-[38px] items-center justify-center rounded-full text-[13px] font-bold"
                >
                    {getInitials(props.auth.user.name)}
                </button>
            </aside>

            <AppsDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />
        </>
    );
}
