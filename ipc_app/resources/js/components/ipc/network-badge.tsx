import { useNetworkStatus } from '@/lib/network-status';
import { Wifi, WifiOff } from 'lucide-react';

/** Renders nothing while the network is healthy; a slim strip only when offline or slow. */
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
