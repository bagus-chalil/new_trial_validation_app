import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { IpcShell } from '@/layouts/ipc-shell';
import { cn } from '@/lib/utils';
import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, Clock, Database, Hourglass, RefreshCw, TriangleAlert, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type TrialStatus = 'approved' | 'in_progress' | 'rejected';

interface TrialStatusRow {
    fg_code: string;
    product_name: string;
    status: TrialStatus;
    trial_count: number;
    latest_trial_code: string;
    latest_stage: string;
    latest_step: string | null;
    pending_with: string | null;
    last_approved_at: string | null;
    idle_days: number | null;
    in_master: boolean;
    ipc_batches: number;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    q?: string;
    status?: string;
    stage?: string;
    master?: string;
    stale?: string;
}

interface Counts {
    all: number;
    approved: number;
    in_progress: number;
    rejected: number;
    in_master: number;
    missing: number;
    stale: number;
}

const statusLabel: Record<TrialStatus, string> = {
    approved: 'Sudah trial (Approved)',
    in_progress: 'Trial berjalan',
    rejected: 'Trial ditolak',
};

const statusVariant: Record<TrialStatus, 'default' | 'secondary' | 'destructive'> = {
    approved: 'default',
    in_progress: 'secondary',
    rejected: 'destructive',
};

/** Left accent + KPI colour per status, so the list can be scanned by colour alone. */
const statusTone: Record<TrialStatus, { bar: string; text: string; accent: string }> = {
    approved: { bar: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400', accent: 'before:bg-emerald-500' },
    in_progress: { bar: 'bg-sky-500', text: 'text-sky-600 dark:text-sky-400', accent: 'before:bg-sky-500' },
    rejected: { bar: 'bg-red-500', text: 'text-red-600 dark:text-red-400', accent: 'before:bg-red-500' },
};

/** Where a trial's latest progress status sits on the four-step journey (Rejected has none). */
const journey = ['Draft', 'Review', 'Approval', 'Approved'];
const journeyIndex: Record<string, number> = { Draft: 0, 'Need Revision': 1, 'In Review': 1, 'Ready for Approval': 2, Approved: 3 };

/** How often the open page re-reads the trial app (the server also caches the API response for a few seconds). */
const POLL_MS = 20_000;
const HIGHLIGHT_MS = 10_000;

const rowSignature = (row: TrialStatusRow) =>
    [row.status, row.trial_count, row.latest_trial_code, row.latest_stage, row.latest_step, row.pending_with].join('|');

const formatDate = (iso: string) => new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

const prefersReducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Eases a number toward its new value so a live update reads as movement, not a jump. */
function useCountUp(target: number) {
    const [value, setValue] = useState(target);
    const shown = useRef(target);

    useEffect(() => {
        if (prefersReducedMotion() || shown.current === target) return;
        const origin = shown.current;
        const start = performance.now();
        let frame = 0;
        const tick = (now: number) => {
            const progress = Math.min(1, (now - start) / 500);
            shown.current = Math.round(origin + (target - origin) * (1 - Math.pow(1 - progress, 3)));
            setValue(shown.current);
            if (progress < 1) frame = requestAnimationFrame(tick);
        };
        frame = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(frame);
    }, [target]);

    return prefersReducedMotion() ? target : value;
}

function KpiCard({
    label,
    count,
    total,
    tone,
    active,
    onClick,
}: {
    label: string;
    count: number;
    total: number;
    tone: { bar: string; text: string } | null;
    active: boolean;
    onClick: () => void;
}) {
    const shown = useCountUp(count);
    const percent = total > 0 ? Math.round((count / total) * 100) : 0;

    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'bg-card focus-visible:ring-primary flex flex-col gap-2 rounded-[20px] border p-4 text-left transition duration-200 outline-none focus-visible:ring-2 active:scale-[0.98] motion-reduce:transition-none',
                active ? 'border-primary ring-primary/20 ring-2' : 'border-border-soft hover:border-primary/40 hover:shadow-sm',
            )}
        >
            <span className="text-muted-foreground text-[12px] font-semibold">{label}</span>
            <span className="flex items-baseline gap-2">
                <span className={cn('text-[28px] leading-none font-bold tracking-tight tabular-nums', tone?.text)}>
                    {shown.toLocaleString('id-ID')}
                </span>
                {tone && <span className="text-muted-foreground text-[12px] font-medium tabular-nums">{percent}%</span>}
            </span>
            <span className="bg-muted h-1.5 w-full overflow-hidden rounded-full">
                <span
                    className={cn(
                        'block h-full rounded-full transition-[width] duration-700 motion-reduce:transition-none',
                        tone?.bar ?? 'bg-primary',
                    )}
                    style={{ width: `${tone ? percent : 100}%` }}
                />
            </span>
        </button>
    );
}

function Panel({ title, icon: Icon, children }: { title: string; icon: React.ElementType; children: React.ReactNode }) {
    return (
        <section className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-4">
            <h2 className="text-muted-foreground flex items-center gap-2 text-[12px] font-semibold tracking-wide uppercase">
                <Icon className="size-3.5" strokeWidth={2.2} />
                {title}
            </h2>
            {children}
        </section>
    );
}

function Journey({ stage }: { stage: string }) {
    const reached = journeyIndex[stage] ?? 0;

    return (
        <div className="flex items-center gap-1" role="img" aria-label={`Tahap ${journey[reached]}`}>
            {journey.map((step, index) => (
                <span key={step} className="flex flex-col items-center gap-1">
                    <span className={cn('h-1.5 w-9 rounded-full transition-colors duration-500', index <= reached ? 'bg-sky-500' : 'bg-muted')} />
                    <span className={cn('text-[9.5px] font-semibold', index <= reached ? 'text-foreground/70' : 'text-muted-foreground/50')}>
                        {step}
                    </span>
                </span>
            ))}
        </div>
    );
}

export default function TrialStatusIndex({
    rows,
    filters,
    counts,
    stages,
    staleDays,
    trialAppAvailable,
}: {
    rows: Paginated<TrialStatusRow>;
    filters: Filters;
    counts: Counts;
    stages: { name: string; count: number }[];
    staleDays: number;
    trialAppAvailable: boolean;
}) {
    const [updatedAt, setUpdatedAt] = useState(() => new Date());
    const [changed, setChanged] = useState<Record<string, 'baru' | 'berubah'>>({});
    const baseline = useRef<{ view: string; signatures: Map<string, string> } | null>(null);
    const view = [rows.current_page, filters.q, filters.status, filters.stage, filters.master, filters.stale].join('|');

    // Poll while the tab is visible, and catch up right when it becomes visible again.
    useEffect(() => {
        const reload = () => {
            if (document.visibilityState === 'visible') router.reload({ only: ['rows', 'counts', 'stages', 'trialAppAvailable'] });
        };
        const timer = window.setInterval(reload, POLL_MS);
        document.addEventListener('visibilitychange', reload);
        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', reload);
        };
    }, []);

    // Compare each refresh with the previous one for the *same* page/filter, and flag what is new or changed.
    useEffect(() => {
        const signatures = new Map(rows.data.map((row) => [row.fg_code, rowSignature(row)]));
        const previous = baseline.current;
        baseline.current = { view, signatures };
        setUpdatedAt(new Date());
        if (!previous || previous.view !== view) return;

        const marks: Record<string, 'baru' | 'berubah'> = {};
        signatures.forEach((signature, fgCode) => {
            if (!previous.signatures.has(fgCode)) marks[fgCode] = 'baru';
            else if (previous.signatures.get(fgCode) !== signature) marks[fgCode] = 'berubah';
        });
        if (Object.keys(marks).length === 0) return;

        setChanged(marks);
        const timer = window.setTimeout(() => setChanged({}), HIGHLIGHT_MS);
        return () => window.clearTimeout(timer);
    }, [rows.data, view]);

    /** Filters other than the search box, as query params. */
    const filterParams = (overrides: Partial<Filters> = {}): Record<string, string> => {
        const merged: Filters = { status: filters.status, stage: filters.stage, master: filters.master, stale: filters.stale, ...overrides };
        return Object.fromEntries(Object.entries(merged).filter(([, value]) => value)) as Record<string, string>;
    };

    const visit = (params: Record<string, string>) =>
        router.get(route('trial-status.index'), filters.q ? { ...params, q: filters.q } : params, { preserveState: true, replace: true });

    /** Clicking an active filter turns it off; otherwise it replaces the status-type filters. */
    const pickStatus = (status: TrialStatus) =>
        visit(filterParams({ status: filters.status === status && !filters.stage ? undefined : status, stage: undefined, stale: undefined }));
    const pickStage = (stage: string) =>
        visit(filterParams({ status: 'in_progress', stage: filters.stage === stage ? undefined : stage, stale: undefined }));
    const toggle = (key: 'master' | 'stale', value: string) => visit(filterParams({ [key]: filters[key] === value ? undefined : value }));
    const clearAll = () => router.get(route('trial-status.index'), filters.q ? { q: filters.q } : {}, { preserveState: true, replace: true });
    const refresh = () =>
        router.get(route('trial-status.index'), { ...filterParams(), ...(filters.q ? { q: filters.q } : {}), refresh: '1' }, { replace: true });

    const activeChips = [
        filters.status &&
            !filters.stage && {
                label: statusLabel[filters.status as TrialStatus] ?? filters.status,
                clear: () => visit(filterParams({ status: undefined })),
            },
        filters.stage && { label: `Tahap: ${filters.stage}`, clear: () => visit(filterParams({ status: undefined, stage: undefined })) },
        filters.master === 'missing' && { label: 'Belum ada di Master IPC', clear: () => toggle('master', 'missing') },
        filters.stale && { label: `Mandek ≥ ${staleDays} hari`, clear: () => toggle('stale', '1') },
    ].filter(Boolean) as { label: string; clear: () => void }[];

    const maxStage = Math.max(1, ...stages.map((stage) => stage.count));
    const masterPercent = counts.all > 0 ? Math.round((counts.in_master / counts.all) * 100) : 0;
    const staleShown = useCountUp(counts.stale);

    return (
        <IpcShell
            title="Status Trial Produk"
            subtitle={`${counts.all} FG code punya trial`}
            backHref="/dashboard"
            headerActions={
                <Button type="button" variant="outline" onClick={refresh} className="h-10 gap-1.5 px-3.5 text-[13px]">
                    <RefreshCw className="size-4" strokeWidth={2.2} />
                    Muat ulang
                </Button>
            }
        >
            <Head title="Status Trial Produk" />
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto p-5 md:p-6">
                {!trialAppAvailable && (
                    <div className="flex items-start gap-2.5 rounded-2xl border border-amber-300/60 bg-amber-50 p-4 text-[13px] text-amber-900">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            Status trial belum bisa diperbarui dari aplikasi Trial Validation. Data yang tampil (kalau ada) adalah data terakhir yang
                            berhasil dimuat. Coba muat ulang; kalau tetap gagal, hubungi IT.
                        </p>
                    </div>
                )}

                <p className="text-muted-foreground flex items-center gap-2 text-[12px] font-medium">
                    <span className="relative flex size-2">
                        {trialAppAvailable && (
                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500/60 motion-reduce:animate-none" />
                        )}
                        <span className={`relative inline-flex size-2 rounded-full ${trialAppAvailable ? 'bg-emerald-500' : 'bg-amber-500'}`} />
                    </span>
                    {trialAppAvailable ? 'Live' : 'Tidak terhubung'} · diperbarui {updatedAt.toLocaleTimeString('id-ID')} · otomatis tiap{' '}
                    {POLL_MS / 1000} detik
                </p>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <KpiCard
                        label="Semua FG code"
                        count={counts.all}
                        total={counts.all}
                        tone={null}
                        active={activeChips.length === 0}
                        onClick={clearAll}
                    />
                    {(['approved', 'in_progress', 'rejected'] as const).map((status) => (
                        <KpiCard
                            key={status}
                            label={statusLabel[status]}
                            count={counts[status]}
                            total={counts.all}
                            tone={statusTone[status]}
                            active={filters.status === status && !filters.stage}
                            onClick={() => pickStatus(status)}
                        />
                    ))}
                </div>

                <div className="grid gap-3 lg:grid-cols-2">
                    <Panel title="Posisi proses (trial berjalan)" icon={Hourglass}>
                        {stages.length === 0 ? (
                            <p className="text-muted-foreground py-2 text-[13px]">Tidak ada trial yang sedang berjalan.</p>
                        ) : (
                            <div className="flex flex-col gap-1.5">
                                {stages.map((stage) => {
                                    const active = filters.stage === stage.name;
                                    return (
                                        <button
                                            key={stage.name}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() => pickStage(stage.name)}
                                            className={cn(
                                                'focus-visible:ring-primary group grid grid-cols-[130px_1fr_auto] items-center gap-3 rounded-xl px-2 py-1.5 text-left transition-colors outline-none focus-visible:ring-2',
                                                active ? 'bg-primary/8' : 'hover:bg-muted/60',
                                            )}
                                        >
                                            <span className={cn('truncate text-[12.5px] font-semibold', active && 'text-primary')}>{stage.name}</span>
                                            <span className="bg-muted h-2 overflow-hidden rounded-full">
                                                <span
                                                    className="block h-full rounded-full bg-sky-500 transition-[width] duration-700 motion-reduce:transition-none"
                                                    style={{ width: `${(stage.count / maxStage) * 100}%` }}
                                                />
                                            </span>
                                            <span className="text-[12.5px] font-bold tabular-nums">{stage.count}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </Panel>

                    <Panel title="Cakupan Master IPC & perhatian" icon={Database}>
                        <div className="flex flex-col gap-2">
                            <div className="flex items-baseline justify-between text-[12.5px] font-semibold">
                                <span>
                                    {counts.in_master} dari {counts.all} FG code sudah ada di Master Produk IPC
                                </span>
                                <span className="text-muted-foreground tabular-nums">{masterPercent}%</span>
                            </div>
                            <span className="bg-muted flex h-2.5 w-full overflow-hidden rounded-full">
                                <span
                                    className="h-full bg-emerald-500 transition-[width] duration-700 motion-reduce:transition-none"
                                    style={{ width: `${masterPercent}%` }}
                                />
                            </span>
                            <button
                                type="button"
                                aria-pressed={filters.master === 'missing'}
                                onClick={() => toggle('master', 'missing')}
                                className={cn(
                                    'focus-visible:ring-primary flex items-center justify-between rounded-xl border px-3 py-2 text-left text-[12.5px] font-semibold transition-colors outline-none focus-visible:ring-2',
                                    filters.master === 'missing' ? 'border-primary bg-primary/8' : 'border-border-soft hover:bg-muted/60',
                                )}
                            >
                                <span>Belum ada di Master IPC</span>
                                <span className="tabular-nums">{counts.missing}</span>
                            </button>
                            <button
                                type="button"
                                aria-pressed={Boolean(filters.stale)}
                                onClick={() => toggle('stale', '1')}
                                className={cn(
                                    'focus-visible:ring-primary flex items-center justify-between gap-2 rounded-xl border px-3 py-2 text-left text-[12.5px] font-semibold transition-colors outline-none focus-visible:ring-2',
                                    filters.stale ? 'border-amber-500 bg-amber-500/10' : 'border-border-soft hover:bg-muted/60',
                                    counts.stale > 0 && !filters.stale && 'border-amber-400/60',
                                )}
                            >
                                <span className="flex items-center gap-1.5">
                                    <Clock className="size-3.5 text-amber-600 dark:text-amber-400" strokeWidth={2.2} />
                                    Perlu perhatian: mandek ≥ {staleDays} hari
                                </span>
                                <span className="text-amber-700 tabular-nums dark:text-amber-300">{staleShown}</span>
                            </button>
                        </div>
                    </Panel>
                </div>

                <MasterSearchBar
                    baseUrl={route('trial-status.index')}
                    initialQ={filters.q ?? ''}
                    placeholder="Cari FG code / nama produk / kode trial..."
                    extraParams={filterParams()}
                />

                {activeChips.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground text-[12px] font-medium">Filter aktif:</span>
                        {activeChips.map((chip) => (
                            <button
                                key={chip.label}
                                type="button"
                                onClick={chip.clear}
                                className="border-primary/30 bg-primary/8 text-primary hover:bg-primary/15 flex h-7 items-center gap-1 rounded-full border px-2.5 text-[12px] font-semibold transition-colors"
                            >
                                {chip.label}
                                <X className="size-3" />
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={clearAll}
                            className="text-muted-foreground hover:text-foreground text-[12px] font-medium underline underline-offset-2"
                        >
                            Hapus semua
                        </button>
                    </div>
                )}

                <div className="flex flex-col gap-3">
                    {rows.data.map((row) => {
                        const stale = row.idle_days !== null && row.idle_days >= staleDays;
                        return (
                            <div
                                key={row.fg_code}
                                className={cn(
                                    'bg-card relative flex flex-col gap-3 overflow-hidden rounded-[20px] border p-4 pl-5 transition-colors duration-700 before:absolute before:inset-y-0 before:left-0 before:w-1',
                                    statusTone[row.status].accent,
                                    changed[row.fg_code] ? 'border-primary ring-primary/20 ring-2' : 'border-border-soft',
                                )}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-[15px] font-bold tracking-tight">{row.fg_code}</p>
                                            <Badge variant={statusVariant[row.status]} className="text-[10.5px]">
                                                {statusLabel[row.status]}
                                            </Badge>
                                            {changed[row.fg_code] && <Badge className="text-[10.5px] capitalize">{changed[row.fg_code]}</Badge>}
                                            {!row.in_master && (
                                                <Badge variant="outline" className="text-[10.5px]">
                                                    Belum ada di Master IPC
                                                </Badge>
                                            )}
                                            {stale && (
                                                <Badge className="border-transparent bg-amber-500/15 text-[10.5px] text-amber-700 dark:text-amber-300">
                                                    Mandek {row.idle_days} hari
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-[13.5px] font-semibold">{row.product_name}</p>
                                    </div>
                                    {row.status !== 'rejected' && <Journey stage={row.status === 'approved' ? 'Approved' : row.latest_stage} />}
                                </div>
                                <div className="text-muted-foreground/80 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px] font-medium">
                                    <span className="flex items-center gap-1.5">
                                        <ClipboardCheck className="size-3.5" strokeWidth={2} />
                                        {row.trial_count} trial · terakhir {row.latest_trial_code}
                                    </span>
                                    {row.status === 'in_progress' && (
                                        <span>
                                            {row.latest_stage}
                                            {row.latest_step ? ` · tahap ${row.latest_step}` : ''}
                                            {row.pending_with ? ` · menunggu ${row.pending_with}` : ''}
                                            {row.idle_days !== null && !stale
                                                ? ` · ${row.idle_days === 0 ? 'hari ini' : `${row.idle_days} hari lalu`}`
                                                : ''}
                                        </span>
                                    )}
                                    {row.last_approved_at && <span>Approved terakhir {formatDate(row.last_approved_at)}</span>}
                                    {row.in_master && <span>{row.ipc_batches} batch di IPC</span>}
                                </div>
                            </div>
                        );
                    })}

                    {rows.data.length === 0 && (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                            <ClipboardCheck className="text-muted-foreground/60 size-8" />
                            <p className="text-muted-foreground text-sm">Tidak ada FG code yang cocok.</p>
                            {(activeChips.length > 0 || filters.q) && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.get(route('trial-status.index'), {}, { replace: true })}
                                >
                                    Reset semua filter
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                <PaginationFooter links={rows.links} lastPage={rows.last_page} />
            </div>
        </IpcShell>
    );
}
