import { ChevronDown, Loader2, Sparkles } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Verdict = 'match' | 'mismatch' | 'review' | 'info';

type AiItem = {
    key: string;
    label: string;
    expected: string | null;
    read: string | null;
    verdict: Verdict;
    note: string | null;
    /** The photo itself is the problem (blurry / cut off / glare): advise taking it again. */
    retake?: boolean;
    /** Nomor IM only: the AI read is trusted and agrees with the form, so the input is locked (read-only). */
    lock?: boolean;
};

/** A value the AI read with confidence that can be copied into a still-empty form field (operator clicks). */
export type AiSuggestion = {
    field: 'mixing_date' | 'shelf_life_months' | 'im_document_number';
    label: string;
    value: string | number;
    display: string;
};

export type FieldStatus = {
    field: string;
    label: string;
    state: 'none' | 'queued' | 'running' | 'done';
    decision?: 'PASS' | 'FAIL' | 'REVIEW';
    reason?: string;
    items?: AiItem[];
    suggestions?: AiSuggestion[];
    /** The newest read was made with the operator's feedback; `discarded` = it was worse, so the earlier read is shown. */
    hint?: { text: string; discarded: boolean };
};

/** One-tap hints per photo: only where/how to look, never the value itself. */
const HINT_PRESETS: Record<string, string[]> = {
    date_bulk: ['Fokus ke stiker kuning Label Bulk', 'Tanggal mixing ada di baris Mixing Date'],
    im_number: ['Fokus ke judul / nomor di bagian atas dokumen IM', 'Umur simpan ada di tabel di bagian bawah dokumen'],
    coding_primary: [
        'Kode cetak ada di ujung tube (bagian crimp)',
        'Kode cetak kecil ada di sisi bawah kemasan',
        'Kode cetak ada di tutup / cap',
        'Kode cetak ada di label kecil di sisi kanan',
    ],
};

const HINT_MAX = 300;

export type Status = { pending: boolean; fields: FieldStatus[]; notes: string[] };

/** Suggestions the form applies by itself (no button): the operator only types when the photo is unclear. */
const AUTO_APPLIED: AiSuggestion['field'][] = ['im_document_number'];

type Entered = {
    mixing_date?: string;
    exp_date?: string;
    shelf_life_months?: number;
    packaging_type?: string;
    im_document_number?: string;
};

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
    onApply,
    onStatus,
}: {
    batchId: number;
    /** Called with every fresh status so the form can auto-fill / lock inputs. */
    onStatus?: (status: Status) => void;
    readOnly: boolean;
    /** Changes whenever an AI-read photo is added/replaced, so the reads get queued. */
    photoKey: string;
    getEntered: () => Entered;
    /** Operator clicked "Pakai": copy the suggested value into the form field. Omit where the form isn't on screen (no buttons then). */
    onApply?: (suggestion: AiSuggestion) => void;
}) {
    const [status, setStatus] = useState<Status | null>(null);
    const [error, setError] = useState(false);
    // The operator can fold the card away; the choice is remembered in this browser only.
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return localStorage.getItem('startup-ai-card-collapsed') === '1';
        } catch {
            return false;
        }
    });
    const toggle = () =>
        setCollapsed((c) => {
            try {
                localStorage.setItem('startup-ai-card-collapsed', c ? '0' : '1');
            } catch {
                // storage unavailable: the card just forgets next time
            }
            return !c;
        });
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

    // "Beri masukan ke AI": which photo's feedback box is open, its text, and whether it is being sent.
    const [hintFor, setHintFor] = useState<string | null>(null);
    const [hintText, setHintText] = useState('');
    const [hintBusy, setHintBusy] = useState(false);
    const [hintError, setHintError] = useState<string | null>(null);

    const sendHint = async (field: string) => {
        const text = hintText.trim();
        if (text.length < 3) {
            setHintError('Tulis masukan dulu (minimal 3 huruf).');
            return;
        }
        setHintBusy(true);
        setHintError(null);
        try {
            const response = await fetch(`/batches/${batchId}/startup-check/ai-hint`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ ...enteredRef.current, field, hint: text }),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                setHintError(json?.errors?.hint?.[0] ?? json?.message ?? 'Masukan belum bisa dikirim. Coba lagi.');
                return;
            }
            setStatus(json as Status);
            setHintFor(null);
            setHintText('');
        } catch {
            setHintError('Masukan belum bisa dikirim. Coba lagi.');
        } finally {
            setHintBusy(false);
        }
    };

    const onStatusRef = useRef(onStatus);
    onStatusRef.current = onStatus;
    useEffect(() => {
        if (status) onStatusRef.current?.(status);
    }, [status]);

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
            <button type="button" onClick={toggle} aria-expanded={!collapsed} className="flex w-full items-center gap-2 text-left">
                <Sparkles className="size-4" strokeWidth={2.2} />
                <h3 className="text-[14px] font-bold">Rekomendasi AI</h3>
                {status.pending && (
                    <span className="text-muted-foreground flex items-center gap-1 text-[11.5px]">
                        <Loader2 className="size-3 animate-spin" /> sedang membaca foto, form tetap bisa diisi
                    </span>
                )}
                <span className="text-muted-foreground ml-auto flex items-center gap-1 text-[11.5px] font-semibold">
                    {collapsed ? 'Tampilkan' : 'Tutup'}
                    <ChevronDown className={`size-4 transition-transform ${collapsed ? '' : 'rotate-180'}`} />
                </span>
            </button>

            {!collapsed && (
                <>
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
                            {f.state === 'done' &&
                                !readOnly &&
                                onApply &&
                                f.suggestions &&
                                f.suggestions.some((s) => !AUTO_APPLIED.includes(s.field)) && (
                                    <div className="flex flex-wrap items-center gap-2 rounded-lg bg-sky-50 p-2.5 text-[12px] text-sky-900 dark:bg-sky-950 dark:text-sky-200">
                                        <span className="font-bold">Saran isi (form masih kosong):</span>
                                        {f.suggestions
                                            .filter((s) => !AUTO_APPLIED.includes(s.field))
                                            .map((s) => (
                                                <button
                                                    key={s.field}
                                                    type="button"
                                                    onClick={() => onApply(s)}
                                                    className="rounded-full border border-sky-300 bg-white px-3 py-1 font-bold hover:bg-sky-100 dark:border-sky-700 dark:bg-sky-900 dark:hover:bg-sky-800"
                                                >
                                                    Pakai {s.label}: {s.display}
                                                </button>
                                            ))}
                                        <span className="text-[11px]">Cek dengan foto sebelum disimpan.</span>
                                    </div>
                                )}
                            {f.state === 'done' && f.items?.some((i) => i.retake) && (
                                <div className="rounded-lg bg-amber-50 p-2.5 text-[12px] text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                    <p className="font-bold">Saran: ambil ulang foto ini</p>
                                    <ul className="list-disc pl-4">
                                        {f.items
                                            .filter((i) => i.retake && i.note)
                                            .map((i) => (
                                                <li key={i.key}>
                                                    <span className="font-semibold">{i.label}:</span> {i.note}
                                                </li>
                                            ))}
                                    </ul>
                                </div>
                            )}
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
                            {f.state === 'done' && f.hint && (
                                <p
                                    className={`rounded-lg p-2 text-[11.5px] ${
                                        f.hint.discarded
                                            ? 'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-200'
                                            : 'bg-sky-50 text-sky-900 dark:bg-sky-950 dark:text-sky-200'
                                    }`}
                                >
                                    {f.hint.discarded
                                        ? `Masukan Anda ("${f.hint.text}") belum membantu AI, hasil sebelumnya dipertahankan. Isi manual dari foto, atau coba masukan lain.`
                                        : `AI membaca ulang dengan masukan Anda: "${f.hint.text}". Tetap cek dengan foto.`}
                                </p>
                            )}
                            {!readOnly && (f.state === 'done' || f.state === 'none') && HINT_PRESETS[f.field] && (
                                <div className="flex flex-col gap-2">
                                    {hintFor !== f.field ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setHintFor(f.field);
                                                setHintText('');
                                                setHintError(null);
                                            }}
                                            className="text-muted-foreground w-fit text-[12px] font-semibold underline"
                                        >
                                            Hasil belum pas? Beri masukan ke AI (tanpa foto ulang)
                                        </button>
                                    ) : (
                                        <div className="border-border flex flex-col gap-2 rounded-lg border p-2.5">
                                            <p className="text-[12px] font-semibold">Beri tahu AI di mana / bagaimana membaca foto yang sama:</p>
                                            <div className="flex flex-wrap gap-1.5">
                                                {HINT_PRESETS[f.field].map((preset) => (
                                                    <button
                                                        key={preset}
                                                        type="button"
                                                        onClick={() => setHintText(preset)}
                                                        className="border-border hover:bg-muted rounded-full border px-2.5 py-1 text-[11.5px]"
                                                    >
                                                        {preset}
                                                    </button>
                                                ))}
                                            </div>
                                            <textarea
                                                value={hintText}
                                                onChange={(e) => setHintText(e.target.value.replace(/\s+/g, ' ').slice(0, HINT_MAX))}
                                                rows={2}
                                                placeholder="Contoh: kode cetak ada di ujung tube, baris paling bawah"
                                                className="border-input bg-background w-full resize-none rounded-md border p-2 text-[12.5px]"
                                            />
                                            <p className="text-muted-foreground text-[11px]">
                                                Cukup petunjuk letak/cara baca. Jangan menulis nilainya: AI hanya menyalin yang benar-benar tercetak
                                                di foto, dan Anda tetap harus mengecek hasilnya.
                                            </p>
                                            {hintError && <p className="text-[12px] font-semibold text-red-600">{hintError}</p>}
                                            <div className="flex gap-2">
                                                <button
                                                    type="button"
                                                    disabled={hintBusy}
                                                    onClick={() => void sendHint(f.field)}
                                                    className="bg-primary text-primary-foreground rounded-full px-3.5 py-1.5 text-[12px] font-bold disabled:opacity-60"
                                                >
                                                    {hintBusy ? 'Mengirim...' : 'Baca ulang dengan masukan ini'}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setHintFor(null)}
                                                    className="px-2 text-[12px] font-semibold underline"
                                                >
                                                    Batal
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
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
                </>
            )}
        </div>
    );
}
