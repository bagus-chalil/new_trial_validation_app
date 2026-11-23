import type { ProductOption } from '@/components/ipc/product-search-select';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Camera, CheckCircle2, Loader2, RefreshCw, ScanLine, TriangleAlert, X } from 'lucide-react';
import { useCallback, useState } from 'react';

export interface LabelRead {
    vision_status: 'OK' | 'LOW_CONFIDENCE' | 'ERROR';
    error: string | null;
    no_batch: string | null;
    no_batch_raw: string | null;
    no_batch_trusted: boolean;
    mixing_date: string | null;
    mixing_date_raw: string | null;
    mixing_date_trusted: boolean;
    kode_bulk: string | null;
    kode_bulk_trusted: boolean;
    product: ProductOption | null;
}

export interface LabelScan {
    scan_id: string;
    photo_url: string;
    ai_enabled: boolean;
    read: LabelRead | null;
}

// JSON endpoints (not Inertia visits): CSRF token comes from Laravel's XSRF-TOKEN cookie.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** Uploads the Label Bulk photo, waits for the AI read (can take a minute on a busy GPU) and keeps the result. */
export function useLabelScan() {
    const [scan, setScan] = useState<LabelScan | null>(null);
    const [reading, setReading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const discardRemote = (scanId: string) => {
        void fetch(route('batches.label-scan.discard', { scanId }), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        }).catch(() => undefined);
    };

    const run = useCallback(
        async (photo: File): Promise<LabelScan | null> => {
            setReading(true);
            setError(null);
            if (scan) discardRemote(scan.scan_id); // a retake replaces the parked photo
            setScan(null);

            const body = new FormData();
            body.append('photo', photo);
            try {
                const response = await fetch(route('batches.label-scan'), {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    setError(data.message ?? `Foto gagal diproses (HTTP ${response.status}). Isi manual atau coba lagi.`);
                    return null;
                }
                setScan(data as LabelScan);
                return data as LabelScan;
            } catch {
                setError('Tidak bisa menghubungi server. Isi manual atau coba lagi.');
                return null;
            } finally {
                setReading(false);
            }
        },
        [scan],
    );

    const clear = useCallback(() => {
        if (scan) discardRemote(scan.scan_id);
        setScan(null);
        setError(null);
    }, [scan]);

    /** After the batch is created the server owns the photo; just forget it locally. */
    const forget = useCallback(() => {
        setScan(null);
        setError(null);
    }, []);

    return { scan, reading, error, run, clear, forget };
}

const dmy = (iso: string | null) => (iso ? iso.split('-').reverse().join('/') : '');

type RowTone = 'ok' | 'warn' | 'info';

function ReadRow({ label, value, tone, note, action }: { label: string; value: string; tone: RowTone; note?: string; action?: React.ReactNode }) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 rounded-lg border px-3 py-2 text-sm',
                tone === 'ok' && 'border-emerald-300/70 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10',
                tone === 'warn' && 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10',
                tone === 'info' && 'border-input bg-muted/30',
            )}
        >
            <div className="min-w-0">
                <div className="text-muted-foreground text-[11.5px] font-medium tracking-wide uppercase">{label}</div>
                <div className="font-semibold break-all">{value}</div>
                {note && <div className="text-muted-foreground mt-0.5 text-[12.5px] leading-snug">{note}</div>}
            </div>
            {action}
        </div>
    );
}

export function LabelScanPanel({
    scan,
    reading,
    error,
    selectedProduct,
    noBatch,
    onOpenCamera,
    onClear,
    onUseProduct,
    onUseNoBatch,
}: {
    scan: LabelScan | null;
    reading: boolean;
    error: string | null;
    selectedProduct: ProductOption | null;
    noBatch: string;
    onOpenCamera: () => void;
    onClear: () => void;
    onUseProduct: (product: ProductOption) => void;
    onUseNoBatch: (noBatch: string) => void;
}) {
    const read = scan?.read ?? null;
    const readable = read !== null && read.vision_status !== 'ERROR' && (read.no_batch || read.kode_bulk || read.mixing_date);
    const rows: React.ReactNode[] = [];

    if (read && readable) {
        // FG: the Kode Bulk on the label identifies the FG in Master Produk.
        if (read.kode_bulk) {
            if (read.product) {
                const same = selectedProduct?.id === read.product.id;
                rows.push(
                    <ReadRow
                        key="fg"
                        label="FG dari Kode Bulk"
                        value={`${read.product.fg_code} — ${read.product.product_name}`}
                        tone={same ? 'ok' : 'warn'}
                        note={
                            same
                                ? `Sesuai: Kode Bulk ${read.kode_bulk} milik FG yang dipilih.`
                                : selectedProduct
                                  ? `Kode Bulk ${read.kode_bulk} di label milik FG ini, bukan ${selectedProduct.fg_code}. Cek lagi FG yang dipilih.`
                                  : `Kode Bulk ${read.kode_bulk} di label.${read.kode_bulk_trusted ? '' : ' Bacaan belum yakin, cek dulu.'}`
                        }
                        action={
                            !same && (
                                <Button type="button" size="sm" variant="outline" onClick={() => onUseProduct(read.product!)}>
                                    Pakai FG ini
                                </Button>
                            )
                        }
                    />,
                );
            } else {
                rows.push(
                    <ReadRow
                        key="fg"
                        label="Kode Bulk"
                        value={read.kode_bulk}
                        tone="warn"
                        note="Kode Bulk ini tidak ada di Master Produk (atau tidak aktif). Pilih FG Code manual."
                    />,
                );
            }
        }

        if (read.no_batch) {
            const same = noBatch.trim() !== '' && noBatch.trim().toUpperCase() === read.no_batch;
            rows.push(
                <ReadRow
                    key="batch"
                    label="No Batch"
                    value={read.no_batch}
                    tone={same ? 'ok' : read.no_batch_trusted ? 'info' : 'warn'}
                    note={same ? 'Sesuai dengan isian.' : read.no_batch_trusted ? undefined : 'Bacaan belum yakin, cek dengan label asli.'}
                    action={
                        !same && (
                            <Button type="button" size="sm" variant="outline" onClick={() => onUseNoBatch(read.no_batch!)}>
                                Pakai
                            </Button>
                        )
                    }
                />,
            );
        } else if (read.no_batch_raw) {
            rows.push(
                <ReadRow key="batch" label="No Batch" value={read.no_batch_raw} tone="warn" note="Bentuknya tidak sesuai 26Y034/JLB, isi manual." />,
            );
        }

        if (read.mixing_date) {
            rows.push(
                <ReadRow
                    key="mixing"
                    label="Mixing Date"
                    value={dmy(read.mixing_date)}
                    tone="info"
                    note="Ditawarkan sebagai saran di Startup Check."
                />,
            );
        }
    }

    return (
        <div className="border-input bg-muted/20 grid gap-3 rounded-xl border p-3">
            <div className="flex items-start gap-3">
                {scan ? (
                    <img src={scan.photo_url} alt="Foto Label Bulk" className="border-input size-16 shrink-0 rounded-lg border object-cover" />
                ) : (
                    <div className="bg-primary/10 text-primary flex size-16 shrink-0 items-center justify-center rounded-lg">
                        <ScanLine className="size-7" />
                    </div>
                )}
                <div className="min-w-0 flex-1">
                    <div className="text-sm font-semibold">Scan Label Bulk</div>
                    <p className="text-muted-foreground text-[12.5px] leading-snug">
                        {scan
                            ? 'Foto ini otomatis jadi foto Label Bulk di Startup Check, tidak perlu foto ulang.'
                            : 'Foto label kuning: FG, No Batch, dan Mixing Date terisi otomatis. Foto langsung disimpan untuk Startup Check.'}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <Button type="button" size="sm" onClick={onOpenCamera} disabled={reading} className="min-h-10">
                            {scan ? <RefreshCw className="size-4" /> : <Camera className="size-4" />}
                            {scan ? 'Foto ulang' : 'Ambil foto label'}
                        </Button>
                        {scan && !reading && (
                            <Button type="button" size="sm" variant="ghost" onClick={onClear} className="min-h-10">
                                <X className="size-4" />
                                Hapus foto
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            {reading && (
                <div className="text-muted-foreground flex items-center gap-2 text-[13px]">
                    <Loader2 className="size-4 animate-spin" />
                    AI membaca label, bisa sampai 1 menit. Anda boleh lanjut mengisi form.
                </div>
            )}

            {error && (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-2.5 text-[13px] text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                    {error}
                </div>
            )}

            {scan && !reading && !scan.ai_enabled && (
                <p className="text-muted-foreground text-[12.5px]">Pembacaan AI sedang nonaktif. Foto tetap tersimpan, isi data secara manual.</p>
            )}

            {scan && !reading && scan.ai_enabled && !readable && (
                <div
                    role="alert"
                    className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-2.5 text-[13px] text-amber-900 dark:bg-amber-500/10 dark:text-amber-200"
                >
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Label belum terbaca{read?.error ? ' (layanan AI bermasalah)' : ''}. Foto ulang seluruh label kuning, tegak lurus, tanpa
                        pantulan, atau isi data manual. Foto ini tetap tersimpan.
                    </span>
                </div>
            )}

            {rows.length > 0 && !reading && (
                <div className="grid gap-2">
                    <div className="text-muted-foreground flex items-center gap-1.5 text-[12px]">
                        <CheckCircle2 className="size-3.5" />
                        Hasil baca AI (saran). Anda bisa mengubah semua isian.
                    </div>
                    {rows}
                </div>
            )}
        </div>
    );
}
