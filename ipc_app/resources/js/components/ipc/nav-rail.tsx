import { AppsDrawer } from '@/components/ipc/apps-drawer';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, Grid2X2, LayoutGrid } from 'lucide-react';
import { useState } from 'react';

function RailLink({ href, icon: Icon, active }: { href: string; icon: typeof LayoutGrid; active: boolean }) {
    return (
        <Link
            href={href}
            className={cn(
                'flex size-12 items-center justify-center rounded-2xl transition-colors',
                active ? 'bg-primary/[0.08] text-primary' : 'text-muted-foreground/70 hover:bg-muted',
            )}
        >
            <Icon className="size-[21px]" strokeWidth={2} />
        </Link>
    );
}

export function NavRail() {
    const { url, props } = usePage<SharedData>();
    const getInitials = useInitials();
    const [drawerOpen, setDrawerOpen] = useState(false);

    const isDashboard = url === '/dashboard';
    const isBatches = url.startsWith('/batches');

    return (
        <>
            <aside className="border-border-soft bg-card hidden w-[76px] shrink-0 flex-col items-center border-r py-5 md:flex">
                <div className="bg-primary flex size-[38px] items-center justify-center rounded-xl">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M9 12l2 2 4-4" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                </div>

                <nav className="flex flex-1 flex-col items-center justify-center gap-3.5">
                    <RailLink href="/dashboard" icon={LayoutGrid} active={isDashboard} />
                    <RailLink href="/batches" icon={ClipboardList} active={isBatches} />
                    <button
                        type="button"
                        onClick={() => setDrawerOpen(true)}
                        className={cn(
                            'flex size-12 items-center justify-center rounded-2xl transition-colors',
                            drawerOpen ? 'bg-primary/[0.08] text-primary' : 'text-muted-foreground/70 hover:bg-muted',
                        )}
                        aria-label="Apps"
                    >
                        <Grid2X2 className="size-[21px]" strokeWidth={2} />
                    </button>
                </nav>

                <button
                    type="button"
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

