import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/hooks/use-initials';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, ClipboardList, Search } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
    bulk_code: string;
}

interface MasterLine {
    id: number;
    code: string;
    name: string;
}

interface StageCheck {
    id: number;
    completed_at: string | null;
}

interface Batch {
    id: number;
    no_batch: string;
    current_stage: string;
    created_at: string;
    master_product: MasterProduct;
    master_line: MasterLine;
    creator: { name: string };
    startup_check: StageCheck | null;
    filling_check: StageCheck | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

function cardCta(batch: Batch): { label: string; primary: boolean } {
    if (batch.current_stage === 'startup')
        return { label: batch.startup_check?.completed_at ? 'Lihat' : 'Isi Startup Check', primary: !batch.startup_check?.completed_at };
    if (batch.current_stage === 'filling')
        return { label: batch.filling_check?.completed_at ? 'Lihat' : 'Isi Filling Check', primary: !batch.filling_check?.completed_at };
    return { label: 'Lihat', primary: false };
}

function cardStatusText(batch: Batch): string {
    if (batch.current_stage === 'startup') return batch.startup_check?.completed_at ? 'Selesai' : 'Belum diisi';
    if (batch.current_stage === 'filling') return batch.filling_check?.completed_at ? 'Selesai' : 'Sedang berjalan';
    return 'Menunggu diisi';
}

function BatchCard({ batch }: { batch: Batch }) {
    const cta = cardCta(batch);

    return (
        <Link
            href={`/batches/${batch.id}`}
            className="border-border-soft bg-card hover:border-border flex flex-col gap-3 rounded-[20px] border p-4 transition-colors"
        >
            <div className="flex items-start justify-between gap-2.5">
                <div className="min-w-0">
                    <p className="text-[16.5px] font-bold tracking-tight">{batch.no_batch}</p>
                    <p className="text-muted-foreground mt-0.5 truncate text-[13px] font-medium">
                        {batch.master_product.product_name} &middot; {batch.master_product.fg_code}
                    </p>
                </div>
                <span
                    className="shrink-0 rounded-full px-[11px] py-[5px] text-[11.5px] font-bold whitespace-nowrap"
                    style={stageBadgeStyle(batch.current_stage)}
                >
                    {stageLabel(batch.current_stage)}
                </span>
            </div>

            <div className="text-muted-foreground/70 flex items-center gap-1.5 text-[12.5px] font-medium">
                <ClipboardList className="size-3.5" strokeWidth={2} />
                {batch.master_line.name} &middot; dibuat oleh {batch.creator.name}
            </div>

            <div className="bg-border-soft h-px" />

            <div className="flex items-center justify-between">
                <span className="text-muted-foreground/70 text-xs font-medium">{cardStatusText(batch)}</span>
                <span
                    className={
                        cta.primary
                            ? 'bg-primary flex h-11 items-center gap-1 rounded-xl px-3.5 text-[13px] font-bold text-white'
                            : 'border-border bg-background text-foreground flex h-11 items-center rounded-xl border px-3.5 text-[13px] font-bold'
                    }
                >
                    {cta.label}
                    {cta.primary && <ChevronRight className="size-3.5" strokeWidth={2.4} />}
                </span>
            </div>
        </Link>
    );
}

function BatchListPane({
    batches,
    filters,
    stages,
    q,
    setQ,
    onSubmitSearch,
    onSetStage,
}: {
    batches: Paginated<Batch>;
    filters: { q?: string; stage?: string };
    stages: string[];
    q: string;
    setQ: (value: string) => void;
    onSubmitSearch: FormEventHandler;
    onSetStage: (stage?: string) => void;
}) {
    return (
        <div className="flex flex-1 flex-col gap-4 p-5 md:p-5">
            <form onSubmit={onSubmitSearch} className="relative">
                <Search className="text-muted-foreground/70 absolute top-1/2 left-4 size-[18px] -translate-y-1/2" strokeWidth={2} />
                <Input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Cari no batch / produk..."
                    className="border-border-soft bg-card h-12 rounded-2xl pl-11 text-sm"
                />
            </form>

            <div className="-mx-5 flex gap-2 overflow-x-auto px-5 pb-1 md:mx-0 md:flex-wrap md:px-0">
                <button
                    type="button"
                    onClick={() => onSetStage(undefined)}
                    className={
                        !filters.stage
                            ? 'bg-primary flex h-11 shrink-0 items-center rounded-full px-4 text-[13px] font-bold text-white'
                            : 'border-border-soft bg-card text-muted-foreground flex h-11 shrink-0 items-center rounded-full border px-4 text-[13px] font-bold'
                    }
                >
                    Semua
                </button>
                {stages.map((stage) => (
                    <button
                        key={stage}
                        type="button"
                        onClick={() => onSetStage(stage)}
                        className={
                            filters.stage === stage
                                ? 'bg-primary flex h-11 shrink-0 items-center rounded-full px-4 text-[13px] font-bold text-white'
                                : 'border-border-soft bg-card text-muted-foreground flex h-11 shrink-0 items-center rounded-full border px-4 text-[13px] font-bold'
                        }
                    >
                        {stageLabel(stage)}
                    </button>
                ))}
            </div>

            <div className="flex flex-col gap-3">
                {batches.data.map((batch) => (
                    <BatchCard key={batch.id} batch={batch} />
                ))}

                {batches.data.length === 0 && (
                    <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                        <ClipboardList className="text-muted-foreground/60 size-8" />
                        <p className="text-muted-foreground text-sm">Belum ada batch.</p>
                    </div>
                )}
            </div>

            {batches.last_page > 1 && (
                <div className="flex flex-wrap gap-1">
                    {batches.links.map((link, i) => (
                        <Button
                            key={i}
                            size="sm"
                            variant={link.active ? 'default' : 'outline'}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function BatchesIndex({
    batches,
    filters,
    stages,
}: {
    batches: Paginated<Batch>;
    filters: { q?: string; stage?: string };
    stages: string[];
}) {
    const [q, setQ] = useState(filters.q ?? '');
    const { props } = usePage<SharedData>();
    const getInitials = useInitials();

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        router.get('/batches', { q, stage: filters.stage }, { preserveState: true, replace: true });
    };

    const setStage = (stage?: string) => {
        router.get('/batches', { q, stage }, { preserveState: true, replace: true });
    };

    const listPane = (
        <BatchListPane batches={batches} filters={filters} stages={stages} q={q} setQ={setQ} onSubmitSearch={submitSearch} onSetStage={setStage} />
    );

    return (
        <IpcShell
            title="Batch"
            subtitle={`${batches.total} batch`}
            headerActions={
                <>
                    <Link
                        href="/batches/create"
                        className="bg-primary hidden h-10 items-center gap-1.5 rounded-xl px-3.5 text-[13px] font-bold text-white md:flex"
                    >
                        + Batch Baru
                    </Link>
                    <div className="bg-primary/[0.1] text-primary flex size-11 items-center justify-center rounded-full text-[15px] font-bold">
                        {getInitials(props.auth.user.name)}
                    </div>
                </>
            }
        >
            <Head title="Batches" />
            <div className="flex min-h-0 flex-1">
                <div className="md:border-border-soft flex min-h-0 w-full flex-1 flex-col overflow-y-auto md:w-[360px] md:shrink-0 md:border-r lg:w-[420px]">
                    {listPane}
                </div>
                <div className="hidden min-w-0 flex-1 flex-col items-center justify-center gap-2 p-6 text-center md:flex">
                    <ClipboardList className="text-muted-foreground/50 size-10" />
                    <p className="font-bold">Pilih batch untuk melihat detail</p>
                    <p className="text-muted-foreground max-w-xs text-sm">
                        Klik salah satu batch di daftar untuk melihat progres dan mengisi tahapan pemeriksaan.
                    </p>
                </div>
            </div>
        </IpcShell>
    );
}
