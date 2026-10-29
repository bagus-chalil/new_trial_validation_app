import { MasterSearchBar } from '@/components/ipc/master-search-bar';
import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { IpcShell } from '@/layouts/ipc-shell';
import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, RefreshCw, TriangleAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type TrialStatus = 'approved' | 'in_progress' | 'rejected';
type FilterValue = 'all' | TrialStatus | 'missing';

interface TrialStatusRow {
    fg_code: string;
    product_name: string;
    status: TrialStatus;
    trial_count: number;
    latest_trial_code: string;
    latest_step: string | null;
    pending_with: string | null;
    last_approved_at: string | null;
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

const filterOptions: { value: FilterValue; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'approved', label: 'Sudah trial' },
    { value: 'in_progress', label: 'Trial berjalan' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'missing', label: 'Belum ada di Master IPC' },
];

/** How often the open page re-reads the trial app (the server also caches the API response for a few seconds). */
const POLL_MS = 20_000;
const HIGHLIGHT_MS = 10_000;

const rowSignature = (row: TrialStatusRow) => [row.status, row.trial_count, row.latest_trial_code, row.latest_step, row.pending_with].join('|');

const formatDate = (iso: string) => new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });

export default function TrialStatusIndex({
    rows,
    filters,
    counts,
    trialAppAvailable,
}: {
    rows: Paginated<TrialStatusRow>;
    filters: { q?: string; status?: string; master?: string };
    counts: Record<FilterValue, number>;
    trialAppAvailable: boolean;
}) {
    const activeFilter: FilterValue = filters.master === 'missing' ? 'missing' : ((filters.status as TrialStatus | undefined) ?? 'all');

    const [updatedAt, setUpdatedAt] = useState(() => new Date());
    const [changed, setChanged] = useState<Record<string, 'baru' | 'berubah'>>({});
    const baseline = useRef<{ view: string; signatures: Map<string, string> } | null>(null);
    const view = [rows.current_page, filters.q, filters.status, filters.master].join('|');

    // Poll while the tab is visible, and catch up right when it becomes visible again.
    useEffect(() => {
        const reload = () => {
            if (document.visibilityState === 'visible') router.reload({ only: ['rows', 'counts', 'trialAppAvailable'] });
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

    const paramsFor = (value: FilterValue): Record<string, string> => {
        const params: Record<string, string> = {};
        if (filters.q) params.q = filters.q;
        if (value === 'missing') params.master = 'missing';
        else if (value !== 'all') params.status = value;
        return params;
    };

    const setFilter = (value: FilterValue) => router.get(route('trial-status.index'), paramsFor(value), { preserveState: true, replace: true });
    const refresh = () => router.get(route('trial-status.index'), { ...paramsFor(activeFilter), refresh: '1' }, { replace: true });

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

                <MasterSearchBar
                    baseUrl={route('trial-status.index')}
                    initialQ={filters.q ?? ''}
                    placeholder="Cari FG code / nama produk / kode trial..."
                    extraParams={paramsFor(activeFilter)}
                />

                <div className="flex flex-wrap items-center gap-2">
                    {filterOptions.map((option) => {
                        const active = activeFilter === option.value;
                        return (
                            <button
                                key={option.value}
                                type="button"
                                aria-pressed={active}
                                onClick={() => setFilter(option.value)}
                                className={`flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-[12.5px] font-semibold transition-colors ${
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border-soft bg-card text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {option.label}
                                <span className={`text-[11.5px] tabular-nums ${active ? 'opacity-80' : 'text-muted-foreground/70'}`}>
                                    {counts[option.value].toLocaleString('id-ID')}
                                </span>
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-col gap-3">
                    {rows.data.map((row) => (
                        <div
                            key={row.fg_code}
                            className={`bg-card flex flex-col gap-2 rounded-[20px] border p-4 transition-colors duration-700 ${
                                changed[row.fg_code] ? 'border-primary ring-primary/20 ring-2' : 'border-border-soft'
                            }`}
                        >
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
                            </div>
                            <p className="text-[13.5px] font-semibold">{row.product_name}</p>
                            <div className="text-muted-foreground/80 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px] font-medium">
                                <span className="flex items-center gap-1.5">
                                    <ClipboardCheck className="size-3.5" strokeWidth={2} />
                                    {row.trial_count} trial · terakhir {row.latest_trial_code}
                                </span>
                                {row.status === 'in_progress' && (
                                    <span>
                                        Tahap: {row.latest_step ?? '-'}
                                        {row.pending_with ? ` · menunggu ${row.pending_with}` : ''}
                                    </span>
                                )}
                                {row.last_approved_at && <span>Approved terakhir {formatDate(row.last_approved_at)}</span>}
                                {row.in_master && <span>{row.ipc_batches} batch di IPC</span>}
                            </div>
                        </div>
                    ))}

                    {rows.data.length === 0 && (
                        <div className="border-border flex flex-col items-center gap-2 rounded-2xl border border-dashed py-12 text-center">
                            <ClipboardCheck className="text-muted-foreground/60 size-8" />
                            <p className="text-muted-foreground text-sm">Tidak ada FG code yang cocok.</p>
                        </div>
                    )}
                </div>

                <PaginationFooter links={rows.links} lastPage={rows.last_page} />
            </div>
        </IpcShell>
    );
}
