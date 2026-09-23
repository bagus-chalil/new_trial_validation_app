import { useInitials } from '@/hooks/use-initials';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, CalendarCheck2, CheckCircle2, ChevronRight, ClipboardList, Layers, Stamp, Trash2, type LucideIcon } from 'lucide-react';

interface DashboardStats {
    activeBatches: number;
    needsActionCount: number;
    pendingApprovalBatches: number;
    completedToday: number;
}

interface StageBreakdownItem {
    stage: string;
    count: number;
}

interface NeedsActionItem {
    id: number;
    no_batch: string;
    product_name: string | null;
    stage: string;
    reason: string;
    href: string;
}

interface DashboardProps {
    stats: DashboardStats;
    stageBreakdown: StageBreakdownItem[];
    needsAction: NeedsActionItem[];
}

function greeting(): string {
    const hour = new Date().getHours();
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 19) return 'Selamat sore';
    return 'Selamat malam';
}

const STAT_CHIP_CLASSES: Record<'zinc' | 'amber' | 'blue' | 'emerald', string> = {
    zinc: 'bg-zinc-100 text-zinc-500',
    amber: 'bg-amber-100 text-amber-600',
    blue: 'bg-blue-100 text-blue-600',
    emerald: 'bg-emerald-100 text-emerald-600',
};

function StatCard({
    icon: Icon,
    value,
    label,
    accent = 'zinc',
}: {
    icon: LucideIcon;
    value: number;
    label: string;
    accent?: keyof typeof STAT_CHIP_CLASSES;
}) {
    return (
        <div className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-4">
            <div className={`flex size-9 items-center justify-center rounded-xl ${STAT_CHIP_CLASSES[accent]}`}>
                <Icon className="size-[18px]" strokeWidth={2.2} />
            </div>
            <div className="flex flex-col gap-0.5">
                <span className="text-[26px] font-bold tracking-tight">{value}</span>
                <span className="text-muted-foreground text-[12.5px] font-medium">{label}</span>
            </div>
        </div>
    );
}

export default function Dashboard({ stats, stageBreakdown, needsAction }: DashboardProps) {
    const { props } = usePage<SharedData>();
    const getInitials = useInitials();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const maxStageCount = Math.max(1, ...stageBreakdown.map((item) => item.count));

    return (
        <IpcShell
            title="Home"
            header={
                <header className="flex shrink-0 items-center justify-between px-5 pt-[22px] pb-4 md:px-6">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-muted-foreground text-[13px] font-medium">{greeting()}</span>
                        <span className="text-[22px] font-bold tracking-tight">{props.auth.user.name}</span>
                    </div>
                    <div className="bg-primary/[0.1] text-primary flex size-11 items-center justify-center rounded-full text-[15px] font-bold">
                        {getInitials(props.auth.user.name)}
                    </div>
                </header>
            }
        >
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-[22px] overflow-y-auto px-5 pt-1 pb-6 md:px-6">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard icon={Layers} value={stats.activeBatches} label="Batch aktif" />
                    <StatCard icon={AlertTriangle} value={stats.needsActionCount} label="Perlu tindakan Anda" accent="amber" />
                    <StatCard icon={Stamp} value={stats.pendingApprovalBatches} label="Menunggu approval" accent="blue" />
                    <StatCard icon={CalendarCheck2} value={stats.completedToday} label="Selesai hari ini" accent="emerald" />
                </div>

                {props.canManageMaster && (
                    <Link href="/masters/recycle-bin" className="border-border-soft bg-card flex items-center gap-3 rounded-[18px] border px-4 py-3">
                        <div className="flex size-[38px] shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-500">
                            <Trash2 className="size-[18px]" strokeWidth={2} />
                        </div>
                        <span className="flex-1 text-[14px] font-semibold">Tempat Sampah</span>
                        <ChevronRight className="text-muted-foreground/60 size-[18px] shrink-0" strokeWidth={2} />
                    </Link>
                )}

                <div className="flex flex-col gap-[22px] md:grid md:grid-cols-5 md:items-start md:gap-5">
                    <div className="flex flex-col gap-2.5 md:col-span-3">
                        <div className="flex items-center justify-between">
                            <span className="text-[15.5px] font-bold">Perlu tindakan Anda</span>
                            <Link href="/batches" className="text-primary text-[12.5px] font-semibold">
                                Lihat semua
                            </Link>
                        </div>

                        {needsAction.map((item) => (
                            <Link
                                key={`${item.stage}-${item.id}`}
                                href={item.href}
                                className="border-border-soft bg-card flex items-center gap-3 rounded-[18px] border px-4 py-3.5"
                            >
                                <div className="flex size-[38px] shrink-0 items-center justify-center rounded-xl" style={stageBadgeStyle(item.stage)}>
                                    <ClipboardList className="size-[18px]" strokeWidth={2} />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-[14.5px] font-semibold">
                                        {item.no_batch}
                                        {item.product_name && (
                                            <span className="text-muted-foreground font-medium"> &middot; {item.product_name}</span>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground text-[12.5px] font-medium">{item.reason}</p>
                                </div>
                                <ChevronRight className="text-muted-foreground/60 size-[18px] shrink-0" strokeWidth={2} />
                            </Link>
                        ))}

                        {needsAction.length === 0 && (
                            <div className="border-border text-muted-foreground flex flex-col items-center gap-2 rounded-[18px] border border-dashed py-6 text-center text-sm">
                                <CheckCircle2 className="size-5 text-emerald-500" strokeWidth={2} />
                                Tidak ada tindakan tertunda.
                            </div>
                        )}
                    </div>

                    <div className="border-border-soft bg-card flex flex-col gap-4 rounded-[20px] border p-4 md:col-span-2">
                        <div className="flex flex-col gap-0.5">
                            <span className="text-[15.5px] font-bold">Ringkasan Tahapan</span>
                            <span className="text-muted-foreground text-[12px] font-medium">
                                Sebaran {stageBreakdown.reduce((sum, item) => sum + item.count, 0)} batch per tahap
                            </span>
                        </div>
                        <div className="flex flex-col gap-3">
                            {stageBreakdown.map((item) => (
                                <Link
                                    key={item.stage}
                                    href={`/batches?stage=${item.stage}`}
                                    className="hover:bg-muted -mx-1 flex items-center gap-2.5 rounded-lg px-1 py-0.5"
                                >
                                    <span className="size-2 shrink-0 rounded-full" style={{ backgroundColor: stageBadgeStyle(item.stage).color }} />
                                    <span className="w-[84px] shrink-0 truncate text-[12.5px] font-semibold">{stageLabel(item.stage)}</span>
                                    <span className="bg-border-soft h-2 min-w-0 flex-1 overflow-hidden rounded-full">
                                        <span
                                            className="block h-full rounded-r transition-[width] duration-200"
                                            style={{
                                                width: `${(item.count / maxStageCount) * 100}%`,
                                                backgroundColor: stageBadgeStyle(item.stage).color,
                                            }}
                                        />
                                    </span>
                                    <span className="text-foreground w-6 shrink-0 text-right text-[13px] font-bold tabular-nums">{item.count}</span>
                                </Link>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="flex flex-col gap-2.5">
                    <span className="text-[15.5px] font-bold">Batch Terbaru</span>
                    <div className="grid grid-cols-1 md:grid-cols-2 md:gap-x-6">
                        {recentBatches.slice(0, 10).map((batch) => (
                            <Link
                                key={batch.id}
                                href={`/batches/${batch.id}`}
                                className="hover:bg-muted flex min-w-0 items-center gap-3 rounded-xl px-0.5 py-2.5"
                            >
                                <span className="size-2 shrink-0 rounded-full bg-green-600" />
                                <span className="text-muted-foreground min-w-0 flex-1 truncate text-[13.5px]">
                                    <span className="text-foreground font-semibold">{batch.no_batch}</span> &middot;{' '}
                                    {batch.master_product?.product_name ?? '—'}
                                </span>
                                <span
                                    className="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                    style={stageBadgeStyle(batch.current_stage)}
                                >
                                    {stageLabel(batch.current_stage)}
                                </span>
                            </Link>
                        ))}
                        {recentBatches.length === 0 && <p className="text-muted-foreground py-6 text-center text-sm">Belum ada batch.</p>}
                    </div>
                </div>
            </div>
        </IpcShell>
    );
}
