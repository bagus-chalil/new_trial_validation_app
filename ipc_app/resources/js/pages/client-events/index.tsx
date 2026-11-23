import { PaginationFooter } from '@/components/ipc/pagination-footer';
import { Button } from '@/components/ui/button';
import { IpcShell } from '@/layouts/ipc-shell';
import { cn } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Bug, CheckCircle2, ChevronDown, LoaderCircle, Sparkles, WifiOff } from 'lucide-react';
import { useState } from 'react';

interface Spread {
    device: string | null;
    browser: string | null;
    count: number;
}

interface Issue {
    fingerprint: string;
    kind: string | null;
    message: string | null;
    detail: string | null;
    url: string | null;
    batch_id: number | null;
    events: number;
    open_events: number;
    users: number;
    first_seen: string;
    last_seen: string;
    last_user: string | null;
    last_online: boolean | null;
    last_net_type: string | null;
    last_app_version: string | null;
    spread: Spread[];
}

interface Props {
    issues: { data: Issue[]; links: { url: string | null; label: string; active: boolean }[]; last_page: number; total: number };
    filters: { status: string; kind: string | null; days: number };
    kinds: string[];
    aiEnabled: boolean;
    summary: { open_issues: number; events_today: number; top_devices: Spread[] };
}

const KIND_LABEL: Record<string, string> = {
    js_error: 'Error halaman',
    promise: 'Error proses',
    request_failed: 'Request gagal',
    slow_request: 'Request lambat',
    camera: 'Kamera',
    upload: 'Upload',
    user_report: 'Laporan operator',
    server_error: 'Error server',
};

const STATUS_TABS = [
    { value: 'open', label: 'Belum selesai' },
    { value: 'resolved', label: 'Selesai' },
    { value: 'all', label: 'Semua' },
];

const RANGES = [
    { value: 1, label: '24 jam' },
    { value: 7, label: '7 hari' },
    { value: 30, label: '30 hari' },
    { value: 90, label: '90 hari' },
];

// Server timestamps are plain "YYYY-MM-DD HH:mm:ss" in the app timezone, same as the operators' own.
const fmt = (iso: string) =>
    new Date(iso.replace(' ', 'T')).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

const chip = (active: boolean) =>
    cn(
        'focus-visible:ring-primary rounded-xl px-3 py-2 text-[12.5px] font-semibold transition-colors focus-visible:ring-2',
        active ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:text-foreground',
    );

function IssueCard({ issue }: { issue: Issue }) {
    const [open, setOpen] = useState(false);
    const resolved = issue.open_events === 0;

    return (
        <div className={cn('border-border-soft bg-card rounded-2xl border p-4', resolved && 'opacity-70')}>
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                    <div className="mb-1.5 flex flex-wrap items-center gap-2">
                        <span className="bg-primary/10 text-primary rounded-full px-2.5 py-1 text-[11px] font-bold">
                            {KIND_LABEL[issue.kind ?? ''] ?? issue.kind}
                        </span>
                        <span className="text-muted-foreground text-[11.5px] font-medium">
                            {issue.events}× · {issue.users} user · terakhir {fmt(issue.last_seen)}
                        </span>
                        {issue.last_online === false && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-[11px] font-bold text-amber-800">
                                <WifiOff className="size-3" /> offline
                            </span>
                        )}
                    </div>
                    <p className="text-[13.5px] leading-snug font-semibold break-words">{issue.message}</p>
                    <p className="text-muted-foreground mt-1 text-[11.5px] break-all">
                        {issue.url}
                        {issue.batch_id ? (
                            <>
                                {' · '}
                                <Link href={`/batches/${issue.batch_id}`} className="text-primary underline">
                                    batch #{issue.batch_id}
                                </Link>
                            </>
                        ) : null}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {issue.spread.map((s) => (
                            <span key={`${s.device}-${s.browser}`} className="bg-muted rounded-lg px-2 py-1 text-[11px] font-medium">
                                {s.device} · {s.browser} <b>({s.count})</b>
                            </span>
                        ))}
                    </div>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Button type="button" size="sm" variant="outline" onClick={() => setOpen(!open)} className="gap-1.5 text-[12px]">
                        Detail <ChevronDown className={cn('size-3.5 transition-transform', open && 'rotate-180')} />
                    </Button>
                    {!resolved && (
                        <Button
                            type="button"
                            size="sm"
                            className="gap-1.5 text-[12px]"
                            onClick={() => router.post(route('client-events.resolve'), { fingerprint: issue.fingerprint }, { preserveScroll: true })}
                        >
                            <CheckCircle2 className="size-3.5" /> Tandai selesai
                        </Button>
                    )}
                </div>
            </div>
            {open && (
                <div className="border-border-soft mt-3 flex flex-col gap-1.5 border-t pt-3 text-[12px]">
                    <p>
                        <b>Pertama terlihat:</b> {fmt(issue.first_seen)} · <b>User terakhir:</b> {issue.last_user ?? 'tamu / sistem'} ·{' '}
                        <b>Koneksi:</b> {issue.last_net_type ?? '—'} · <b>Versi app:</b> {issue.last_app_version ?? '—'}
                    </p>
                    {issue.detail && (
                        <pre className="bg-muted max-h-48 overflow-auto rounded-xl p-3 text-[11px] whitespace-pre-wrap">{issue.detail}</pre>
                    )}
                </div>
            )}
        </div>
    );
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** "Analisis AI": advisory summary of the open issues (Ollama via the Vision Service). Never changes data. */
function AiAnalysis({ days }: { days: number }) {
    const [state, setState] = useState<{ loading: boolean; text?: string; error?: string }>({ loading: false });

    const run = async () => {
        setState({ loading: true });
        try {
            const response = await fetch(route('client-events.analyze'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ days }),
            });
            const data = (await response.json().catch(() => ({}))) as { ok?: boolean; text?: string; error?: string };
            setState(
                data.ok && data.text
                    ? { loading: false, text: data.text }
                    : { loading: false, error: data.error ?? `Analisis gagal (HTTP ${response.status}).` },
            );
        } catch {
            setState({ loading: false, error: 'Tidak bisa menghubungi server.' });
        }
    };

    return (
        <div className="border-border-soft bg-card flex flex-col gap-3 rounded-2xl border p-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-[13.5px] font-bold">Analisis AI</p>
                    <p className="text-muted-foreground text-[11.5px]">
                        Ringkasan pola dan dugaan penyebab dari kendala yang belum selesai. Hanya saran, data tidak berubah.
                    </p>
                </div>
                <Button type="button" size="sm" onClick={() => void run()} disabled={state.loading} className="gap-1.5 text-[12px]">
                    {state.loading ? <LoaderCircle className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                    {state.loading ? 'Menganalisis...' : 'Analisis'}
                </Button>
            </div>
            {state.loading && <p className="text-muted-foreground text-[11.5px]">Bisa sampai 1-2 menit (server AI memproses satu per satu).</p>}
            {state.error && <p className="text-[12.5px] font-medium text-red-600">{state.error}</p>}
            {state.text && <p className="bg-muted rounded-xl p-3 text-[12.5px] leading-relaxed whitespace-pre-wrap">{state.text}</p>}
        </div>
    );
}

export default function ClientEventsIndex({ issues, filters, kinds, aiEnabled, summary }: Props) {
    const go = (next: Partial<Props['filters']>) =>
        router.get(route('client-events.index'), { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <IpcShell title="Log Kendala" subtitle="Masalah yang tertangkap dari lapangan" backHref="/dashboard">
            <Head title="Log Kendala" />
            <div className="flex flex-1 flex-col gap-5 overflow-y-auto p-5 md:p-6">
                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <div className="border-border-soft bg-card rounded-2xl border p-4">
                        <p className="text-muted-foreground text-[11.5px] font-semibold">Kendala belum selesai (7 hari)</p>
                        <p className="mt-1 text-3xl font-bold">{summary.open_issues}</p>
                    </div>
                    <div className="border-border-soft bg-card rounded-2xl border p-4">
                        <p className="text-muted-foreground text-[11.5px] font-semibold">Kejadian hari ini</p>
                        <p className="mt-1 text-3xl font-bold">{summary.events_today}</p>
                    </div>
                    <div className="border-border-soft bg-card col-span-2 rounded-2xl border p-4 md:col-span-1">
                        <p className="text-muted-foreground text-[11.5px] font-semibold">Perangkat paling bermasalah</p>
                        {summary.top_devices.length === 0 ? (
                            <p className="mt-1 text-sm font-medium">Belum ada.</p>
                        ) : (
                            <ul className="mt-1 flex flex-col gap-0.5 text-[12px] font-medium">
                                {summary.top_devices.slice(0, 3).map((d) => (
                                    <li key={`${d.device}-${d.browser}`}>
                                        {d.device} · {d.browser} <b>({d.count})</b>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                {aiEnabled && <AiAnalysis days={filters.days} />}

                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Status kendala">
                        {STATUS_TABS.map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                aria-pressed={filters.status === tab.value}
                                onClick={() => go({ status: tab.value })}
                                className={chip(filters.status === tab.value)}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Rentang waktu">
                        {RANGES.map((range) => (
                            <button
                                key={range.value}
                                type="button"
                                aria-pressed={filters.days === range.value}
                                onClick={() => go({ days: range.value })}
                                className={chip(filters.days === range.value)}
                            >
                                {range.label}
                            </button>
                        ))}
                    </div>
                    <select
                        aria-label="Jenis kendala"
                        value={filters.kind ?? ''}
                        onChange={(event) => go({ kind: event.target.value || null })}
                        className="border-border bg-background h-9 rounded-xl border-[1.5px] px-3 text-[12.5px] font-semibold"
                    >
                        <option value="">Semua jenis</option>
                        {kinds.map((kind) => (
                            <option key={kind} value={kind}>
                                {KIND_LABEL[kind] ?? kind}
                            </option>
                        ))}
                    </select>
                </div>

                {issues.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 py-16 text-center">
                        <Bug className="text-muted-foreground/40 size-12" strokeWidth={1.5} />
                        <p className="text-muted-foreground text-sm">Tidak ada kendala pada filter ini.</p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {issues.data.map((issue) => (
                            <IssueCard key={issue.fingerprint} issue={issue} />
                        ))}
                    </div>
                )}

                <PaginationFooter links={issues.links} lastPage={issues.last_page} />
            </div>
        </IpcShell>
    );
}
