import AppearanceToggleTab from '@/components/appearance-tabs';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background relative flex min-h-svh flex-col items-center justify-center gap-8 px-5 py-10 sm:px-6">
            <div className="absolute top-4 right-4">
                <AppearanceToggleTab className="scale-90" />
            </div>

            <div className="flex w-full max-w-[380px] flex-col gap-7">
                <div className="flex flex-col items-center gap-3 text-center">
                    <Link href={route('home')} className="bg-primary flex size-14 items-center justify-center rounded-2xl shadow-lg shadow-primary/20">
                        <svg width="27" height="27" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M9 12l2 2 4-4" />
                            <circle cx="12" cy="12" r="9" />
                        </svg>
                    </Link>
                    <div className="flex flex-col items-center gap-0.5">
                        <span className="text-[19px] font-bold tracking-tight">IPC</span>
                        <span className="text-muted-foreground text-[12.5px] font-medium">In Process Control</span>
                    </div>
                </div>

                <div className="border-border-soft bg-card flex flex-col gap-6 rounded-[28px] border p-6 shadow-sm sm:p-7">
                    <div className="flex flex-col gap-1 text-center">
                        <h1 className="text-[19px] font-bold tracking-tight">{title}</h1>
                        {description && <p className="text-muted-foreground text-[13px] font-medium">{description}</p>}
                    </div>
                    {children}
                </div>

                <p className="text-muted-foreground/70 text-center text-[11.5px] font-medium">
                    &copy; {new Date().getFullYear()} Cosmax Indonesia
                </p>
            </div>
        </div>
    );
}
