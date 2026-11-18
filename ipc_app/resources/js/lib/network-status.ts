import { reportClientEvent } from '@/lib/client-reporter';
import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

/**
 * Lightweight network + activity state for the navbar. Passive only: it never makes a request of its own,
 * it watches browser online/offline events, the Network Information API (where it exists) and the app's
 * own Inertia requests. Listeners are attached once, on first use.
 *
 * Two separate stores so a component only re-renders for what it shows:
 *  - network status (ok / slow / offline): changes rarely, drives the always-visible dot and the warning strip;
 *  - activity (idle / loading / upload with progress): drives the spinner pill; only active while a request runs.
 * A healthy, idle app causes zero re-renders.
 */
export type NetworkStatus = 'ok' | 'slow' | 'offline';
export type Activity = { kind: 'idle' } | { kind: 'loading' } | { kind: 'upload'; pct: number; loaded: number; total: number };

const SLOW_MS = 6_000; // a normal page/save request still pending after this = slow network
const SLOW_UPLOAD_MS = 25_000; // photo uploads are legitimately bigger
const FAST_MS = 3_000; // a request finishing this quickly proves the network is fine again
const SLOW_HOLD_MS = 60_000; // without further evidence, forget "slow" after a minute
const SPINNER_DELAY_MS = 400; // quick requests never flash a spinner
const REPORT_UPLOAD_MS = 10_000; // slower uploads are written to Log Kendala with size + duration

type NetInfo = { effectiveType?: string };
const connectionOf = () => (navigator as Navigator & { connection?: EventTarget & NetInfo }).connection;

let status: NetworkStatus = typeof navigator !== 'undefined' && navigator.onLine === false ? 'offline' : 'ok';
let activity: Activity = { kind: 'idle' };
let started = false;
let holdTimer: number | undefined;
const statusListeners = new Set<() => void>();
const activityListeners = new Set<() => void>();

function setStatus(next: NetworkStatus) {
    if (next === status) return;
    status = next;
    statusListeners.forEach((listener) => listener());
}

function setActivity(next: Activity) {
    if (next.kind === activity.kind && (next.kind !== 'upload' || (activity.kind === 'upload' && activity.pct === next.pct))) return;
    activity = next;
    activityListeners.forEach((listener) => listener());
}

function markSlow() {
    if (status === 'offline') return;
    setStatus('slow');
    window.clearTimeout(holdTimer);
    holdTimer = window.setTimeout(() => status === 'slow' && setStatus('ok'), SLOW_HOLD_MS);
}

function markOk() {
    window.clearTimeout(holdTimer);
    setStatus(navigator.onLine === false ? 'offline' : 'ok');
}

function formatMb(bytes: number) {
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function start() {
    if (started || typeof window === 'undefined') return;
    started = true;

    window.addEventListener('offline', () => {
        window.clearTimeout(holdTimer);
        setStatus('offline');
    });
    window.addEventListener('online', markOk);

    const connection = connectionOf();
    connection?.addEventListener?.('change', () => {
        if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') markSlow();
        else if (status === 'slow') markOk();
    });

    let slowTimer: number | undefined;
    let spinnerTimer: number | undefined;
    let startedAt = 0;
    let isUpload = false;
    let lastTotal = 0;

    router.on('start', (event) => {
        window.clearTimeout(slowTimer);
        window.clearTimeout(spinnerTimer);
        startedAt = Date.now();
        isUpload = Boolean(event.detail.visit.forceFormData);
        lastTotal = 0;
        slowTimer = window.setTimeout(markSlow, isUpload ? SLOW_UPLOAD_MS : SLOW_MS);
        // An upload shows its progress right away; any other request only gets a spinner if it is not instant.
        if (isUpload) setActivity({ kind: 'upload', pct: 0, loaded: 0, total: 0 });
        else spinnerTimer = window.setTimeout(() => setActivity({ kind: 'loading' }), SPINNER_DELAY_MS);
    });

    router.on('progress', (event) => {
        const progress = event.detail.progress;
        if (!progress || !isUpload) return;
        lastTotal = progress.total ?? lastTotal;
        setActivity({ kind: 'upload', pct: Math.round(progress.percentage ?? 0), loaded: progress.loaded ?? 0, total: progress.total ?? 0 });
    });

    router.on('finish', () => {
        window.clearTimeout(slowTimer);
        window.clearTimeout(spinnerTimer);
        const elapsed = startedAt ? Date.now() - startedAt : 0;
        if (isUpload && elapsed > REPORT_UPLOAD_MS) {
            reportClientEvent(
                'upload',
                'Upload foto lambat',
                `ukuran=${lastTotal ? formatMb(lastTotal) : '?'} durasi=${(elapsed / 1000).toFixed(1)}dtk koneksi=${connectionOf()?.effectiveType ?? '?'}`,
            );
        }
        if (startedAt && elapsed < FAST_MS && status === 'slow') markOk();
        startedAt = 0;
        isUpload = false;
        setActivity({ kind: 'idle' });
    });

    router.on('exception', () => {
        if (navigator.onLine === false) setStatus('offline');
    });
}

function subscribeStatus(listener: () => void) {
    start();
    statusListeners.add(listener);
    return () => {
        statusListeners.delete(listener);
    };
}

function subscribeActivity(listener: () => void) {
    start();
    activityListeners.add(listener);
    return () => {
        activityListeners.delete(listener);
    };
}

export function useNetworkStatus(): NetworkStatus {
    return useSyncExternalStore(
        subscribeStatus,
        () => status,
        () => 'ok' as NetworkStatus,
    );
}

export function useActivity(): Activity {
    return useSyncExternalStore(
        subscribeActivity,
        () => activity,
        () => ({ kind: 'idle' }) as Activity,
    );
}
