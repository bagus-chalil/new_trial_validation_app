import { type NetworkStatus, useActivity, useNetworkStatus } from '@/lib/network-status';
import { cn } from '@/lib/utils';
import { Loader2, Wifi, WifiOff } from 'lucide-react';

const LABEL: Record<NetworkStatus, string> = { ok: 'Online', slow: 'Lambat', offline: 'Offline' };
const TITLE: Record<NetworkStatus, string> = {
    ok: 'Jaringan normal',
    slow: 'Jaringan lambat: tunggu proses simpan atau upload selesai',
    offline: 'Tidak ada koneksi: data yang belum disimpan belum terkirim',
};

/** Always-visible status dot + label. Re-renders only when the status itself changes. */
export function NetworkDot({ className }: { className?: string }) {
    const status = useNetworkStatus();
    const color = status === 'ok' ? 'bg-emerald-500' : status === 'slow' ? 'bg-amber-500' : 'bg-red-500';
    const text = status === 'ok' ? 'text-muted-foreground' : status === 'slow' ? 'text-amber-700' : 'text-red-700';
    return (
        <span role="status" title={TITLE[status]} className={cn('inline-flex items-center gap-1.5 text-[10px] font-semibold', text, className)}>
            <span className={cn('size-2 shrink-0 rounded-full', color, status !== 'ok' && 'animate-pulse')} />
            {LABEL[status]}
        </span>
    );
}

/** Warning strip, only while the network is offline or slow. */
export function NetworkBadge() {
    const status = useNetworkStatus();
    if (status === 'ok') return null;

    const offline = status === 'offline';
    return (
        <div
            role="status"
            aria-live="polite"
            className={`mx-5 mb-1 flex shrink-0 items-center gap-2 rounded-2xl border px-4 py-2 text-[13px] font-semibold md:mx-6 ${
                offline ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900'
            }`}
        >
            {offline ? <WifiOff className="size-4 shrink-0" /> : <Wifi className="size-4 shrink-0" />}
            {offline
                ? 'Tidak ada koneksi. Data yang belum disimpan belum terkirim, jangan tutup halaman.'
                : 'Jaringan lambat. Tunggu proses simpan atau upload selesai, jangan tekan berulang kali.'}
        </div>
    );
}

function mb(bytes: number) {
    return (bytes / 1024 / 1024).toFixed(1);
}

/**
 * Spinner pill while a request is running (photo uploads show percentage + size).
 * `pointer-events-none` on purpose: it must never swallow taps on the old field tablets.
 */
export function ActivityIndicator() {
    const activity = useActivity();
    if (activity.kind === 'idle') return null;

    return (
        <div className="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" role="status" aria-live="polite">
            <div className="bg-card border-border flex max-w-sm min-w-[220px] flex-col gap-1.5 rounded-2xl border px-4 py-2.5 shadow-lg">
                <div className="flex items-center gap-2.5 text-[13px] font-semibold">
                    <Loader2 className="text-primary size-4 shrink-0 animate-spin" />
                    {activity.kind === 'upload' ? 'Mengunggah foto, jangan tutup halaman…' : 'Memproses…'}
                </div>
                {activity.kind === 'upload' && (
                    <>
                        <div className="bg-muted h-1.5 overflow-hidden rounded-full">
                            <div className="bg-primary h-full rounded-full transition-[width] duration-150" style={{ width: `${activity.pct}%` }} />
                        </div>
                        <p className="text-muted-foreground text-[11px] font-medium">
                            {activity.pct}%{activity.total > 0 && ` · ${mb(activity.loaded)} / ${mb(activity.total)} MB`}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}
