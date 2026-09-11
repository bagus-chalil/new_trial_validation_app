import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import cosmaxLogo from '@/assets/cosmax-idn-logo.jpg';
import AppearanceToggleTab from '@/components/appearance-tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
        <div className="flex min-h-svh flex-col bg-background lg:flex-row">
            <div className="relative hidden overflow-hidden lg:flex lg:w-[45%] lg:flex-col lg:justify-between lg:p-12 xl:w-2/5">
                <div className="absolute inset-0 bg-gradient-to-br from-brand via-[#c81a1f] to-[#7a1014]" />
                <div className="absolute -top-24 -right-24 size-72 rounded-full bg-white/10 blur-3xl" />
                <div className="absolute bottom-0 left-0 size-80 -translate-x-1/3 translate-y-1/3 rounded-full bg-black/10 blur-3xl" />
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle, white 1px, transparent 1px)',
                        backgroundSize: '22px 22px',
                    }}
                />

                <div className="relative z-10 flex flex-col gap-10">
                    <Link
                        href={home()}
                        className="inline-flex w-fit items-center rounded-2xl bg-white px-6 py-4 shadow-lg"
                    >
                        <img
                            src={cosmaxLogo}
                            alt="COSMAX Indonesia"
                            className="h-14 w-auto object-contain"
                        />
                    </Link>

                    <div className="space-y-4 text-white">
                        <p className="text-sm font-semibold tracking-wide text-white/70 uppercase">
                            QAC Super Apps
                        </p>
                        <h2 className="text-3xl leading-tight font-bold text-balance">
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
                        <Card className="border-border/60 shadow-xl shadow-black/5">
                            <CardHeader className="items-center text-center">
                                <CardTitle className="text-xl">
                                    {title}
                                </CardTitle>
                                <CardDescription>{description}</CardDescription>
                            </CardHeader>
                            <CardContent>{children}</CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
}
