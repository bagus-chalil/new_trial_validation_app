import { usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

const POLL_MS = 5 * 60 * 1000;

/**
 * Shows a "new version available" banner once the server's Inertia asset version
 * differs from the one this tab booted with. Checked when the tab becomes visible
 * again (tablets keep tabs open for days) and every few minutes.
 *
 * Never reloads on its own: the user may be mid-form, so they choose when.
 */
export function VersionWatcher() {
    const { version } = usePage();
    const [stale, setStale] = useState(false);

    useEffect(() => {
        if (!version || stale) return;

        let busy = false;
        const check = () => {
            if (busy || document.visibilityState !== 'visible') return;
            busy = true;
            fetch('/app-version', { cache: 'no-store', headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : null))
                .then((data: { version?: string | null } | null) => {
                    if (data?.version && data.version !== version) setStale(true);
                })
                .catch(() => {})
                .finally(() => {
                    busy = false;
                });
        };

        const timer = window.setInterval(check, POLL_MS);
        document.addEventListener('visibilitychange', check);
        window.addEventListener('focus', check);
        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', check);
            window.removeEventListener('focus', check);
        };
    }, [version, stale]);

    if (!stale) return null;

    return (
        <div className="mx-5 mb-2 flex shrink-0 items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm text-blue-900 md:mx-6">
            <span className="min-w-0 flex-1 font-medium">Versi baru aplikasi tersedia. Simpan isian dulu, lalu muat ulang.</span>
            <button
                type="button"
                onClick={() => window.location.reload()}
                className="flex shrink-0 items-center gap-1.5 rounded-xl bg-blue-600 px-3 py-2 text-[13px] font-semibold text-white"
            >
                <RefreshCw className="size-4" />
                Muat ulang
            </button>
        </div>
    );
}
