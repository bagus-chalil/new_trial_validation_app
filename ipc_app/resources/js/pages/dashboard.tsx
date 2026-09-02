import { useInitials } from '@/hooks/use-initials';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, ClipboardList } from 'lucide-react';

function greeting(): string {
    const hour = new Date().getHours();
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 19) return 'Selamat sore';
    return 'Selamat malam';
}

export default function Dashboard() {
    const { props } = usePage<SharedData>();
    const getInitials = useInitials();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const activeBatches = recentBatches.filter((b) => b.current_stage !== 'completed');
    const needsAction = recentBatches.filter((b) => b.current_stage === 'startup' || b.current_stage === 'filling');

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
                <div className="grid grid-cols-2 gap-3">
                    <div className="border-border-soft bg-card flex flex-col gap-1.5 rounded-[20px] border p-4">
                        <span className="text-[26px] font-bold tracking-tight">{activeBatches.length}</span>
                        <span className="text-muted-foreground text-[12.5px] font-medium">Batch aktif</span>
                    </div>
                    <div className="border-border-soft bg-card flex flex-col gap-1.5 rounded-[20px] border p-4">
                        <span className="text-[26px] font-bold tracking-tight text-amber-600">{needsAction.length}</span>
                        <span className="text-muted-foreground text-[12.5px] font-medium">Perlu tindakan Anda</span>
                    </div>
                </div>

                <div className="flex flex-col gap-2.5">
                    <div className="flex items-center justify-between">
                        <span className="text-[15.5px] font-bold">Perlu tindakan Anda</span>
                        <Link href="/batches" className="text-primary text-[12.5px] font-semibold">
                            Lihat semua
                        </Link>
                    </div>

                    {needsAction.slice(0, 5).map((batch) => (
                        <Link
                            key={batch.id}
                            href={`/batches/${batch.id}`}
                            className="border-border-soft bg-card flex items-center gap-3 rounded-[18px] border px-4 py-3.5"
                        >
                            <div
                                className="flex size-[38px] shrink-0 items-center justify-center rounded-xl"
                                style={stageBadgeStyle(batch.current_stage)}
                            >
                                <ClipboardList className="size-[18px]" strokeWidth={2} />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-[14.5px] font-semibold">{batch.no_batch}</p>
                                <p className="text-muted-foreground text-[12.5px] font-medium">{stageLabel(batch.current_stage)} belum diisi</p>
                            </div>
                            <ChevronRight className="text-muted-foreground/60 size-[18px] shrink-0" strokeWidth={2} />
                        </Link>
                    ))}

                    {needsAction.length === 0 && (
                        <div className="border-border text-muted-foreground rounded-[18px] border border-dashed py-6 text-center text-sm">
                            Tidak ada tindakan tertunda.
                        </div>
                    )}
                </div>

                <div className="flex flex-col gap-2.5">
                    <span className="text-[15.5px] font-bold">Batch Terbaru</span>
                    <div className="flex flex-col">
                        {recentBatches.slice(0, 8).map((batch) => (
                            <Link
                                key={batch.id}
                                href={`/batches/${batch.id}`}
                                className="hover:bg-muted flex items-center gap-3 rounded-xl px-0.5 py-2.5"
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
