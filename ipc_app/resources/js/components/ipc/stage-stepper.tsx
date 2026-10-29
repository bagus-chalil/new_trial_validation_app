import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Lock } from 'lucide-react';

export interface StepperStage {
    key: string;
    label: string;
    status: 'done' | 'active' | 'locked';
    href: string | null;
    available: boolean;
    description?: string;
    /** TH Progress "n/max" for the repeatable round stages (filling/packing/finished), once saved. */
    progress?: string | null;
}

function StageCircle({ stage, size = 32 }: { stage: StepperStage; size?: number }) {
    return (
        <div
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full',
                stage.status === 'done' && 'bg-green-600',
                stage.status === 'active' && 'bg-primary ring-primary/[0.13] ring-4',
                stage.status === 'locked' && 'border-border bg-background border-[1.5px]',
            )}
            style={{ width: size, height: size }}
        >
            {stage.status === 'done' && <Check className="size-4 text-white" strokeWidth={2.6} />}
            {stage.status === 'active' && <div className="size-2.5 rounded-full bg-white" />}
            {stage.status === 'locked' && <Lock className="text-muted-foreground/70 size-3.5" strokeWidth={2.2} />}
        </div>
    );
}

function StageMeta({ stage }: { stage: StepperStage }) {
    if (stage.status === 'done') return <span className="text-muted-foreground mt-0.5 text-xs font-medium">Selesai</span>;
    if (stage.status === 'active')
        return (
            <span className="text-muted-foreground mt-0.5 text-xs font-medium">
                {stage.description ??
                    (!stage.available
                        ? 'Belum tersedia'
                        : stage.progress
                          ? `TH Progress ${stage.progress} · ketuk untuk isi`
                          : 'Sedang berjalan · ketuk untuk isi')}
            </span>
        );
    return <span className="text-muted-foreground/70 mt-0.5 text-xs font-medium">Belum dimulai</span>;
}

export function StageStepper({ stages }: { stages: StepperStage[] }) {
    return (
        <ol className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" aria-label="Tahapan proses batch">
            {stages.map((stage, index) => {
                const content = (
                    <>
                        <div className="flex items-center justify-between gap-3">
                            <StageCircle stage={stage} size={34} />
                            <span className="text-muted-foreground/60 text-[10px] font-bold tracking-widest uppercase">
                                Tahap {String(index + 1).padStart(2, '0')}
                            </span>
                        </div>
                        <div className="mt-4">
                            <p
                                className={cn(
                                    'text-sm font-bold',
                                    stage.status === 'active' && 'text-primary',
                                    stage.status === 'done' && 'text-green-700 dark:text-green-400',
                                )}
                            >
                                {stage.label}
                            </p>
                            <div className="mt-1 leading-relaxed">
                                <StageMeta stage={stage} />
                            </div>
                        </div>
                        {stage.href && (
                            <span className="text-primary mt-4 inline-flex items-center gap-2 text-xs font-bold">
                                {stage.status === 'done' ? 'Lihat hasil' : 'Buka pemeriksaan'}
                                <ArrowRight aria-hidden="true" className="size-3.5" />
                            </span>
                        )}
                    </>
                );
                const style = cn(
                    'block h-full rounded-2xl border p-4 transition-colors',
                    stage.status === 'active'
                        ? 'border-primary/30 bg-primary/[0.04] shadow-sm'
                        : stage.status === 'done'
                          ? 'border-green-200 bg-green-50/40 dark:border-green-900 dark:bg-green-950/10'
                          : 'border-border-soft bg-muted/30 text-muted-foreground',
                    stage.href && 'hover:border-primary/50 hover:bg-primary/[0.06] focus-visible:outline-primary focus-visible:outline-2',
                );
                return (
                    <li key={stage.key} aria-current={stage.status === 'active' ? 'step' : undefined}>
                        {stage.href ? (
                            <Link href={stage.href} className={style}>
                                {content}
                            </Link>
                        ) : (
                            <div className={style}>{content}</div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
