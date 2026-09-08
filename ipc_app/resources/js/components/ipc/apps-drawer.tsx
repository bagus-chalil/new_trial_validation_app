import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useAppearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { FlaskConical, KeyRound, LogOut, MapPin, Moon, Package, Palette, Sun, Trash2, UserRound } from 'lucide-react';

interface AppItem {
    label: string;
    icon: React.ElementType;
    color: string;
    bg: string;
    href?: string;
    onClick?: () => void;
}

function AppIcon({ item, onClose }: { item: AppItem; onClose: () => void }) {
    const { label, icon: Icon, color, bg, href, onClick } = item;
    const inner = (
        <div className="flex flex-col items-center gap-2">
            <div className="flex size-[56px] items-center justify-center rounded-2xl shadow-sm" style={{ backgroundColor: bg }}>
                <Icon className="size-[26px]" style={{ color }} strokeWidth={2} />
            </div>
            <span className="w-[70px] text-center text-[11.5px] font-semibold leading-tight">{label}</span>
        </div>
    );
    if (href) return <Link href={href} onClick={onClose} className="active:scale-95 transition-transform">{inner}</Link>;
    return <button type="button" onClick={() => { onClick?.(); onClose(); }} className="active:scale-95 transition-transform">{inner}</button>;
}

export function AppsDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { auth } = usePage<SharedData>().props;
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();
    const isDark = appearance === 'dark';
    const handleClose = () => { cleanup(); onClose(); };

    const initials = auth.user.name.split(' ').map((w: string) => w[0]).slice(0, 2).join('');

    const sections: { title: string; items: AppItem[] }[] = [
        {
            title: 'Data',
            items: [
                { label: 'Tempat Sampah', icon: Trash2, color: '#71717a', bg: '#f4f4f5', href: route('recycle-bin.index') },
                { label: 'Master Line', icon: MapPin, color: '#2563eb', bg: '#eff6ff', href: route('master-lines.index') },
                { label: 'Master Produk', icon: Package, color: '#16a34a', bg: '#f0fdf4', href: route('master-products.index') },
                { label: 'Master Test Type', icon: FlaskConical, color: '#9333ea', bg: '#faf5ff', href: route('master-test-types.index') },
            ],
        },
        {
            title: 'Pengaturan',
            items: [
                { label: 'Profil', icon: UserRound, color: '#0891b2', bg: '#ecfeff', href: route('profile.edit') },
                { label: 'Password', icon: KeyRound, color: '#d97706', bg: '#fffbeb', href: route('password.edit') },
                {
                    label: isDark ? 'Mode Terang' : 'Mode Gelap',
                    icon: isDark ? Sun : Moon,
                    color: isDark ? '#d97706' : '#4b5563',
                    bg: isDark ? '#fffbeb' : '#f3f4f6',
                    onClick: () => updateAppearance(isDark ? 'light' : 'dark'),
                },
                { label: 'Tampilan', icon: Palette, color: '#db2777', bg: '#fdf2f8', href: route('appearance') },
            ],
        },
    ];

    return (
        <Sheet open={open} onOpenChange={(v) => !v && handleClose()}>
            <SheetContent side="bottom" className="rounded-t-[28px] border-t-0 p-0 pb-[env(safe-area-inset-bottom,0px)]">
                <div className="flex justify-center pt-3 pb-1">
                    <div className="bg-muted-foreground/20 h-[4px] w-[36px] rounded-full" />
                </div>
                <SheetTitle className="sr-only">Menu Aplikasi</SheetTitle>
                <div className="flex items-center gap-3 border-b border-border px-5 py-3.5">
                    <div className="bg-primary/10 text-primary flex size-[38px] shrink-0 items-center justify-center rounded-full text-[13px] font-bold">{initials}</div>
                    <div className="min-w-0">
                        <p className="truncate text-[14px] font-bold">{auth.user.name}</p>
                        <p className="text-muted-foreground truncate text-[12px]">{auth.user.email}</p>
                    </div>
                </div>
                <div className="flex flex-col gap-5 px-5 py-5">
                    {sections.map((section) => (
                        <div key={section.title} className="flex flex-col gap-3">
                            <span className="text-muted-foreground text-[11px] font-semibold uppercase tracking-widest">{section.title}</span>
                            <div className="grid grid-cols-4 gap-y-4">
                                {section.items.map((item) => <AppIcon key={item.label} item={item} onClose={handleClose} />)}
                            </div>
                        </div>
                    ))}
                </div>
                <div className="border-t border-border px-5 pb-5">
                    <button
                        type="button"
                        onClick={() => { router.post(route('logout')); handleClose(); }}
                        className="flex w-full items-center gap-3 rounded-2xl px-1 py-3 text-red-500 active:bg-red-50"
                    >
                        <LogOut className="size-[18px]" strokeWidth={2} />
                        <span className="text-[14px] font-semibold">Keluar</span>
                    </button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

