import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Check, Lock } from 'lucide-react';

export interface StepperStage {
    key: string;
    label: string;
    status: 'done' | 'active' | 'locked';
    href: string | null;
    available: boolean;
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
                {stage.available ? 'Sedang berjalan · ketuk untuk isi' : 'Belum tersedia'}
            </span>
        );
    return <span className="text-muted-foreground/70 mt-0.5 text-xs font-medium">Belum dimulai</span>;
}

export function StageStepper({ stages }: { stages: StepperStage[] }) {
    return (
        <>
            {/* Mobile: vertical */}
            <div className="flex flex-col md:hidden">
                {stages.map((stage, i) => {
                    const row = (
                        <>
                            <div className="flex flex-col items-center">
                                <StageCircle stage={stage} />
                                {i < stages.length - 1 && (
                                    <div className={cn('min-h-7 w-0.5 flex-1', stage.status === 'done' ? 'bg-green-600' : 'bg-border')} />
                                )}
                            </div>
                            <div className={cn('flex-1', i < stages.length - 1 && 'pb-[22px]')}>
                                <p
                                    className={cn(
                                        'text-[14.5px] font-bold',
                                        stage.status === 'active' && 'text-primary',
                                        stage.status === 'locked' && 'text-muted-foreground/70',
                                    )}
                                >
                                    {stage.label}
                                </p>
                                <StageMeta stage={stage} />
                            </div>
                        </>
                    );

                    if (stage.href) {
                        return (
                            <Link
                                key={stage.key}
                                href={stage.href}
                                className={cn('flex gap-3.5', stage.status === 'active' && 'bg-primary/[0.05] -mx-3 rounded-2xl px-3 py-2.5')}
                            >
                                {row}
                            </Link>
                        );
                    }

                    return (
                        <div key={stage.key} className="flex gap-3.5">
                            {row}
                        </div>
                    );
                })}
            </div>

            {/* Tablet+: horizontal */}
            <div className="hidden items-start md:flex">
                {stages.map((stage, i) => (
                    <div key={stage.key} className="flex flex-1 flex-col items-center gap-2">
                        <div className="flex w-full items-center">
                            <div
                                className={cn(
                                    'mt-[18px] h-0.5 flex-1',
                                    i === 0 ? 'bg-transparent' : stage.status === 'done' ? 'bg-green-600' : 'bg-border',
                                )}
                            />
                            <StageCircle stage={stage} size={36} />
                            <div
                                className={cn(
                                    'mt-[18px] h-0.5 flex-1',
                                    i === stages.length - 1 ? 'bg-transparent' : stage.status === 'done' ? 'bg-green-600' : 'bg-border',
                                )}
                            />
                        </div>
                        <span
                            className={cn(
                                'text-xs font-bold',
                                stage.status === 'done' && 'text-green-600',
                                stage.status === 'active' && 'text-primary',
                                stage.status === 'locked' && 'text-muted-foreground/70',
                            )}
                        >
                            {stage.label}
                        </span>
                    </div>
                ))}
            </div>
        </>
    );
}
