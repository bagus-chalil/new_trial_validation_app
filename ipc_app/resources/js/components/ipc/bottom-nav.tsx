import { AppsDrawer } from '@/components/ipc/apps-drawer';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, LayoutGrid, Plus, Grid2X2 } from 'lucide-react';
import { useState } from 'react';

function TabLink({ href, icon: Icon, label, active }: { href: string; icon: React.ElementType; label: string; active: boolean }) {
    return (
        <Link
            href={href}
            className={cn(
                'flex min-h-[52px] flex-1 flex-col items-center justify-center gap-1',
                active ? 'text-primary' : 'text-muted-foreground/60',
            )}
        >
            <Icon className="size-[22px]" strokeWidth={2} />
            <span className={cn('text-[11px]', active ? 'font-semibold' : 'font-medium')}>{label}</span>
        </Link>
    );
}

export function BottomNav() {
    const { url } = usePage();
    const [drawerOpen, setDrawerOpen] = useState(false);

    const isDashboard = url === '/dashboard';
    const isBatches = url.startsWith('/batches') && url !== '/batches/create';

    return (
        <>
            <nav className="border-border-soft bg-card fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t px-6 pt-2.5 pb-[calc(14px+env(safe-area-inset-bottom,0px))] md:hidden">
                <TabLink href="/dashboard" icon={LayoutGrid} label="Home" active={isDashboard} />
                <TabLink href="/batches" icon={ClipboardList} label="Batch" active={isBatches} />

                <div className="flex flex-1 items-center justify-center">
                    <Link
                        href="/batches/create"
                        className="bg-primary -mt-7 flex size-[52px] items-center justify-center rounded-full shadow-[0_8px_20px_-6px_rgba(47,111,237,0.5)]"
                        aria-label="Batch Baru"
                    >
                        <Plus className="size-6 text-white" strokeWidth={2.4} />
                    </Link>
                </div>

                <button
                    type="button"
                    onClick={() => setDrawerOpen(true)}
                    className={cn(
                        'flex min-h-[52px] flex-1 flex-col items-center justify-center gap-1',
                        drawerOpen ? 'text-primary' : 'text-muted-foreground/60',
                    )}
                >
                    <Grid2X2 className="size-[22px]" strokeWidth={2} />
                    <span className={cn('text-[11px]', drawerOpen ? 'font-semibold' : 'font-medium')}>Apps</span>
                </button>
            </nav>

            <AppsDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} />
        </>
    );
}
