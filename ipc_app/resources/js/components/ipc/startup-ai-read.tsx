import { Loader2, ScanSearch } from 'lucide-react';
import { useState } from 'react';

type Verdict = 'match' | 'mismatch' | 'review' | 'info';

type AiItem = {
    key: string;
    label: string;
    expected: string | null;
    read: string | null;
    verdict: Verdict;
    note: string | null;
};

type AiResult = {
    decision: 'PASS' | 'FAIL' | 'REVIEW';
    reason: string;
    items: AiItem[];
};

const DECISION_STYLE: Record<AiResult['decision'], { label: string; className: string }> = {
    PASS: { label: 'Sesuai', className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' },
    FAIL: { label: 'Tidak sesuai', className: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' },
    REVIEW: { label: 'Perlu dicek manual', className: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200' },
};

const VERDICT_TEXT: Record<Verdict, string> = {
    match: 'Sesuai',
    mismatch: 'Beda',
    review: 'Cek manual',
    info: 'Info',
};

// JSON endpoint (not an Inertia visit), so the CSRF token comes from Laravel's XSRF-TOKEN cookie.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * "Baca AI" next to a saved Startup Check photo. ADVISORY ONLY: it shows what the Vision
 * Service read versus the form / master data. It never blocks saving and never fills a
 * checklist toggle (the decision logic lives server-side in StartupVisionEvaluator).
 */
export function StartupAiRead({
    batchId,
    field,
    hasPhoto,
    getEntered,
}: {
    batchId: number;
    field: string;
    hasPhoto: boolean;
    getEntered: () => { mixing_date?: string; exp_date?: string };
}) {
    const [processing, setProcessing] = useState(false);
    const [result, setResult] = useState<AiResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const run = async () => {
        setProcessing(true);
        setError(null);
        setResult(null);
        try {
            const response = await fetch(`/batches/${batchId}/startup-check/ai-read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ field, ...getEntered() }),
            });
            const data = await response.json();
            if (!response.ok) {
                setError(data.message ?? `Gagal (HTTP ${response.status})`);
                return;
            }
            setResult(data as AiResult);
        } catch {
            setError('Tidak bisa menghubungi server. Coba lagi.');
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="flex flex-col gap-2">
            <button
                type="button"
                onClick={run}
                disabled={!hasPhoto || processing}
                className="border-border bg-background flex h-10 items-center justify-center gap-2 rounded-xl border-[1.5px] px-3.5 text-[13px] font-bold disabled:cursor-not-allowed disabled:opacity-60"
            >
                {processing ? <Loader2 className="size-4 animate-spin" /> : <ScanSearch className="size-4" strokeWidth={2.2} />}
                {processing ? 'Membaca foto...' : 'Baca AI (saran)'}
            </button>
            {!hasPhoto && <p className="text-muted-foreground text-[11.5px]">Ambil foto dulu, lalu klik Baca AI.</p>}
            {error && <p className="text-destructive text-[12px] font-semibold">{error}</p>}
            {result && (
                <div className="border-border flex flex-col gap-2 rounded-xl border p-3 text-[12.5px]">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`rounded-full px-2.5 py-0.5 text-[12px] font-bold ${DECISION_STYLE[result.decision].className}`}>
                            {DECISION_STYLE[result.decision].label}
                        </span>
                        <span className="text-muted-foreground">{result.reason}</span>
                    </div>
                    {result.items.length > 0 && (
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
                                {result.items.map((item) => (
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
                    <p className="text-muted-foreground text-[11px]">
                        Hanya saran dari AI - tidak mengubah isian atau menghalangi Simpan. Pastikan dengan mata sendiri.
                    </p>
                </div>
            )}
        </div>
    );
}
