import { Badge } from '@/components/ui/badge';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, router } from '@inertiajs/react';
import { MapPin, User } from 'lucide-react';
import { useEffect } from 'react';

interface LineBatch {
    id: number;
    no_batch: string;
    product_name: string | null;
    fg_code: string | null;
    stage: string;
    stage_label: string;
    step: number | null;
    total_steps: number;
    pic: string | null;
    last_activity_at: string | null;
}

interface Line {
    id: number;
    code: string;
    name: string;
    category: string;
    area: string;
    batches: LineBatch[];
}

function idleMinutes(iso: string | null): number | null {
    if (!iso) return null;
    return Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
}

function formatIdle(min: number | null): string {
    if (min === null) return '-';
    if (min < 60) return `${min} menit lalu`;
    if (min < 1440) return `${Math.floor(min / 60)} jam ${min % 60} menit lalu`;
    return `${Math.floor(min / 1440)} hari lalu`;
}

export default function LineMonitor({ lines }: { lines: Line[] }) {
    // Keep the board fresh without a manual reload.
    useEffect(() => {
        const timer = setInterval(() => router.reload({ only: ['lines'] }), 30000);
        return () => clearInterval(timer);
    }, []);

    const running = lines.filter((l) => l.batches.length > 0).length;

    return (
        <IpcShell title="Monitor Line" subtitle={`${running} dari ${lines.length} line berjalan`} backHref="/dashboard">
            <Head title="Monitor Line" />
            <div className="grid flex-1 content-start gap-4 overflow-y-auto p-5 md:grid-cols-2 md:p-6 xl:grid-cols-3">
                {lines.map((line) => (
                    <div key={line.id} className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-4">
                        <div>
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-[16px] font-bold tracking-tight">{line.name}</p>
                                <Badge variant={line.batches.length ? 'default' : 'secondary'} className="text-[10.5px]">
                                    {line.batches.length ? 'Berjalan' : 'Idle'}
                                </Badge>
                            </div>
                            <div className="text-muted-foreground/70 mt-1 flex items-center gap-1.5 text-[12.5px] font-medium">
                                <MapPin className="size-3.5" strokeWidth={2} />
                                {line.code} &middot; {line.category} &middot; {line.area}
                            </div>
                        </div>

                        {line.batches.map((b) => {
                            const idle = idleMinutes(b.last_activity_at);
                            const stale = idle !== null && idle > 120;
                            return (
                                <a
                                    key={b.id}
                                    href={route('batches.show', b.id)}
                                    className="border-border-soft hover:bg-muted/50 rounded-2xl border p-3 transition-colors"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-[15px] font-bold">{b.no_batch}</p>
                                        <Badge variant="outline" className="text-[10.5px]">
                                            {b.stage_label}
                                            {b.step ? ` · ${b.step}/${b.total_steps}` : ''}
                                        </Badge>
                                    </div>
                                    <p className="mt-0.5 truncate text-[13px] font-semibold">{b.product_name ?? '-'}</p>
                                    <p className="text-muted-foreground text-[12px]">FG {b.fg_code ?? '-'}</p>
                                    <div className="mt-2 flex items-center justify-between gap-2 text-[12.5px]">
                                        <span className="flex items-center gap-1.5 font-semibold">
                                            <User className="size-3.5" />
                                            {b.pic ?? '-'}
                                        </span>
                                        <span className={stale ? 'text-destructive font-semibold' : 'text-muted-foreground'}>{formatIdle(idle)}</span>
                                    </div>
                                </a>
                            );
                        })}

                        {line.batches.length === 0 && <p className="text-muted-foreground text-[13px]">Tidak ada batch aktif.</p>}
                    </div>
                ))}

                {lines.length === 0 && <p className="text-muted-foreground col-span-full py-12 text-center text-sm">Belum ada line aktif.</p>}
            </div>
        </IpcShell>
    );
}
