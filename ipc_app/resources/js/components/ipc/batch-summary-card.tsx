import { Button } from '@/components/ui/button';
import { LoaderCircle, Sparkles } from 'lucide-react';
import { useState } from 'react';

export interface BatchSummary {
    points: string[];
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Approver's batch summary. The rule-based points are computed on the server from recorded data and
 * are always shown. "Tulis ringkasan AI" asks the Vision Service (Ollama) to word the same facts as a
 * short paragraph. Advisory only: it never recommends a decision and nothing here changes data.
 */
export function BatchSummaryCard({ batchId, summary, aiEnabled }: { batchId: number; summary: BatchSummary; aiEnabled: boolean }) {
    const [state, setState] = useState<{ loading: boolean; text?: string; error?: string }>({ loading: false });

    const run = async () => {
        setState({ loading: true });
        try {
            const response = await fetch(route('approval.summary', batchId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            });
            const data = (await response.json().catch(() => ({}))) as { ok?: boolean; text?: string; error?: string };
            setState(
                data.ok && data.text
                    ? { loading: false, text: data.text }
                    : { loading: false, error: data.error ?? `Ringkasan gagal (HTTP ${response.status}).` },
            );
        } catch {
            setState({ loading: false, error: 'Tidak bisa menghubungi server.' });
        }
    };

    return (
        <section className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-[18px]">
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-[15px] font-bold">Ringkasan untuk approver</p>
                    <p className="text-muted-foreground mt-0.5 text-[12px] font-medium">
                        Dirangkum dari data pemeriksaan yang tercatat. Hanya membantu membaca, keputusan tetap di tangan Anda.
                    </p>
                </div>
                {aiEnabled && (
                    <Button type="button" size="sm" onClick={() => void run()} disabled={state.loading} className="gap-1.5 text-[12px]">
                        {state.loading ? <LoaderCircle className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                        {state.loading ? 'Menulis...' : 'Tulis ringkasan AI'}
                    </Button>
                )}
            </div>

            {state.loading && <p className="text-muted-foreground text-[11.5px]">Bisa sampai 1-2 menit (server AI memproses satu per satu).</p>}
            {state.error && <p className="text-[12.5px] font-medium text-red-600">{state.error}</p>}
            {state.text && (
                <div className="bg-primary/[0.06] rounded-xl p-3.5">
                    <p className="text-primary mb-1 flex items-center gap-1.5 text-[10.5px] font-bold tracking-wide uppercase">
                        <Sparkles className="size-3" /> Ringkasan AI (saran)
                    </p>
                    <p className="text-[13px] leading-relaxed whitespace-pre-wrap">{state.text}</p>
                </div>
            )}

            <ul className="flex list-disc flex-col gap-1.5 pl-5 text-[13px] leading-relaxed">
                {summary.points.map((point) => (
                    <li key={point}>{point}</li>
                ))}
            </ul>
        </section>
    );
}
