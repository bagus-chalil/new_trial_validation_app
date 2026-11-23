import { cn } from '@/lib/utils';
import { AlertTriangle, CheckCircle2, Repeat } from 'lucide-react';

export interface ScopeHits {
    hits: number;
    of: number;
}

/** One NOT OK item of a batch, compared with earlier batches of the same product / line. */
export interface NotOkPattern {
    key?: string;
    label: string;
    repeating: boolean;
    product: ScopeHits | null;
    line: ScopeHits | null;
}

/** One item that keeps coming back across batches (Dashboard). */
export interface RepeatFinding {
    key: string;
    label: string;
    batches: number;
    of: number;
    line: { name: string; count: number } | null;
    product: { name: string; count: number } | null;
}

function scopeText(scope: ScopeHits | null, name: string): string | null {
    return scope ? `${scope.hits} dari ${scope.of} batch ${name} yang sama` : null;
}

/** Findings of ONE batch and whether they repeat. Advisory only: it never changes a decision. */
export function BatchPatternsCard({ patterns, className }: { patterns: NotOkPattern[]; className?: string }) {
    const repeating = patterns.filter((p) => p.repeating).length;

    return (
        <section className={cn('border-border-soft bg-card flex flex-col gap-3 rounded-3xl border p-5 shadow-sm lg:p-6', className)}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[15.5px] font-bold">Temuan NOT OK &amp; pola berulang</p>
                    <p className="text-muted-foreground mt-1 text-xs">
                        Dibandingkan dengan 10 batch terakhir produk dan line yang sama. Hanya informasi, bukan keputusan.
                    </p>
                </div>
                {patterns.length > 0 && (
                    <span
                        className={cn(
                            'shrink-0 rounded-full px-3 py-1 text-xs font-bold',
                            repeating ? 'bg-amber-100 text-amber-800' : 'bg-primary/10 text-primary',
                        )}
                    >
                        {repeating ? `${repeating} berulang` : `${patterns.length} temuan`}
                    </span>
                )}
            </div>

            {patterns.length === 0 ? (
                <p className="flex items-center gap-2 rounded-2xl bg-emerald-50 px-4 py-3 text-[13px] font-medium text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                    <CheckCircle2 className="size-4 shrink-0" strokeWidth={2.4} />
                    Tidak ada item NOT OK pada batch ini.
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {patterns.map((p) => {
                        const where = [scopeText(p.product, 'produk'), scopeText(p.line, 'line')].filter(Boolean).join(' · ');

                        return (
                            <li
                                key={p.key ?? p.label}
                                className={cn(
                                    'flex items-start gap-3 rounded-2xl border px-4 py-3',
                                    p.repeating ? 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30' : 'border-border-soft',
                                )}
                            >
                                {p.repeating ? (
                                    <Repeat className="mt-0.5 size-4 shrink-0 text-amber-700" strokeWidth={2.4} />
                                ) : (
                                    <AlertTriangle className="text-muted-foreground mt-0.5 size-4 shrink-0" strokeWidth={2.4} />
                                )}
                                <div className="min-w-0">
                                    <p className="text-[13.5px] font-bold break-words">{p.label}</p>
                                    <p className="text-muted-foreground text-[12px] font-medium">
                                        {where ? `Juga NOT OK di ${where}.` : 'Belum pernah NOT OK pada batch sebelumnya.'}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

/** Dashboard card: items that came back NOT OK on at least two batches in the last 30 days. */
export function RepeatFindingsCard({ findings }: { findings: RepeatFinding[] }) {
    return (
        <section className="border-border-soft bg-card rounded-2xl border p-5 md:p-6">
            <div className="flex items-center gap-2">
                <Repeat className="text-primary size-4" strokeWidth={2.4} />
                <h2 className="text-[15px] font-bold">Temuan berulang (30 hari)</h2>
            </div>
            <p className="text-muted-foreground mt-1 text-[12px]">Item yang NOT OK di minimal 2 batch. Hanya sinyal untuk dicek QC.</p>

            {findings.length === 0 ? (
                <p className="text-muted-foreground mt-4 text-[13px] font-medium">Belum ada item yang berulang NOT OK dalam 30 hari terakhir.</p>
            ) : (
                <ol className="mt-4 flex flex-col gap-2.5">
                    {findings.map((f, index) => (
                        <li key={f.key} className="border-border-soft flex items-start gap-3 rounded-xl border px-3.5 py-3">
                            <span className="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold">
                                {index + 1}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="text-[13px] font-bold break-words">{f.label}</p>
                                <p className="text-muted-foreground text-[11.5px] font-medium">
                                    {f.batches} dari {f.of} batch
                                    {f.line ? ` · terbanyak di ${f.line.name} (${f.line.count}x)` : ''}
                                    {f.product ? ` · produk ${f.product.name} (${f.product.count}x)` : ''}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
