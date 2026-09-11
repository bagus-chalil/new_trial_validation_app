import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import cosmaxLogo from '@/assets/cosmax-idn-logo.jpg';
import cosmaxVertical from '@/assets/cosmax-vertical.png';
import AppearanceToggleTab from '@/components/appearance-tabs';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights = [
    'Form trial, validasi parameter & weighing',
    'Review berjenjang per departemen',
    'Approval, laporan, dan riwayat aktivitas',
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative flex min-h-svh flex-col bg-background lg:flex-row">
            <div className="relative hidden overflow-hidden lg:flex lg:w-[45%] lg:flex-col lg:justify-between lg:p-12 xl:w-2/5">
                <div className="absolute inset-0 bg-gradient-to-br from-brand via-[#c81a1f] to-[#7a1014]" />
                <div className="absolute -top-24 -right-24 size-72 rounded-full bg-white/10 blur-3xl" />
                <div className="absolute bottom-0 left-0 size-80 -translate-x-1/3 translate-y-1/3 rounded-full bg-black/10 blur-3xl" />
                <div className="absolute top-1/3 right-10 size-20 rounded-full border-2 border-white/25" />
                <div className="absolute top-[42%] right-20 size-8 rounded-full border-2 border-white/25" />
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle, white 1px, transparent 1px)',
                        backgroundSize: '22px 22px',
                    }}
                />

                <div className="relative z-10 flex flex-col items-center gap-8 text-center">
                    <Link
                        href={home()}
                        className="inline-flex w-fit items-center rounded-[28px] bg-white px-9 py-7 shadow-2xl shadow-black/20"
                    >
                        <img
                            src={cosmaxVertical}
                            alt="COSMAX"
                            className="h-56 w-auto object-contain"
                        />
                    </Link>

                    <div className="space-y-3 text-white">
                        <p className="text-sm font-semibold tracking-wide text-white/70 uppercase">
                            QAC Super Apps
                        </p>
                        <h2 className="text-2xl leading-tight font-bold text-balance">
                            {name}
                        </h2>
                        <p className="max-w-sm text-balance text-white/80">
                            Pengelolaan trial produksi dari pengajuan, review
                            lintas departemen, hingga approval dan pelaporan —
                            dalam satu alur kerja.
                        </p>
                    </div>
                </div>

                <ul className="relative z-10 flex flex-col gap-3">
                    {highlights.map((item) => (
                        <li
                            key={item}
                            className="flex items-start gap-3 text-sm text-white/90"
                        >
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                            <span>{item}</span>
                        </li>
                    ))}
                </ul>

                <p className="relative z-10 text-xs text-white/60">
                    &copy; {new Date().getFullYear()} Cosmax Indonesia. All
                    rights reserved.
                </p>
            </div>

            <svg
                className="pointer-events-none absolute inset-y-0 left-[45%] z-10 hidden h-full w-28 -translate-x-1/2 lg:block xl:left-2/5"
                viewBox="0 0 120 800"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <path
                    d="M120,0 C70,40 90,90 55,130 C10,175 100,230 60,290 C15,350 95,390 65,440 C25,495 100,540 60,600 C15,660 95,710 60,760 C35,795 90,800 120,800 Z"
                    style={{ fill: 'var(--background)' }}
                />
            </svg>

            <div className="flex flex-1 flex-col">
                <div className="flex items-center justify-between p-6 lg:justify-end lg:p-8">
                    <Link
                        href={home()}
                        className="inline-flex items-center rounded-lg bg-white px-3 py-2 shadow-sm ring-1 ring-border lg:hidden"
                    >
                        <img
                            src={cosmaxLogo}
                            alt="COSMAX Indonesia"
                            className="h-8 w-auto object-contain"
                        />
                    </Link>
                    <AppearanceToggleTab className="scale-90" />
                </div>

                <div className="flex flex-1 items-center justify-center px-6 pb-12">
                    <div className="w-full max-w-sm animate-in duration-500 fade-in slide-in-from-bottom-4">
                        <div className="mb-8 space-y-2 text-center lg:text-left">
                            <div className="mx-auto mb-6 h-1.5 w-12 rounded-full bg-gradient-to-r from-brand to-[#7a1014] lg:mx-0" />
                            <h1 className="text-2xl font-bold text-balance">
                                {title}
                            </h1>
                            <p className="text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
