import { BatchActionsMenu } from '@/components/ipc/batch-actions-menu';
import { BatchCreatedAt } from '@/components/ipc/batch-created-at';
import { BatchFilterBar, type BatchFilterOptions, type BatchFilterValues } from '@/components/ipc/batch-filter-bar';
import { Button } from '@/components/ui/button';
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group';
import { useInitials } from '@/hooks/use-initials';
import { IpcShell } from '@/layouts/ipc-shell';
import { stageBadgeStyle, stageLabel } from '@/lib/ipc-stages';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, ChevronRight, ClipboardList, Layers3, Search, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MasterProduct {
    id: number;
    fg_code: string;
    product_name: string;
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
    master_line: MasterLine | null;
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

function BatchCard({ batch, canManage }: { batch: Batch; canManage: boolean }) {
    const cta = cardCta(batch);

    // Stretched link (the Link's ::after covers the card) instead of wrapping the card in the
    // Link, so the admin ⋮ menu can sit on top without nesting a button inside an <a>.
    return (
        <div className="border-border-soft bg-card hover:border-primary/40 relative flex flex-col gap-3 rounded-[20px] border p-4 shadow-sm transition-[border-color,box-shadow] hover:shadow-md">
            <div className="flex items-start justify-between gap-2.5">
                <div className="min-w-0">
                    <Link
                        href={`/batches/${batch.id}`}
                        className="focus-visible:after:ring-primary text-[16.5px] font-bold tracking-tight after:absolute after:inset-0 after:rounded-[20px] focus-visible:outline-none focus-visible:after:ring-2"
                    >
                        {batch.no_batch}
                    </Link>
                    <p className="text-muted-foreground mt-0.5 truncate text-[13px] font-medium">
                        {batch.master_product.product_name} &middot; {batch.master_product.fg_code}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    <span
                        className="rounded-full px-[11px] py-[5px] text-[11.5px] font-bold whitespace-nowrap"
                        style={stageBadgeStyle(batch.current_stage)}
                    >
                        {stageLabel(batch.current_stage)}
                    </span>
                    {canManage && <BatchActionsMenu batch={batch} />}
                </div>
            </div>

            <div className="text-muted-foreground/70 flex items-center gap-1.5 text-[12.5px] font-medium">
                <ClipboardList className="size-3.5" strokeWidth={2} />
                {batch.master_line?.name ?? '—'} &middot; dibuat oleh {batch.creator.name}
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
            <BatchCreatedAt value={batch.created_at} />
        </div>
    );
}

function BatchListPane({
    batches,
    filters,
    filterOptions,
    stages,
    q,
    setQ,
    onSubmitSearch,
    onClearSearch,
    onSetStage,
}: {
    batches: Paginated<Batch>;
    filters: BatchFilterValues;
    filterOptions: BatchFilterOptions;
    stages: string[];
    q: string;
    setQ: (value: string) => void;
    onSubmitSearch: FormEventHandler;
    onClearSearch: () => void;
    onSetStage: (stage?: string) => void;
}) {
    const { canManageMaster } = usePage<SharedData>().props;

    return (
        <div className="flex flex-1 flex-col gap-4 p-5 md:p-5">
            <form onSubmit={onSubmitSearch}>
                <InputGroup className="border-border-soft bg-card h-12 rounded-2xl">
                    <InputGroupAddon>
                        <Search className="text-muted-foreground/70 size-[18px]" strokeWidth={2} />
                    </InputGroupAddon>
                    <InputGroupInput
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        aria-label="Cari nomor batch atau produk"
                        placeholder="Cari no batch / produk..."
                        className="text-sm"
                    />
                    {q && (
                        <InputGroupAddon align="inline-end">
                            <InputGroupButton type="button" size="icon-xs" aria-label="Bersihkan pencarian" onClick={onClearSearch}>
                                <X className="size-3.5" />
                            </InputGroupButton>
                        </InputGroupAddon>
                    )}
                </InputGroup>
            </form>

            <BatchFilterBar
                path="/batches"
                filters={filters}
                options={filterOptions}
                leadingControl={
                    <select
                        aria-label="Kategori tahapan batch"
                        value={filters.stage ?? ''}
                        onChange={(e) => onSetStage(e.target.value || undefined)}
                        className="border-border-soft bg-card text-foreground focus-visible:ring-primary/30 h-12 min-w-0 flex-1 rounded-2xl border px-3 text-[13px] font-semibold outline-none focus-visible:ring-2"
                    >
                        <option value="">Semua tahapan</option>
                        {stages.map((stage) => (
                            <option key={stage} value={stage}>
                                {stageLabel(stage)}
                            </option>
                        ))}
                    </select>
                }
            />

            <div className="flex flex-col gap-3">
                {batches.data.map((batch) => (
                    <BatchCard key={batch.id} batch={batch} canManage={!!canManageMaster} />
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
    filterOptions,
    stages,
}: {
    batches: Paginated<Batch>;
    filters: BatchFilterValues;
    filterOptions: BatchFilterOptions;
    stages: string[];
}) {
    const [q, setQ] = useState(filters.q ?? '');
    const { props } = usePage<SharedData>();
    const getInitials = useInitials();

    // Search / stage selection keep whatever the filter bar has applied (date, creator, line, flow).
    const visit = (patch: BatchFilterValues) => {
        const params = Object.fromEntries(Object.entries({ ...filters, ...patch }).filter(([, value]) => value !== undefined && value !== ''));
        router.get('/batches', params, { preserveState: true, replace: true });
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        visit({ q });
    };

    const clearSearch = () => {
        setQ('');
        visit({ q: undefined });
    };

    const setStage = (stage?: string) => {
        visit({ q, stage });
    };

    const listPane = (
        <BatchListPane
            batches={batches}
            filters={filters}
            filterOptions={filterOptions}
            stages={stages}
            q={q}
            setQ={setQ}
            onSubmitSearch={submitSearch}
            onClearSearch={clearSearch}
            onSetStage={setStage}
        />
    );

    return (
        <IpcShell
            title="Batch"
            subtitle={`${batches.total} batch`}
            headerActions={
                <>
                    {props.canCreateBatch && (
                        <Link
                            href="/batches/create"
                            className="bg-primary hidden h-10 items-center gap-1.5 rounded-xl px-3.5 text-[13px] font-bold text-white md:flex"
                        >
                            + Batch Baru
                        </Link>
                    )}
                    <div className="bg-primary/[0.1] text-primary flex size-11 items-center justify-center rounded-full text-[15px] font-bold">
                        {getInitials(props.auth.user.name)}
                    </div>
                </>
            }
        >
            <Head title="Batches" />
            <div className="flex min-h-0 flex-1">
                <div className="md:border-border-soft flex min-h-0 w-full flex-col overflow-y-auto md:w-[360px] md:shrink-0 md:border-r lg:w-[440px] xl:w-[480px]">
                    {listPane}
                </div>
                <section className="bg-primary/[0.025] hidden min-w-0 flex-1 overflow-y-auto p-6 md:block lg:p-8" aria-label="Ringkasan daftar batch">
                    <div className="mx-auto flex max-w-3xl flex-col gap-4">
                        <div className="border-border-soft bg-card rounded-3xl border p-5 shadow-sm lg:p-6">
                            <div className="bg-primary/10 text-primary mb-3 flex size-9 items-center justify-center rounded-2xl">
                                <Layers3 className="size-5" />
                            </div>
                            <p className="text-primary text-xs font-bold tracking-widest uppercase">Batch workspace</p>
                            <h2 className="mt-2 text-xl font-bold tracking-tight">Pantau proses, lanjutkan pemeriksaan.</h2>
                            <p className="text-muted-foreground mt-2 max-w-lg text-[13px] leading-relaxed">
                                Cari batch, pilih tahapan, lalu buka detail untuk melihat progres dan melanjutkan pemeriksaan.
                            </p>
                            <div className="border-border-soft mt-4 grid grid-cols-2 gap-4 border-t pt-3">
                                <div>
                                    <p className="text-2xl font-bold">{batches.total}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Batch sesuai filter</p>
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{batches.data.length}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">Ditampilkan di halaman ini</p>
                                </div>
                            </div>
                        </div>
                        <div className="border-border-soft bg-card rounded-3xl border p-5 shadow-sm">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="font-bold">Tahapan di halaman ini</h3>
                                    <p className="text-muted-foreground mt-1 text-xs">Pilih tahapan untuk memfilter daftar batch.</p>
                                </div>
                                <ClipboardList className="text-primary size-5 shrink-0" />
                            </div>
                            <div className="grid gap-2 lg:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setStage()}
                                    aria-pressed={!filters.stage}
                                    className={`focus-visible:outline-primary flex items-center gap-2 rounded-xl border px-3 py-2 text-left transition-colors focus-visible:outline-2 ${!filters.stage ? 'border-primary/40 bg-primary/5' : 'border-border-soft hover:bg-muted'}`}
                                >
                                    <span className="min-w-0 flex-1 text-xs font-semibold">Semua</span>
                                    <ArrowUpRight className="text-muted-foreground size-3.5 shrink-0" />
                                </button>
                                {stages.map((stage) => {
                                    const count = batches.data.filter((batch) => batch.current_stage === stage).length;
                                    return (
                                        <button
                                            key={stage}
                                            type="button"
                                            onClick={() => setStage(stage)}
                                            aria-pressed={filters.stage === stage}
                                            className={`focus-visible:outline-primary flex items-center gap-2 rounded-xl border px-3 py-2 text-left transition-colors focus-visible:outline-2 ${filters.stage === stage ? 'border-primary/40 bg-primary/5' : 'border-border-soft hover:bg-muted'}`}
                                        >
                                            <span className="min-w-0 flex-1 text-xs font-semibold">{stageLabel(stage)}</span>
                                            <span className="bg-muted flex size-6 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                                                {count}
                                            </span>
                                            <ArrowUpRight className="text-muted-foreground size-3.5 shrink-0" />
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                        <p className="text-muted-foreground px-2 text-xs leading-relaxed">
                            Pilih kartu batch pada daftar untuk membuka detail dan tahapan pemeriksaannya.
                        </p>
                    </div>
                </section>
            </div>
        </IpcShell>
    );
}
