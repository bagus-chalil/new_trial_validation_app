import { BatchNavList } from '@/components/ipc/batch-nav-list';
import { StageStepper, type StepperStage } from '@/components/ipc/stage-stepper';
import { TwoPane } from '@/components/ipc/two-pane';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { type RecentBatch, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight, ClipboardList } from 'lucide-react';

interface Batch {
    id: number;
    no_batch: string;
    current_stage: string;
    created_at: string;
    master_product: { product_name: string; fg_code: string; bulk_code: string };
    master_line: { name: string; code: string };
    creator: { name: string };
    startup_check: { completed_at: string | null } | null;
    filling_check: { completed_at: string | null } | null;
    packing_check: { completed_at: string | null } | null;
    finished_check: { completed_at: string | null } | null;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function BatchesShow({ batch, stages }: { batch: Batch; stages: StepperStage[] }) {
    const { props } = usePage<SharedData>();
    const recentBatches = (props.recentBatches ?? []) as RecentBatch[];

    const activeStage = stages.find((s) => s.status === 'active');
    const doneStages = stages.filter((s) => s.status === 'done');
    const completedAtByKey: Record<string, string | null> = {
        startup: batch.startup_check?.completed_at ?? null,
        filling: batch.filling_check?.completed_at ?? null,
        packing: batch.packing_check?.completed_at ?? null,
        finished: batch.finished_check?.completed_at ?? null,
    };

    return (
        <IpcShell title="Detail Batch" backHref="/batches">
            <Head title={`Batch ${batch.no_batch}`} />
            <TwoPane list={<BatchNavList batches={recentBatches} activeId={batch.id} />}>
                <div className="flex flex-1 flex-col gap-[22px] p-5 md:p-8">
                    {/* Info card */}
                    <div className="border-border-soft bg-card flex flex-col gap-3.5 rounded-[22px] border p-[18px] md:flex-row md:items-start md:justify-between md:gap-8 md:border-none md:bg-transparent md:p-0">
                        <div className="flex items-start justify-between gap-3 md:block">
                            <div>
                                <p className="text-[20px] font-bold tracking-tight md:text-[26px]">{batch.no_batch}</p>
                                <p className="text-muted-foreground mt-0.5 text-[13.5px] font-medium md:text-sm">
                                    {batch.master_product.product_name} &middot; {batch.master_product.fg_code} &middot; {batch.master_line.name}
                                </p>
                            </div>
                            <span
                                className="shrink-0 rounded-full px-[11px] py-[5px] text-[11.5px] font-bold whitespace-nowrap md:hidden"
                                style={stageBadgeStyle(batch.current_stage)}
                            >
                                {stageLabel(batch.current_stage)}
                            </span>
                        </div>

                        <div className="bg-border-soft h-px md:hidden" />

                        <div className="grid grid-cols-2 gap-3.5 md:flex md:gap-6">
                            <div>
                                <p className="text-muted-foreground/70 text-[11.5px] font-semibold tracking-wide uppercase">Line</p>
                                <p className="mt-0.5 text-[13.5px] font-semibold">{batch.master_line.name}</p>
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
                                <p className="mt-0.5 text-[13.5px] font-semibold">{batch.master_product.bulk_code}</p>
                            </div>
                        </div>
                    </div>

                    {/* Stepper */}
                    <div className="flex flex-col gap-0.5">
                        <p className="mb-2.5 text-[15.5px] font-bold md:hidden">Tahapan proses</p>
                        <StageStepper stages={stages} />
                    </div>

                    {/* Active stage CTA — tablet only; mobile's stepper row is itself the tappable affordance */}
                    {activeStage?.href && (
                        <div className="border-primary/20 bg-primary/[0.05] hidden items-center justify-between rounded-[22px] border p-6 md:flex">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-wide uppercase">Sedang berjalan</p>
                                <p className="mt-1 text-[19px] font-bold">{activeStage.label}</p>
                                <p className="text-muted-foreground mt-0.5 text-[13px] font-medium">Ketuk untuk mengisi tahap ini</p>
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
                            {doneStages.map((stage) => (
                                <div
                                    key={stage.key}
                                    className="border-border-soft bg-card flex items-center gap-4 rounded-[18px] border px-[18px] py-4"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-green-100">
                                        <ClipboardList className="size-4 text-green-700" strokeWidth={2.4} />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold">{stage.label}</p>
                                        <p className="text-muted-foreground/70 mt-0.5 text-[12.5px] font-medium">
                                            {completedAtByKey[stage.key]
                                                ? `Diselesaikan · ${formatDate(completedAtByKey[stage.key] as string)}`
                                                : 'Selesai'}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </TwoPane>
        </IpcShell>
    );
}
