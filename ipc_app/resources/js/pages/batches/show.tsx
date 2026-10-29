import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { StageStepper, type StepperStage } from '@/components/ipc/stage-stepper';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { cn } from '@/lib/utils';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, ClipboardList, Printer } from 'lucide-react';

interface Batch {
    id: number;
    no_batch: string;
    bulk_code: string;
    current_stage: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string };
    master_line: { name: string; code: string } | null;
    creator: { name: string };
    startup_check: { completed_at: string | null } | null;
    filling_check: { completed_at: string | null } | null;
    packing_check: { completed_at: string | null } | null;
    finished_check: { completed_at: string | null } | null;
    micro_check: { completed_at: string | null } | null;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

interface MicroCheckWaiting {
    hasProgress: boolean;
    href: string | null;
}

export default function BatchesShow({
    batch,
    stages,
    microCheckWaiting,
}: {
    batch: Batch;
    stages: StepperStage[];
    microCheckWaiting: MicroCheckWaiting | null;
}) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const activeStage = stages.find((s) => s.status === 'active');
    const doneStages = stages.filter((s) => s.status === 'done');
    const completedAtByKey: Record<string, string | null> = {
        startup: batch.startup_check?.completed_at ?? null,
        filling: batch.filling_check?.completed_at ?? null,
        packing: batch.packing_check?.completed_at ?? null,
        finished: batch.finished_check?.completed_at ?? null,
        micro: batch.micro_check?.completed_at ?? null,
    };

    return (
        <IpcShell title="Detail Batch" backHref="/batches">
            <Head title={`Batch ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-6 p-5 md:p-6 lg:p-8">
                    {/* Info card */}
                    <div className="workflow-intro border-primary/15 bg-card flex flex-col gap-5 rounded-3xl border p-5 shadow-sm xl:flex-row xl:items-start xl:justify-between xl:gap-8 xl:p-7">
                        <div className="flex items-start justify-between gap-3 md:block">
                            <div>
                                <p className="text-primary mb-2 text-[10px] font-bold tracking-[0.18em] uppercase">Batch workspace</p>
                                <p className="text-[20px] font-bold tracking-tight md:text-[26px]">{batch.no_batch}</p>
                                <p className="text-muted-foreground mt-0.5 text-[13.5px] font-medium md:text-sm">
                                    {batch.master_product.product_name} &middot; {batch.master_product.fg_code} &middot;{' '}
                                    {batch.master_line?.name ?? '—'}
                                </p>
                            </div>
                            <span
                                className="shrink-0 rounded-full px-[11px] py-[5px] text-[11.5px] font-bold whitespace-nowrap"
                                style={stageBadgeStyle(batch.current_stage)}
                            >
                                {stageLabel(batch.current_stage)}
                            </span>
                        </div>

                        <div className="bg-border-soft h-px md:hidden" />

                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:flex xl:gap-6">
                            <div>
                                <p className="text-muted-foreground/70 text-[11.5px] font-semibold tracking-wide uppercase">Line</p>
                                <p className="mt-0.5 text-[13.5px] font-semibold">{batch.master_line?.name ?? '—'}</p>
                            </div>
                            <div className="md:text-right">
                                <p className="text-muted-foreground/70 text-[11.5px] font-semibold tracking-wide uppercase">Dibuat oleh</p>
                                <p className="mt-0.5 text-[13.5px] font-semibold">{batch.creator.name}</p>
                            </div>
                            <div className="md:text-right">
                                <p className="text-muted-foreground/70 text-[11.5px] font-semibold tracking-wide uppercase">Tanggal dibuat</p>
                                <p className="mt-0.5 text-[13.5px] font-semibold">{formatDate(batch.created_at)}</p>
                            </div>
                            <div className="md:hidden">
                                <p className="text-muted-foreground/70 text-[11.5px] font-semibold tracking-wide uppercase">Bulk code</p>
                                <p className="mt-0.5 text-[13.5px] font-semibold">{batch.bulk_code}</p>
                            </div>
                        </div>
                    </div>

                    {batch.current_stage === 'completed' && (
                        <div className="flex items-start gap-4 rounded-3xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
                            <CheckCircle2 className="size-8 shrink-0" />
                            <div>
                                <h2 className="text-lg font-bold">Seluruh proses batch selesai</h2>
                                <p className="mt-1 text-sm leading-relaxed">
                                    Hasil pemeriksaan dan dokumen batch dapat ditinjau kembali melalui tahapan di bawah.
                                </p>
                            </div>
                        </div>
                    )}
                    {/* Stepper */}
                    <div className="border-border-soft bg-card flex flex-col gap-4 rounded-3xl border p-5 shadow-sm lg:p-6">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <p className="text-[15.5px] font-bold">Tahapan proses</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {doneStages.length} dari {stages.length} tahapan selesai
                                </p>
                            </div>
                            <span className="bg-primary/10 text-primary rounded-full px-3 py-1 text-xs font-bold">
                                {stages.length ? Math.round((doneStages.length / stages.length) * 100) : 0}%
                            </span>
                        </div>
                        <div
                            className="bg-muted h-1.5 overflow-hidden rounded-full"
                            role="progressbar"
                            aria-label="Progres tahapan batch"
                            aria-valuemin={0}
                            aria-valuemax={stages.length}
                            aria-valuenow={doneStages.length}
                        >
                            <div
                                className="bg-primary h-full rounded-full transition-[width] duration-300"
                                style={{ width: `${stages.length ? (doneStages.length / stages.length) * 100 : 0}%` }}
                            />
                        </div>
                        <StageStepper stages={stages} />
                    </div>

                    {microCheckWaiting && (
                        <div className="flex flex-col gap-2 rounded-[22px] border border-amber-300 bg-amber-50 p-5 text-amber-900 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-xs font-bold tracking-wide uppercase">Menunggu Micro Check</p>
                                <p className="mt-1 text-[14px] font-semibold">
                                    Finished Good sudah selesai. Approval terkunci sampai Microbiologist menekan Selesaikan pada Micro Check
                                    {microCheckWaiting.hasProgress ? ' (progress sudah tersimpan, belum diselesaikan).' : ' (belum diisi).'}
                                </p>
                            </div>
                            {microCheckWaiting.href && (
                                <Link
                                    href={microCheckWaiting.href}
                                    className="bg-primary flex h-[46px] items-center gap-2 rounded-2xl px-5 text-sm font-bold whitespace-nowrap text-white"
                                >
                                    Isi Micro Check
                                    <ChevronRight className="size-4" strokeWidth={2.4} />
                                </Link>
                            )}
                        </div>
                    )}

                    {/* Active stage CTA — tablet only; mobile's stepper row is itself the tappable affordance */}
                    {activeStage?.href && (
                        <div className="border-primary/20 bg-primary/[0.05] flex flex-wrap items-center justify-between gap-4 rounded-3xl border p-6">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-wide uppercase">Sedang berjalan</p>
                                <p className="mt-1 text-[19px] font-bold">{activeStage.label}</p>
                                <p className="text-muted-foreground mt-0.5 text-[13px] font-medium">
                                    Lanjutkan pemeriksaan pada tahapan yang sedang aktif.
                                </p>
                            </div>
                            <Link
                                href={activeStage.href}
                                className="bg-primary flex h-[50px] items-center gap-2 rounded-2xl px-[22px] text-sm font-bold text-white"
                            >
                                Buka {activeStage.label}
                                <ChevronRight className="size-4" strokeWidth={2.4} />
                            </Link>
                        </div>
                    )}

                    {/* Completed summary */}
                    {doneStages.length > 0 && (
                        <div className="flex flex-col gap-2.5">
                            <p className="text-muted-foreground text-[14.5px] font-bold">Sudah selesai</p>
                            {doneStages.map((stage) => {
                                const isPrint = stage.key === 'print';
                                const body = (
                                    <>
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-green-100">
                                            {isPrint ? (
                                                <Printer className="size-4 text-green-700" strokeWidth={2.4} />
                                            ) : (
                                                <ClipboardList className="size-4 text-green-700" strokeWidth={2.4} />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold">{stage.label}</p>
                                            <p className="text-muted-foreground/70 mt-0.5 text-[12.5px] font-medium">
                                                {completedAtByKey[stage.key]
                                                    ? `Diselesaikan · ${formatDate(completedAtByKey[stage.key] as string)}`
                                                    : 'Selesai'}
                                            </p>
                                        </div>
                                        {stage.href && (
                                            <span className={cn('shrink-0 text-xs font-bold', isPrint ? 'text-primary' : 'text-muted-foreground')}>
                                                {isPrint ? 'Cetak' : 'Lihat'}
                                            </span>
                                        )}
                                        {stage.href && <ChevronRight className="text-muted-foreground/60 size-4 shrink-0" strokeWidth={2.4} />}
                                    </>
                                );

                                const className = 'border-border-soft bg-card flex items-center gap-4 rounded-[18px] border px-[18px] py-4';

                                if (stage.href) {
                                    return (
                                        <Link key={stage.key} href={stage.href} className={cn(className, 'transition-colors hover:bg-black/[0.02]')}>
                                            {body}
                                        </Link>
                                    );
                                }

                                return (
                                    <div key={stage.key} className={className}>
                                        {body}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </TwoPane>
        </IpcShell>
    );
}
