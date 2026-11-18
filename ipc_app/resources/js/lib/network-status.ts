import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

/**
 * Lightweight network health for the navbar badge. Passive only: it never makes a request of its own,
 * it just watches browser online/offline events, the Network Information API (where it exists) and
 * how long the app's own Inertia requests take. Listeners are attached once, on first use, and the store only notifies subscribers when the status actually changes,
 * so a healthy connection causes zero re-renders.
 */
export type NetworkStatus = 'ok' | 'slow' | 'offline';

const SLOW_MS = 6_000; // a normal page/save request still pending after this = slow network
const SLOW_UPLOAD_MS = 25_000; // photo uploads are legitimately bigger
const FAST_MS = 3_000; // a request finishing this quickly proves the network is fine again
const SLOW_HOLD_MS = 60_000; // without further evidence, forget "slow" after a minute

let status: NetworkStatus = typeof navigator !== 'undefined' && navigator.onLine === false ? 'offline' : 'ok';
let started = false;
let holdTimer: number | undefined;
const listeners = new Set<() => void>();

function set(next: NetworkStatus) {
    if (next === status) return;
    status = next;
    listeners.forEach((listener) => listener());
}

function markSlow() {
    if (status === 'offline') return;
    set('slow');
    window.clearTimeout(holdTimer);
    holdTimer = window.setTimeout(() => status === 'slow' && set('ok'), SLOW_HOLD_MS);
}

function markOk() {
    window.clearTimeout(holdTimer);
    set(navigator.onLine === false ? 'offline' : 'ok');
}

function start() {
    if (started || typeof window === 'undefined') return;
    started = true;

    window.addEventListener('offline', () => {
        window.clearTimeout(holdTimer);
        set('offline');
    });
    window.addEventListener('online', markOk);

    const connection = (navigator as Navigator & { connection?: EventTarget & { effectiveType?: string } }).connection;
    connection?.addEventListener?.('change', () => {
        if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') markSlow();
        else if (status === 'slow') markOk();
    });

    let slowTimer: number | undefined;
    let startedAt = 0;
    router.on('start', (event) => {
        window.clearTimeout(slowTimer);
        startedAt = Date.now();
        const upload = Boolean(event.detail.visit.forceFormData);
        slowTimer = window.setTimeout(markSlow, upload ? SLOW_UPLOAD_MS : SLOW_MS);
    });
    router.on('finish', () => {
        window.clearTimeout(slowTimer);
        if (startedAt && Date.now() - startedAt < FAST_MS && status === 'slow') markOk();
    });
    router.on('exception', () => {
        if (navigator.onLine === false) set('offline');
    });
}

function subscribe(listener: () => void) {
    start();
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
}

export function useNetworkStatus(): NetworkStatus {
    return useSyncExternalStore(
        subscribe,
        () => status,
        () => 'ok' as NetworkStatus,
    );
}
