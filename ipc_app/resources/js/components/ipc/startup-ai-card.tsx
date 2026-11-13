import { Loader2, Sparkles } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Verdict = 'match' | 'mismatch' | 'review' | 'info';

type AiItem = {
    key: string;
    label: string;
    expected: string | null;
    read: string | null;
    verdict: Verdict;
    note: string | null;
};

type FieldStatus = {
    field: string;
    label: string;
    state: 'none' | 'queued' | 'running' | 'done';
    decision?: 'PASS' | 'FAIL' | 'REVIEW';
    reason?: string;
    items?: AiItem[];
};

type Status = { pending: boolean; fields: FieldStatus[]; notes: string[] };

type Entered = { mixing_date?: string; exp_date?: string; shelf_life_months?: number; packaging_type?: string };

const DECISION_STYLE = {
    PASS: { label: 'Sesuai', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' },
    FAIL: { label: 'Tidak sesuai', className: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' },
    REVIEW: { label: 'Perlu dicek manual', className: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' },
} as const;

const VERDICT_TEXT: Record<Verdict, string> = { match: 'Sesuai', mismatch: 'Beda', review: 'Cek manual', info: 'Info' };

const POLL_MS = 3000;

// JSON endpoints (not Inertia visits), so the CSRF token comes from Laravel's XSRF-TOKEN cookie.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function query(entered: Entered): string {
    const params = new URLSearchParams();
    Object.entries(entered).forEach(([k, v]) => v !== undefined && v !== '' && params.set(k, String(v)));
    return params.toString();
}

/**
 * "Rekomendasi AI": the Startup photos are read in the background (server-side queue, one request at
 * a time on the AI server) as soon as they are saved, and this card shows what the AI makes of them
 * against the form values typed so far. ADVISORY ONLY: it never blocks saving and never fills a
 * checklist value; the decision logic lives server-side (StartupVisionEvaluator).
 */
export function StartupAiCard({
    batchId,
    readOnly,
    photoKey,
    getEntered,
}: {
    batchId: number;
    readOnly: boolean;
    /** Changes whenever an AI-read photo is added/replaced, so the reads get queued. */
    photoKey: string;
    getEntered: () => Entered;
}) {
    const [status, setStatus] = useState<Status | null>(null);
    const [error, setError] = useState(false);
    const entered = getEntered();
    const enteredKey = query(entered);
    const enteredRef = useRef(entered);
    enteredRef.current = entered;

    const call = useCallback(
        async (enqueue: boolean) => {
            try {
                const base = `/batches/${batchId}/startup-check`;
                const response = enqueue
                    ? await fetch(`${base}/ai-queue`, {
                          method: 'POST',
                          credentials: 'same-origin',
                          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                          body: JSON.stringify(enteredRef.current),
                      })
                    : await fetch(`${base}/ai-status?${query(enteredRef.current)}`, {
                          credentials: 'same-origin',
                          headers: { Accept: 'application/json' },
                      });
                if (!response.ok) throw new Error(String(response.status));
                setStatus((await response.json()) as Status);
                setError(false);
            } catch {
                setError(true);
            }
        },
        [batchId],
    );

    // A photo or the packaging type changed: queue whatever is missing (idempotent on the server).
    useEffect(() => {
        void call(!readOnly);
    }, [photoKey, entered.packaging_type, readOnly, call]);

    // The typed dates changed: re-evaluate from the stored reads (no new AI call).
    useEffect(() => {
        const timer = setTimeout(() => void call(false), 700);
        return () => clearTimeout(timer);
    }, [enteredKey, call]);

    // Poll while reads are waiting / running.
    useEffect(() => {
        if (!status?.pending) return;
        const timer = setInterval(() => void call(false), POLL_MS);
        return () => clearInterval(timer);
    }, [status?.pending, call]);

    // A finished (read-only) Startup has no way to queue a read, so a photo that was never read is just hidden.
    const fields = status?.fields.filter((f) => !(readOnly && f.state === 'none')) ?? [];

    // Always visible once the feature is on, so "no card" never looks like "broken": say why it is empty.
    if (!status || fields.length === 0) {
        const message = error
            ? 'Rekomendasi AI sementara tidak bisa dimuat. Isian tetap bisa disimpan.'
            : !status
              ? 'Memuat rekomendasi AI...'
              : 'Belum ada foto Startup yang bisa dibaca AI. Ambil foto Label Bulk, IM Number, atau Primary Coding di Startup Check, hasilnya muncul di sini.';

        return (
            <div className="border-border col-span-full flex flex-col gap-2 rounded-2xl border p-4">
                <div className="flex items-center gap-2">
                    <Sparkles className="size-4" strokeWidth={2.2} />
                    <h3 className="text-[14px] font-bold">Rekomendasi AI</h3>
                </div>
                <p className="text-muted-foreground text-[12px]">{message}</p>
            </div>
        );
    }

    return (
        <div className="border-border col-span-full flex flex-col gap-3 rounded-2xl border p-4">
            <div className="flex items-center gap-2">
                <Sparkles className="size-4" strokeWidth={2.2} />
                <h3 className="text-[14px] font-bold">Rekomendasi AI</h3>
                {status.pending && (
                    <span className="text-muted-foreground flex items-center gap-1 text-[11.5px]">
                        <Loader2 className="size-3 animate-spin" /> sedang membaca foto, form tetap bisa diisi
                    </span>
                )}
            </div>

            {fields.map((f) => (
                <div key={f.field} className="border-border flex flex-col gap-2 rounded-xl border p-3 text-[12.5px]">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-bold">{f.label}</span>
                        {f.state === 'done' && f.decision && (
                            <span className={`rounded-full px-2.5 py-0.5 text-[12px] font-bold ${DECISION_STYLE[f.decision].className}`}>
                                {DECISION_STYLE[f.decision].label}
                            </span>
                        )}
                        {f.state === 'queued' && <span className="text-muted-foreground">Menunggu antrean AI...</span>}
                        {f.state === 'running' && (
                            <span className="text-muted-foreground flex items-center gap-1">
                                <Loader2 className="size-3 animate-spin" /> Membaca foto...
                            </span>
                        )}
                        {f.state === 'none' && (
                            <span className="text-muted-foreground">
                                Belum terbaca.{' '}
                                {!readOnly && (
                                    <button type="button" onClick={() => void call(true)} className="font-semibold underline">
                                        Coba lagi
                                    </button>
                                )}
                            </span>
                        )}
                        {f.state === 'done' && f.reason && <span className="text-muted-foreground">{f.reason}</span>}
                    </div>
                    {f.state === 'done' && f.items && f.items.length > 0 && (
                        <table className="w-full text-left">
                            <thead>
                                <tr className="text-muted-foreground text-[11px]">
                                    <th className="pr-2 font-semibold">Item</th>
                                    <th className="pr-2 font-semibold">Diharapkan</th>
                                    <th className="pr-2 font-semibold">Terbaca</th>
                                    <th className="font-semibold">Hasil</th>
                                </tr>
                            </thead>
                            <tbody>
                                {f.items.map((item) => (
                                    <tr key={item.key} className="border-border border-t align-top">
                                        <td className="py-1 pr-2 font-semibold">{item.label}</td>
                                        <td className="py-1 pr-2 break-all">{item.expected ?? '-'}</td>
                                        <td className="py-1 pr-2 break-all">{item.read ?? '-'}</td>
                                        <td className="py-1">
                                            {VERDICT_TEXT[item.verdict]}
                                            {item.note && <span className="text-muted-foreground block text-[11px]">{item.note}</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            ))}

            {status.notes.map((note) => (
                <p key={note} className="text-[12px] font-semibold">
                    {note}
                </p>
            ))}
            <p className="text-muted-foreground text-[11px]">
                Hanya saran dari AI - tidak mengubah isian atau menghalangi Simpan & Lanjut. Pastikan dengan mata sendiri.
            </p>
        </div>
    );
}
