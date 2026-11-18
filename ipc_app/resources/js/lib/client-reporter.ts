import { router } from '@inertiajs/react';

/**
 * Field-issue reporter: sends JS errors, failed/slow requests, camera/upload failures and operator
 * reports to POST /client-log so admins can see them in "Log Kendala".
 *
 * Events are queued in localStorage first and flushed when online, so a phone with a bad
 * connection (the very case worth knowing about) still reports once the signal is back.
 * Never sends form values or photos, only the message, page path and device facts.
 */
export type ClientEventKind = 'js_error' | 'promise' | 'request_failed' | 'slow_request' | 'camera' | 'upload' | 'user_report';

interface QueuedEvent {
    kind: ClientEventKind;
    message: string;
    detail?: string;
    url: string;
    online: boolean;
    net_type?: string;
    app_version?: string;
    occurred_at: string;
}

const STORAGE_KEY = 'ipc-client-events';
const MAX_QUEUE = 50;
const MAX_PER_PAGE_LOAD = 30;
const BATCH = 20;
const SLOW_MS = 8000;
const DEDUPE_MS = 30_000;
const IGNORED = [/ResizeObserver loop/i, /^Script error\.?$/i];

let appVersion: string | undefined;
let sentThisLoad = 0;
let flushing = false;
const recent = new Map<string, number>();

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function readQueue(): QueuedEvent[] {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? (JSON.parse(raw) as unknown) : [];
        return Array.isArray(parsed) ? (parsed as QueuedEvent[]) : [];
    } catch {
        return [];
    }
}

function writeQueue(queue: QueuedEvent[]) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(queue.slice(-MAX_QUEUE)));
    } catch {
        // storage blocked/full: drop silently, reporting must never break the page
    }
}

export function reportClientEvent(kind: ClientEventKind, message: string, detail?: string) {
    try {
        const text = message.trim().slice(0, 500);
        if (!text || IGNORED.some((re) => re.test(text))) return;

        const key = `${kind}|${text}`;
        const now = Date.now();
        if (now - (recent.get(key) ?? 0) < DEDUPE_MS || sentThisLoad >= MAX_PER_PAGE_LOAD) return;
        recent.set(key, now);
        sentThisLoad += 1;

        const connection = (navigator as Navigator & { connection?: { effectiveType?: string } }).connection;
        writeQueue([
            ...readQueue(),
            {
                kind,
                message: text,
                detail: detail?.slice(0, 4000),
                url: window.location.pathname,
                online: navigator.onLine,
                net_type: connection?.effectiveType,
                app_version: appVersion,
                occurred_at: new Date().toISOString(),
            },
        ]);
        void flush();
    } catch {
        // never throw from the reporter
    }
}

async function flush() {
    if (flushing || !navigator.onLine) return;
    const queue = readQueue();
    if (queue.length === 0) return;

    flushing = true;
    const sending = queue.slice(0, BATCH);
    try {
        const response = await fetch('/client-log', {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: JSON.stringify({ events: sending }),
        });
        // Server errors keep the events for a retry; anything else (OK, 419 expired session, 422) drops them
        // so a rejected batch can never be resent forever.
        if (response.status < 500) writeQueue(readQueue().slice(sending.length));
    } catch {
        // offline / network down: keep the queue, retry on the next trigger
    } finally {
        flushing = false;
    }
}

export function initClientReporter(version?: string) {
    appVersion = version;

    window.addEventListener('error', (event) => {
        // Resource load failures bubble as plain events without a message; only real script errors matter.
        if (!event.message) return;
        reportClientEvent(
            'js_error',
            event.message,
            [event.filename && `${event.filename}:${event.lineno}`, event.error?.stack].filter(Boolean).join('\n'),
        );
    });

    window.addEventListener('unhandledrejection', (event) => {
        const reason = event.reason as { message?: string; stack?: string } | string | undefined;
        const message = typeof reason === 'string' ? reason : (reason?.message ?? 'Promise ditolak tanpa pesan');
        reportClientEvent('promise', message, typeof reason === 'object' ? reason?.stack : undefined);
    });

    router.on('invalid', (event) => {
        reportClientEvent('request_failed', `HTTP ${event.detail.response.status}: server membalas bukan halaman aplikasi`);
    });
    router.on('exception', (event) => {
        reportClientEvent('request_failed', `Request gagal: ${event.detail.exception?.message ?? 'tanpa pesan'}`);
    });

    let slowTimer: number | undefined;
    router.on('start', (event) => {
        window.clearTimeout(slowTimer);
        const path = event.detail.visit.url.pathname;
        slowTimer = window.setTimeout(
            () =>
                reportClientEvent(
                    'slow_request',
                    `Request lebih dari ${SLOW_MS / 1000} detik tanpa respon: ${event.detail.visit.method.toUpperCase()} ${path}`,
                ),
            SLOW_MS,
        );
    });
    router.on('finish', () => window.clearTimeout(slowTimer));

    window.addEventListener('online', () => void flush());
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && void flush());
    window.setInterval(() => void flush(), 60_000);
    void flush();
}
