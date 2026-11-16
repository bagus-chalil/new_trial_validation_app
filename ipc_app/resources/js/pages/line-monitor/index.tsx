import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { IpcShell } from '@/layouts/ipc-shell';
import { STAGE_BADGE_COLORS, stageLabel } from '@/lib/ipc-stages';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Copy,
    ExternalLink,
    Maximize2,
    Minimize2,
    Moon,
    Pause,
    Play,
    QrCode,
    Radio,
    RefreshCw,
    Share2,
    User,
} from 'lucide-react';
import { type ReactNode, type RefObject, useEffect, useMemo, useRef, useState } from 'react';

interface LineBatch {
    id: number;
    no_batch: string;
    product_name: string | null;
    fg_code: string | null;
    stage: string;
    stage_label: string;
    stages: string[];
    flow: string | null;
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
    public_url: string | null;
    qr_url: string | null;
    batches: LineBatch[];
}

interface ShareInfo {
    url: string;
    qr_svg: string;
    qr_url: string;
    can_regenerate: boolean;
}

/** Copy text to the clipboard; falls back to a hidden textarea where the Clipboard API is blocked (plain HTTP). */
async function copyText(text: string) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
}

function useCopied(): [boolean, (text: string) => void] {
    const [copied, setCopied] = useState(false);
    return [
        copied,
        (text: string) => {
            void copyText(text).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1800);
            });
        },
    ];
}

/* Wallboard tuning */
const REFRESH_MS = 30_000;
const SCROLL_SPEEDS = [
    { key: 'slow', label: 'Lambat', pxPerSec: 16 },
    { key: 'normal', label: 'Sedang', pxPerSec: 34 },
    { key: 'fast', label: 'Cepat', pxPerSec: 70 },
] as const;
type ScrollSpeed = (typeof SCROLL_SPEEDS)[number]['key'];
const EDGE_HOLD_MS = 4_000; // pause at the top and bottom before moving on
const USER_PAUSE_MS = 15_000; // a touch / wheel pauses auto-scroll this long
const STALE_MIN = 120; // > 2 h without activity: "lama"
const STUCK_MIN = 24 * 60; // > 1 day: "macet"
const MAX_BATCHES_PER_CARD = 3;

const STAGE_ORDER = ['startup', 'filling', 'packing', 'finished', 'approval', 'print'];
const SHORT_STAGE: Record<string, string> = {
    startup: 'Startup',
    filling: 'Filling',
    packing: 'Packing',
    finished: 'FG',
    approval: 'Approval',
    print: 'Print',
};

function stageColor(stage: string) {
    return STAGE_BADGE_COLORS[stage] ?? { bg: '#F4F4F5', fg: '#71717A' };
}

function idleMinutes(iso: string | null): number | null {
    if (!iso) return null;
    return Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
}

function formatIdle(min: number | null): string {
    if (min === null) return '-';
    if (min < 1) return 'baru saja';
    if (min < 60) return `${min} mnt lalu`;
    if (min < 1440) return `${Math.floor(min / 60)} jam ${min % 60} mnt lalu`;
    return `${Math.floor(min / 1440)} hari lalu`;
}

function health(min: number | null): 'ok' | 'stale' | 'stuck' {
    if (min === null) return 'ok';
    if (min > STUCK_MIN) return 'stuck';
    if (min > STALE_MIN) return 'stale';
    return 'ok';
}

function readPref(key: string, fallback: boolean): boolean {
    try {
        const v = window.localStorage.getItem(key);
        return v === null ? fallback : v === '1';
    } catch {
        return fallback;
    }
}

function writePref(key: string, value: boolean) {
    try {
        window.localStorage.setItem(key, value ? '1' : '0');
    } catch {
        // storage blocked: the toggle just isn't remembered
    }
}

/**
 * Slow continuous auto-scroll for a TV/wall display. Holds a few seconds at the top and at the
 * bottom, then glides back up to the top (eased animation, not a jump). Any touch, wheel, drag or key pauses it for a while so a
 * person standing at the screen can read or scroll by hand.
 */
const REWIND_SPEEDUP = 4; // going back up is 4x the chosen scroll speed...
const REWIND_MIN_MS = 1_500; // ...but never shorter than this (still visibly animated)
const REWIND_MAX_MS = 6_000; // ...nor longer than this on a very long board

function easeInOutCubic(x: number): number {
    return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
}

function useAutoScroll(ref: RefObject<HTMLDivElement | null>, enabled: boolean, pxPerSec: number) {
    const [progress, setProgress] = useState(0);
    // Read through a ref so changing the speed doesn't restart the loop (no jump back to the top).
    const speedRef = useRef(pxPerSec);
    useEffect(() => {
        speedRef.current = pxPerSec;
    }, [pxPerSec]);

    useEffect(() => {
        const el = ref.current;
        if (!el || !enabled) return;

        let raf = 0;
        let last = 0;
        let pos = el.scrollTop;
        let holdUntil = performance.now() + EDGE_HOLD_MS;
        let atEnd = false;
        let resync = false;
        let lastReported = -1;
        // Animated return to the top (instead of a hard jump): eased, a few times faster than going down.
        let rewind: { from: number; start: number; duration: number } | null = null;

        const pause = () => {
            holdUntil = performance.now() + USER_PAUSE_MS;
            atEnd = false;
            rewind = null;
            resync = true;
        };

        const tick = (t: number) => {
            const dt = last ? Math.min(t - last, 100) : 0;
            last = t;
            const max = el.scrollHeight - el.clientHeight;

            if (resync) {
                pos = el.scrollTop;
                if (t >= holdUntil) resync = false;
            }

            if (max > 2 && t >= holdUntil && !resync) {
                if (atEnd) {
                    atEnd = false;
                    const duration = Math.min(REWIND_MAX_MS, Math.max(REWIND_MIN_MS, (pos / (speedRef.current * REWIND_SPEEDUP)) * 1000));
                    rewind = { from: pos, start: t, duration };
                } else if (rewind) {
                    const p = Math.min(1, (t - rewind.start) / rewind.duration);
                    pos = rewind.from * (1 - easeInOutCubic(p));
                    el.scrollTop = pos;
                    if (p >= 1) {
                        rewind = null;
                        pos = 0;
                        el.scrollTop = 0;
                        holdUntil = t + EDGE_HOLD_MS;
                    }
                } else {
                    pos = Math.min(max, pos + (speedRef.current * dt) / 1000);
                    el.scrollTop = pos;
                    if (pos >= max - 1) {
                        atEnd = true;
                        holdUntil = t + EDGE_HOLD_MS;
                    }
                }
            }

            const pct = max > 2 ? Math.round((el.scrollTop / max) * 100) : 0;
            if (pct !== lastReported) {
                lastReported = pct;
                setProgress(pct);
            }
            raf = requestAnimationFrame(tick);
        };

        const opts = { passive: true } as const;
        el.addEventListener('wheel', pause, opts);
        el.addEventListener('touchstart', pause, opts);
        el.addEventListener('pointerdown', pause, opts);
        el.addEventListener('keydown', pause);
        raf = requestAnimationFrame(tick);

        return () => {
            cancelAnimationFrame(raf);
            el.removeEventListener('wheel', pause);
            el.removeEventListener('touchstart', pause);
            el.removeEventListener('pointerdown', pause);
            el.removeEventListener('keydown', pause);
        };
    }, [ref, enabled]);

    return progress;
}

function Clock() {
    const [now, setNow] = useState(() => new Date());
    useEffect(() => {
        const t = setInterval(() => setNow(new Date()), 1000);
        return () => clearInterval(t);
    }, []);

    return (
        <div className="text-right leading-tight">
            <p className="font-mono text-[22px] font-bold tracking-tight tabular-nums">
                {now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
            </p>
            <p className="text-muted-foreground text-[11.5px] font-medium">
                {now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
            </p>
        </div>
    );
}

function LiveBadge({ lastSync }: { lastSync: number }) {
    const [left, setLeft] = useState(REFRESH_MS / 1000);
    useEffect(() => {
        const t = setInterval(() => setLeft(Math.max(0, Math.ceil((lastSync + REFRESH_MS - Date.now()) / 1000))), 500);
        return () => clearInterval(t);
    }, [lastSync]);

    return (
        <span className="inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1.5 text-[11.5px] font-bold text-green-700">
            <span className="relative flex size-2">
                <span className="absolute inline-flex size-full animate-ping rounded-full bg-green-500 opacity-60" />
                <span className="relative inline-flex size-2 rounded-full bg-green-500" />
            </span>
            LIVE &middot; {left}s
        </span>
    );
}

function IconToggle({ onClick, title, active, children }: { onClick: () => void; title: string; active?: boolean; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={title}
            aria-label={title}
            aria-pressed={active}
            className={
                'flex size-10 items-center justify-center rounded-xl border transition-colors ' +
                (active ? 'border-foreground bg-foreground text-background' : 'border-border-soft bg-card text-foreground hover:bg-muted')
            }
        >
            {children}
        </button>
    );
}

/** One segment per stage of the batch's own flow; done = filled, current = pulsing, next = empty. */
function StageTrack({ batch }: { batch: LineBatch }) {
    const current = batch.stages.indexOf(batch.stage);
    const color = stageColor(batch.stage);

    return (
        <div className="mt-2.5">
            <div className="flex gap-1">
                {batch.stages.map((s, i) => {
                    const done = current > -1 && i < current;
                    const isCurrent = i === current;
                    return (
                        <div
                            key={s}
                            className="relative h-1.5 flex-1 overflow-hidden rounded-full"
                            style={{ backgroundColor: done ? '#A1A1AA' : isCurrent ? color.fg : '#E4E4E7' }}
                        >
                            {isCurrent && <span className="lm-shimmer absolute inset-y-0 w-1/2" />}
                        </div>
                    );
                })}
            </div>
            <div className="mt-1 flex gap-1">
                {batch.stages.map((s, i) => (
                    <span
                        key={s}
                        className={
                            'flex-1 truncate text-center text-[9.5px] font-semibold tracking-wide uppercase ' +
                            (i === current ? '' : 'text-muted-foreground/60')
                        }
                        style={i === current ? { color: color.fg } : undefined}
                    >
                        {SHORT_STAGE[s] ?? s}
                    </span>
                ))}
            </div>
        </div>
    );
}

function BatchRow({ batch, compact, linkable }: { batch: LineBatch; compact?: boolean; linkable: boolean }) {
    const idle = idleMinutes(batch.last_activity_at);
    const h = health(idle);
    const color = stageColor(batch.stage);

    return (
        <BatchFrame
            href={linkable ? route('batches.show', batch.id) : null}
            className="border-border-soft relative block overflow-hidden rounded-2xl border bg-white/60 p-3 pl-4 transition-colors dark:bg-white/5"
        >
            <span className="absolute inset-y-0 left-0 w-1" style={{ backgroundColor: color.fg }} />
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-mono text-[15px] font-bold tracking-tight">{batch.no_batch}</p>
                    <p className="mt-0.5 line-clamp-2 text-[12.5px] leading-snug font-semibold">{batch.product_name ?? '-'}</p>
                </div>
                <span
                    className="shrink-0 rounded-full px-2.5 py-1 text-[10.5px] font-bold whitespace-nowrap"
                    style={{ backgroundColor: color.bg, color: color.fg }}
                >
                    {batch.stage_label}
                </span>
            </div>

            <div className="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-2 text-[11.5px]">
                <span>FG {batch.fg_code ?? '-'}</span>
                {batch.flow && <span className="bg-muted text-muted-foreground rounded-md px-1.5 py-px text-[10px] font-semibold">{batch.flow}</span>}
            </div>

            {!compact && batch.stages.length > 0 && <StageTrack batch={batch} />}

            <div className="mt-2.5 flex items-center justify-between gap-2 text-[12px]">
                <span className="flex min-w-0 items-center gap-1.5 font-semibold">
                    <User className="size-3.5 shrink-0" />
                    <span className="truncate">{batch.pic ?? '-'}</span>
                </span>
                <span
                    className={
                        'flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold ' +
                        (h === 'stuck' ? 'bg-red-50 text-red-700' : h === 'stale' ? 'bg-amber-50 text-amber-700' : 'text-muted-foreground')
                    }
                    title="Aktivitas terakhir"
                >
                    {h !== 'ok' && <AlertTriangle className="size-3" />}
                    {formatIdle(idle)}
                </span>
            </div>
        </BatchFrame>
    );
}

function BatchFrame({ href, className, children }: { href: string | null; className: string; children: ReactNode }) {
    return href ? (
        <a href={href} className={className + ' hover:bg-muted/40'}>
            {children}
        </a>
    ) : (
        <div className={className}>{children}</div>
    );
}

function LinkActions({ line }: { line: Line }) {
    const [copied, copy] = useCopied();
    if (!line.public_url || !line.qr_url) return null;
    const publicUrl = line.public_url;

    const btn = 'text-muted-foreground hover:bg-muted hover:text-foreground flex size-8 items-center justify-center rounded-lg transition-colors';

    return (
        <div className="flex shrink-0 items-center">
            <a href={line.qr_url} target="_blank" rel="noreferrer" className={btn} title="Poster QR" aria-label="Poster QR">
                <QrCode className="size-4" />
            </a>
            <a
                href={publicUrl}
                target="_blank"
                rel="noreferrer"
                className={btn}
                title="Buka halaman publik (tanpa login)"
                aria-label="Buka halaman publik"
            >
                <ExternalLink className="size-4" />
            </a>
            <button
                type="button"
                onClick={() => copy(publicUrl)}
                className={btn}
                title={copied ? 'Tersalin' : 'Salin URL publik'}
                aria-label="Salin URL publik"
            >
                {copied ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
            </button>
        </div>
    );
}

function LineCard({ line, linkable }: { line: Line; linkable: boolean }) {
    const lead = line.batches[0];
    const accent = lead ? stageColor(lead.stage).fg : '#A1A1AA';
    const worst = line.batches.reduce<'ok' | 'stale' | 'stuck'>((acc, b) => {
        const h = health(idleMinutes(b.last_activity_at));
        return h === 'stuck' || acc === 'stuck' ? 'stuck' : h === 'stale' || acc === 'stale' ? 'stale' : 'ok';
    }, 'ok');
    const shown = line.batches.slice(0, MAX_BATCHES_PER_CARD);
    const hidden = line.batches.length - shown.length;

    return (
        <div className="lm-card border-border-soft bg-card relative flex break-inside-avoid flex-col gap-2.5 overflow-hidden rounded-[22px] border p-4 pt-5 shadow-sm">
            <span className="absolute inset-x-0 top-0 h-1.5" style={{ backgroundColor: accent }} />
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <p className="truncate text-[18px] font-extrabold tracking-tight">{line.name}</p>
                        <span className="bg-foreground text-background rounded-md px-1.5 py-0.5 text-[10.5px] font-bold">{line.batches.length}</span>
                        {worst !== 'ok' && (
                            <AlertTriangle
                                className={'size-4 shrink-0 ' + (worst === 'stuck' ? 'text-red-600' : 'text-amber-600')}
                                aria-label="Ada batch tanpa aktivitas"
                            />
                        )}
                    </div>
                    <p className="text-muted-foreground/80 mt-0.5 truncate text-[11.5px] font-medium">
                        {line.code} &middot; {line.category} &middot; {line.area}
                    </p>
                </div>
                <LinkActions line={line} />
            </div>

            {shown.map((b, i) => (
                <BatchRow key={b.id} batch={b} compact={i > 0} linkable={linkable} />
            ))}

            {hidden > 0 && <p className="text-muted-foreground text-center text-[11.5px] font-semibold">+{hidden} batch lain di line ini</p>}
        </div>
    );
}

/** Share the whole board publicly: link to copy, QR to scan, printable poster, regenerate (admin). */
function ShareBoard({ share }: { share: ShareInfo }) {
    const [copied, copy] = useCopied();

    const regenerate = () => {
        if (!window.confirm('Buat link baru? Link dan QR lama (termasuk yang sudah dicetak) tidak bisa dipakai lagi.')) return;
        router.post(route('line-monitor.regenerate-token'), {}, { preserveScroll: true });
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="border-border-soft bg-card text-foreground hover:bg-muted flex h-10 items-center gap-2 rounded-xl border px-3 text-[12.5px] font-bold transition-colors"
                >
                    <Share2 className="size-4" /> Bagikan
                </button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Bagikan Monitor Line</DialogTitle>
                    <DialogDescription>
                        Layar ini bisa dibuka tanpa login lewat link atau QR di bawah. Hanya bisa dibuka dari jaringan kantor.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col items-center gap-2">
                    <div
                        className="rounded-2xl border bg-white p-3 [&_svg]:size-[200px]"
                        aria-label="QR Monitor Line"
                        dangerouslySetInnerHTML={{ __html: share.qr_svg }}
                    />
                    <p className="text-muted-foreground text-center text-[12px]">Scan dengan kamera HP / tablet</p>
                </div>

                <div className="flex items-center gap-2">
                    <input
                        readOnly
                        value={share.url}
                        onFocus={(e) => e.currentTarget.select()}
                        className="border-border-soft bg-muted/40 h-10 min-w-0 flex-1 rounded-xl border px-3 font-mono text-[12px]"
                    />
                    <button
                        type="button"
                        onClick={() => copy(share.url)}
                        className="bg-foreground text-background flex h-10 shrink-0 items-center gap-1.5 rounded-xl px-3 text-[12.5px] font-bold"
                    >
                        {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                        {copied ? 'Tersalin' : 'Salin'}
                    </button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <a
                        href={share.url}
                        target="_blank"
                        rel="noreferrer"
                        className="border-border-soft hover:bg-muted flex h-9 items-center gap-1.5 rounded-xl border px-3 text-[12.5px] font-semibold"
                    >
                        <ExternalLink className="size-4" /> Buka tanpa login
                    </a>
                    <a
                        href={share.qr_url}
                        target="_blank"
                        rel="noreferrer"
                        className="border-border-soft hover:bg-muted flex h-9 items-center gap-1.5 rounded-xl border px-3 text-[12.5px] font-semibold"
                    >
                        <QrCode className="size-4" /> Poster QR (cetak)
                    </a>
                    {share.can_regenerate && (
                        <button
                            type="button"
                            onClick={regenerate}
                            className="ml-auto flex h-9 items-center gap-1.5 rounded-xl border border-red-200 px-3 text-[12.5px] font-semibold text-red-700 hover:bg-red-50"
                        >
                            <RefreshCw className="size-4" /> Buat link baru
                        </button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

/** Full-screen frame for the public (no login) board: no navigation, nothing that needs a session. */
function PublicFrame({ header, children }: { title: string; header: ReactNode; children: ReactNode }) {
    return (
        <div className="bg-background flex h-screen flex-col supports-[height:100dvh]:h-dvh">
            {header}
            {children}
        </div>
    );
}

export default function LineMonitor({ lines, share, publicMode = false }: { lines: Line[]; share?: ShareInfo; publicMode?: boolean }) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const [autoScroll, setAutoScroll] = useState(() => {
        const reduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        return readPref('lm.autoscroll', !reduced);
    });
    const [stageFilter, setStageFilter] = useState<string | null>(null);
    const [fullscreen, setFullscreen] = useState(false);
    const [lastSync, setLastSync] = useState(() => Date.now());

    // Keep the board fresh without a manual reload (doesn't remount, so the scroll position stays).
    useEffect(() => {
        const timer = setInterval(() => {
            router.reload({ only: ['lines'], onFinish: () => setLastSync(Date.now()) });
        }, REFRESH_MS);
        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        const onChange = () => setFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', onChange);
        return () => document.removeEventListener('fullscreenchange', onChange);
    }, []);

    const toggleFullscreen = () => {
        if (document.fullscreenElement) {
            document.exitFullscreen?.().catch(() => {});
        } else {
            document.documentElement.requestFullscreen?.().catch(() => {});
        }
    };

    const toggleAutoScroll = () => {
        setAutoScroll((v) => {
            writePref('lm.autoscroll', !v);
            return !v;
        });
    };

    const [speed, setSpeed] = useState<ScrollSpeed>(() => {
        try {
            const v = window.localStorage.getItem('lm.speed');
            return SCROLL_SPEEDS.some((s) => s.key === v) ? (v as ScrollSpeed) : 'normal';
        } catch {
            return 'normal';
        }
    });
    const changeSpeed = (key: ScrollSpeed) => {
        setSpeed(key);
        try {
            window.localStorage.setItem('lm.speed', key);
        } catch {
            // storage blocked: the choice just isn't remembered
        }
    };
    const pxPerSec = SCROLL_SPEEDS.find((s) => s.key === speed)?.pxPerSec ?? 34;

    const progress = useAutoScroll(scrollRef, autoScroll, pxPerSec);

    const stats = useMemo(() => {
        const byStage: Record<string, number> = {};
        let batches = 0;
        let needsAttention = 0;
        for (const l of lines) {
            for (const b of l.batches) {
                batches++;
                byStage[b.stage] = (byStage[b.stage] ?? 0) + 1;
                if (health(idleMinutes(b.last_activity_at)) !== 'ok') needsAttention++;
            }
        }
        return { byStage, batches, needsAttention, running: lines.filter((l) => l.batches.length > 0).length };
    }, [lines]);

    const visible = useMemo(() => {
        const running = lines.filter((l) => l.batches.length > 0);
        if (!stageFilter) return running;
        return running.map((l) => ({ ...l, batches: l.batches.filter((b) => b.stage === stageFilter) })).filter((l) => l.batches.length > 0);
    }, [lines, stageFilter]);

    const idleLines = useMemo(() => lines.filter((l) => l.batches.length === 0), [lines]);
    const stagesPresent = STAGE_ORDER.filter((s) => stats.byStage[s]);

    const Frame = publicMode ? PublicFrame : IpcShell;

    const header = (
        <header className="border-border-soft bg-card flex shrink-0 flex-wrap items-center gap-3 border-b px-5 py-3 md:px-6">
            {!publicMode && (
                <Link
                    href="/dashboard"
                    aria-label="Kembali"
                    className="border-border-soft bg-card text-foreground flex size-11 shrink-0 items-center justify-center rounded-2xl border"
                >
                    <ArrowLeft className="size-4.5" strokeWidth={2.2} />
                </Link>
            )}
            <div className="min-w-0 flex-1">
                <h1 className="flex items-center gap-2 truncate text-[20px] font-extrabold tracking-tight">
                    <Radio className="size-5 text-red-600" /> Monitor Line
                </h1>
                <p className="text-muted-foreground/80 mt-0.5 truncate text-[12px] font-medium">Status produksi per line, diperbarui otomatis</p>
            </div>
            <div className="flex items-center gap-2.5">
                <LiveBadge lastSync={lastSync} />
                {share && <ShareBoard share={share} />}
                <IconToggle onClick={toggleAutoScroll} title={autoScroll ? 'Hentikan auto-scroll' : 'Jalankan auto-scroll'} active={autoScroll}>
                    {autoScroll ? <Pause className="size-4" /> : <Play className="size-4" />}
                </IconToggle>
                <div
                    className="border-border-soft bg-card flex h-10 items-center rounded-xl border p-1"
                    role="group"
                    aria-label="Kecepatan auto-scroll"
                >
                    {SCROLL_SPEEDS.map((s) => (
                        <button
                            key={s.key}
                            type="button"
                            onClick={() => changeSpeed(s.key)}
                            aria-pressed={speed === s.key}
                            disabled={!autoScroll}
                            className={
                                'h-full rounded-lg px-2.5 text-[11.5px] font-bold transition-colors disabled:opacity-40 ' +
                                (speed === s.key ? 'bg-foreground text-background' : 'text-muted-foreground hover:bg-muted')
                            }
                        >
                            {s.label}
                        </button>
                    ))}
                </div>
                <IconToggle onClick={toggleFullscreen} title={fullscreen ? 'Keluar layar penuh' : 'Layar penuh (mode TV)'} active={fullscreen}>
                    {fullscreen ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                </IconToggle>
                <div className="hidden pl-2 sm:block">
                    <Clock />
                </div>
            </div>
        </header>
    );

    return (
        <Frame title="Monitor Line" header={header}>
            <Head title="Monitor Line" />
            <style>{`
                @keyframes lm-shimmer { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
                .lm-shimmer { background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.65), rgba(255,255,255,0)); animation: lm-shimmer 2.2s ease-in-out infinite; }
                @keyframes lm-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
                .lm-card { animation: lm-in .35s ease-out both; }
                @media (prefers-reduced-motion: reduce) { .lm-shimmer, .lm-card { animation: none; } }
            `}</style>

            {/* Summary strip */}
            <div className="border-border-soft bg-card shrink-0 border-b px-5 py-3 md:px-6">
                <div className="flex flex-wrap items-stretch gap-3">
                    <div className="flex items-baseline gap-2 pr-2">
                        <span className="text-[30px] leading-none font-extrabold tabular-nums">{stats.running}</span>
                        <span className="text-muted-foreground text-[12.5px] font-semibold">
                            / {lines.length} line berjalan
                            <br />
                            {stats.batches} batch aktif
                        </span>
                    </div>
                    {stats.needsAttention > 0 && (
                        <div className="flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 text-amber-800">
                            <AlertTriangle className="size-4" />
                            <span className="text-[12.5px] font-bold">{stats.needsAttention} batch tanpa aktivitas &gt; 2 jam</span>
                        </div>
                    )}

                    <div className="ml-auto flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            onClick={() => setStageFilter(null)}
                            className={
                                'rounded-full border px-3 py-1.5 text-[12px] font-bold transition-colors ' +
                                (stageFilter === null
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border-soft text-muted-foreground hover:bg-muted')
                            }
                        >
                            Semua
                        </button>
                        {stagesPresent.map((s) => {
                            const c = stageColor(s);
                            const active = stageFilter === s;
                            return (
                                <button
                                    key={s}
                                    type="button"
                                    onClick={() => setStageFilter(active ? null : s)}
                                    className="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[12px] font-bold transition-colors"
                                    style={
                                        active
                                            ? { backgroundColor: c.fg, borderColor: c.fg, color: '#fff' }
                                            : { backgroundColor: c.bg, borderColor: c.bg, color: c.fg }
                                    }
                                >
                                    <span className="text-[14px] tabular-nums">{stats.byStage[s]}</span>
                                    {stageLabel(s)}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Stage distribution bar */}
                {stats.batches > 0 && (
                    <div className="mt-3 flex h-2 overflow-hidden rounded-full bg-zinc-100">
                        {stagesPresent.map((s) => (
                            <div
                                key={s}
                                title={`${stageLabel(s)}: ${stats.byStage[s]}`}
                                className="h-full transition-[width] duration-500"
                                style={{ width: `${(stats.byStage[s] / stats.batches) * 100}%`, backgroundColor: stageColor(s).fg }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Auto-scroll progress */}
            <div className="relative h-0.5 shrink-0 bg-transparent">
                {autoScroll && <div className="h-full bg-red-500/70 transition-[width] duration-300" style={{ width: `${progress}%` }} />}
            </div>

            <div ref={scrollRef} tabIndex={0} className="min-h-0 flex-1 overflow-y-auto p-5 outline-none md:p-6">
                <div className="gap-4 [column-fill:balance] sm:columns-2 xl:columns-3 2xl:columns-4">
                    {visible.map((line) => (
                        <div key={line.id} className="mb-4 break-inside-avoid">
                            <LineCard line={line} linkable={!publicMode} />
                        </div>
                    ))}
                </div>

                {visible.length === 0 && (
                    <p className="text-muted-foreground py-12 text-center text-sm">
                        {stageFilter ? `Tidak ada batch di tahap ${stageLabel(stageFilter)}.` : 'Belum ada line yang berjalan.'}
                    </p>
                )}

                {!stageFilter && idleLines.length > 0 && (
                    <div className="border-border-soft mt-2 rounded-[22px] border border-dashed p-4">
                        <p className="text-muted-foreground mb-2.5 flex items-center gap-2 text-[12px] font-bold tracking-wide uppercase">
                            <Moon className="size-3.5" /> Line idle ({idleLines.length})
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {idleLines.map((l) => (
                                <span
                                    key={l.id}
                                    className="bg-muted text-muted-foreground rounded-xl px-3 py-1.5 text-[12.5px] font-semibold"
                                    title={`${l.code} · ${l.area}`}
                                >
                                    {l.name}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </Frame>
    );
}
