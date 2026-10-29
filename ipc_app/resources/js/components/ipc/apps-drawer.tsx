import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { useAppearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Archive,
    ArrowUpRight,
    Check,
    FileText,
    FlaskConical,
    Grid2X2,
    KeyRound,
    LogOut,
    MapPin,
    Monitor,
    Moon,
    Package,
    ScanText,
    Search,
    Sun,
    Trash2,
    UserRound,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface AppItem {
    label: string;
    description: string;
    icon: React.ElementType;
    href: string;
}

function AppCard({ item, active, onClose }: { item: AppItem; active: boolean; onClose: () => void }) {
    const Icon = item.icon;
    return (
        <Link
            href={item.href}
            onClick={onClose}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'group focus-visible:ring-primary flex min-w-0 items-center gap-3 rounded-2xl border p-3.5 transition duration-200 outline-none focus-visible:ring-2 active:scale-[0.98] motion-reduce:transition-none',
                active ? 'border-primary/30 bg-primary/5' : 'border-border-soft bg-card hover:border-primary/30 hover:bg-primary/5',
            )}
        >
            <span
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors',
                    active ? 'bg-primary text-white' : 'bg-primary/8 text-primary group-hover:bg-primary/15',
                )}
            >
                <Icon className="size-5" strokeWidth={1.8} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-[13px] font-semibold">{item.label}</span>
                <span className="text-muted-foreground mt-0.5 block text-[11px] leading-relaxed">{item.description}</span>
            </span>
            {active ? (
                <Check className="text-primary size-3.5 shrink-0" />
            ) : (
                <ArrowUpRight className="text-muted-foreground/50 group-hover:text-primary size-3.5 shrink-0 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none" />
            )}
        </Link>
    );
}

export function AppsDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { auth, canManageMaster, canUseVisionTest } = usePage<SharedData>().props;
    const { url } = usePage();
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();
    const [query, setQuery] = useState('');
    useEffect(() => {
        if (!open) setQuery('');
    }, [open]);
    const handleClose = () => {
        cleanup();
        onClose();
    };
    const initials = auth.user.name
        .split(' ')
        .map((word: string) => word[0])
        .slice(0, 2)
        .join('');
    const sections: { title: string; description: string; items: AppItem[] }[] = [
        ...(canManageMaster
            ? [
                  {
                      title: 'Data & administrasi',
                      description: 'Kelola data pendukung pemeriksaan.',
                      items: [
                          { label: 'Arsip', description: 'Riwayat batch selesai', icon: Archive, href: route('archive.index') },
                          { label: 'Tempat Sampah', description: 'Pulihkan data terhapus', icon: Trash2, href: route('recycle-bin.index') },
                          { label: 'Master Line', description: 'Line produksi', icon: MapPin, href: route('master-lines.index') },
                          { label: 'Master Produk', description: 'Katalog produk & kode FG', icon: Package, href: route('master-products.index') },
                          { label: 'Master Test Type', description: 'Jenis pengujian', icon: FlaskConical, href: route('master-test-types.index') },
                          { label: 'Dokumen Laporan', description: 'Referensi dokumen', icon: FileText, href: route('report-documents.index') },
                          { label: 'Manajemen User', description: 'Akun & hak akses', icon: Users, href: route('users.index') },
                      ],
                  },
              ]
            : []),
        ...(canUseVisionTest
            ? [
                  {
                      title: 'Alat pemeriksaan',
                      description: 'Bantuan untuk pekerjaan sehari-hari.',
                      items: [{ label: 'OCR Test Product', description: 'Baca teks pada produk', icon: ScanText, href: route('vision-test.index') }],
                  },
              ]
            : []),
        {
            title: 'Akun',
            description: 'Pengaturan akun pribadi Anda.',
            items: [
                { label: 'Profil', description: 'Informasi akun', icon: UserRound, href: route('profile.edit') },
                { label: 'Password', description: 'Keamanan akun', icon: KeyRound, href: route('password.edit') },
            ],
        },
    ];
    const needle = query.trim().toLocaleLowerCase('id-ID');
    const filtered = sections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => `${section.title} ${item.label} ${item.description}`.toLocaleLowerCase('id-ID').includes(needle)),
        }))
        .filter((section) => section.items.length);
    const currentPath = new URL(url, window.location.origin).pathname;

    return (
        <Sheet open={open} onOpenChange={(value) => !value && handleClose()}>
            <SheetContent
                side="bottom"
                overlayClassName="bg-black/30 backdrop-blur-[2px] motion-reduce:animate-none"
                className="bg-background border-border-soft flex max-h-[90dvh] flex-col gap-0 overflow-hidden rounded-t-3xl border p-0 pb-[env(safe-area-inset-bottom,0px)] md:inset-y-4 md:right-4 md:left-auto md:max-h-none md:w-[620px] md:max-w-[calc(100vw-108px)] md:rounded-3xl"
            >
                <div className="border-border-soft bg-card shrink-0 border-b px-5 pt-6 pb-4 md:px-6">
                    <div className="mb-4 flex items-center gap-3 pr-8">
                        <span className="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                            <Grid2X2 className="size-5" />
                        </span>
                        <div>
                            <SheetTitle className="text-lg font-bold tracking-tight">Menu aplikasi</SheetTitle>
                            <SheetDescription className="mt-0.5 text-xs">Semua kebutuhan kerja, dalam satu tempat.</SheetDescription>
                        </div>
                    </div>
                    <div className="relative">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                        <Input
                            aria-label="Cari menu aplikasi"
                            placeholder="Cari menu atau fitur..."
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            className="bg-background border-border-soft h-11 rounded-xl pr-10 pl-10 text-[13px]"
                        />
                        {query && (
                            <button
                                type="button"
                                aria-label="Bersihkan pencarian menu"
                                onClick={() => setQuery('')}
                                className="text-muted-foreground hover:text-foreground focus-visible:ring-primary absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg focus-visible:ring-2"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>
                </div>
                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 md:px-6">
                    <div className="flex flex-col gap-6">
                        {filtered.map((section) => (
                            <section key={section.title}>
                                <div className="mb-3">
                                    <h3 className="text-[13px] font-bold">{section.title}</h3>
                                    <p className="text-muted-foreground mt-0.5 text-[11.5px]">{section.description}</p>
                                </div>
                                <div className="grid grid-cols-1 gap-2.5 min-[420px]:grid-cols-2">
                                    {section.items.map((item) => (
                                        <AppCard
                                            key={item.href}
                                            item={item}
                                            active={currentPath === new URL(item.href, window.location.origin).pathname}
                                            onClose={handleClose}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                        {filtered.length === 0 && (
                            <div className="text-muted-foreground flex flex-col items-center gap-2 py-10 text-center">
                                <Search className="mb-1 size-7 opacity-50" />
                                <p className="text-sm font-semibold">Menu tidak ditemukan</p>
                                <p className="text-xs">Coba nama menu atau kata kunci lain.</p>
                            </div>
                        )}
                    </div>
                </div>
                <div className="border-border-soft bg-card shrink-0 border-t px-5 py-4 md:px-6">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <span className="text-muted-foreground text-xs font-medium">Tampilan</span>
                        <div className="bg-muted flex rounded-xl p-1" role="group" aria-label="Tema aplikasi">
                            {(
                                [
                                    { value: 'light', label: 'Terang', icon: Sun },
                                    { value: 'dark', label: 'Gelap', icon: Moon },
                                    { value: 'system', label: 'Sistem', icon: Monitor },
                                ] as const
                            ).map((mode) => (
                                <button
                                    key={mode.value}
                                    type="button"
                                    aria-pressed={appearance === mode.value}
                                    onClick={() => updateAppearance(mode.value)}
                                    className={cn(
                                        'focus-visible:ring-primary flex items-center gap-1.5 rounded-lg px-2.5 py-2 text-[11px] font-semibold transition-colors focus-visible:ring-2',
                                        appearance === mode.value ? 'bg-card text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <mode.icon className="size-3.5" />
                                    {mode.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="border-border-soft flex items-center gap-3 border-t pt-4">
                        <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                            {initials}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-[13px] font-bold">{auth.user.name}</p>
                            <p className="text-muted-foreground truncate text-[11px]">{auth.user.email}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                router.post(route('logout'));
                                handleClose();
                            }}
                            className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-primary flex shrink-0 items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition-colors focus-visible:ring-2"
                        >
                            <LogOut className="size-4" />
                            Keluar
                        </button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
