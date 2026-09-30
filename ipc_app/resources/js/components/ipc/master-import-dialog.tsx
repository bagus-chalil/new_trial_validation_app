import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import {
    COUNT_LABELS,
    formatBytes,
    formatNumber,
    importRequest,
    isRunning,
    type MasterImport,
    type MasterImportIssue,
    type MasterImportType,
    uploadImportFile,
} from '@/lib/master-import';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleSlash,
    Download,
    FileSpreadsheet,
    Info,
    Loader2,
    RotateCcw,
    ShieldCheck,
    Upload,
    UploadCloud,
    X,
    XCircle,
} from 'lucide-react';
import { type DragEvent, useCallback, useEffect, useRef, useState } from 'react';

const MAX_FILE_BYTES = 10 * 1024 * 1024;
const ACCEPTED_EXTENSIONS = ['xlsx', 'xls', 'csv'];
const POLL_INTERVAL_MS = 1000;
/** After this long unclaimed in the queue, assume no worker is running and say so. */
const WORKER_STALE_SECONDS = 15;

type UploadState = { file: File; loaded: number; total: number; abort: () => void };

const STEPS = ['Upload', 'Validasi', 'Tinjau', 'Simpan'] as const;

function stepIndex(importState: MasterImport | null, uploading: boolean): number {
    if (uploading || !importState) return 0;
    switch (importState.status) {
        case 'queued':
        case 'validating':
            return 1;
        case 'validated':
            return 2;
        case 'failed':
            return importState.can_commit ? 3 : 0;
        case 'cancelled':
            return 0;
        default:
            return 3;
    }
}

function percent(done: number, total: number): number | null {
    return total > 0 ? Math.min(100, (done / total) * 100) : null;
}

export function MasterImportDialog({
    type,
    templateHref,
    title,
    description,
    entityLabel,
}: {
    type: MasterImportType;
    templateHref: string;
    title: string;
    description: string;
    /** What the commit phase counts, e.g. "produk" (one per FG Code) or "line". */
    entityLabel: string;
}) {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [dragging, setDragging] = useState(false);
    const [upload, setUpload] = useState<UploadState | null>(null);
    const [importState, setImportState] = useState<MasterImport | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const previousStatus = useRef<string | null>(null);

    // Resume an import that's still open (e.g. after a page reload, or on another tab).
    useEffect(() => {
        importRequest<MasterImport | null>(route('master-imports.latest', type))
            .then((latest) => latest && (isRunning(latest.status) || latest.status === 'validated') && setImportState(latest))
            .catch(() => undefined);
    }, [type]);

    // Poll while a background job is working. Keeps going with the dialog closed, so the
    // trigger button can show progress and the page refreshes when a commit finishes.
    useEffect(() => {
        if (!importState || !isRunning(importState.status)) return;
        const timer = window.setTimeout(() => {
            importRequest<MasterImport>(route('master-imports.show', importState.id))
                .then(setImportState)
                .catch(() => setImportState({ ...importState }));
        }, POLL_INTERVAL_MS);
        return () => window.clearTimeout(timer);
    }, [importState]);

    useEffect(() => {
        const status = importState?.status ?? null;
        if (status === 'completed' && previousStatus.current !== 'completed') {
            router.reload();
        }
        previousStatus.current = status;
    }, [importState?.status]);

    const pickFile = useCallback((picked: File | null | undefined) => {
        setError(null);
        if (!picked) return;
        const extension = picked.name.split('.').pop()?.toLowerCase() ?? '';
        if (!ACCEPTED_EXTENSIONS.includes(extension)) {
            setError(`Format file .${extension} tidak didukung. Gunakan .xlsx, .xls, atau .csv.`);
            return;
        }
        if (picked.size > MAX_FILE_BYTES) {
            setError(`Ukuran file ${formatBytes(picked.size)} melebihi batas 10 MB.`);
            return;
        }
        setFile(picked);
    }, []);

    const startUpload = async () => {
        if (!file) return;
        setError(null);
        const request = uploadImportFile(route('master-imports.store', type), file, (loaded, total) =>
            setUpload((current) => (current ? { ...current, loaded, total } : current)),
        );
        setUpload({ file, loaded: 0, total: file.size, abort: request.abort });
        try {
            setImportState(await request.promise);
            setFile(null);
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setUpload(null);
        }
    };

    const act = async (action: 'commit' | 'cancel') => {
        if (!importState) return;
        setBusy(true);
        setError(null);
        try {
            setImportState(await importRequest<MasterImport>(route(`master-imports.${action}`, importState.id), 'POST'));
        } catch (e) {
            setError((e as Error).message);
        } finally {
            setBusy(false);
        }
    };

    const reset = () => {
        setImportState(null);
        setFile(null);
        setError(null);
    };

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        pickFile(event.dataTransfer.files?.[0]);
    };

    const running = importState !== null && isRunning(importState.status);
    const committing = importState?.status === 'commit_queued' || importState?.status === 'committing';
    const showUploadForm =
        !upload && (!importState || importState.status === 'cancelled' || (importState.status === 'failed' && !importState.can_commit));

    return (
        <>
            <div className="flex flex-wrap items-center gap-2">
                <Button asChild type="button" variant="outline" size="sm" className="h-9 gap-1.5 text-[12.5px]">
                    <a href={templateHref}>
                        <Download className="size-3.5" strokeWidth={2.2} />
                        Unduh Template
                    </a>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-9 gap-1.5 text-[12.5px]"
                    onClick={() => {
                        setError(null);
                        if (importState && ['completed', 'cancelled'].includes(importState.status)) reset();
                        setOpen(true);
                    }}
                >
                    {running ? (
                        <>
                            <Loader2 className="size-3.5 animate-spin" strokeWidth={2.2} />
                            {committing ? 'Menyimpan' : 'Memvalidasi'}
                            {percent(importState.processed_rows, importState.total_rows) !== null &&
                                ` ${Math.floor(percent(importState.processed_rows, importState.total_rows) ?? 0)}%`}
                        </>
                    ) : importState?.status === 'validated' ? (
                        <>
                            <AlertTriangle className="size-3.5 text-amber-600" strokeWidth={2.2} />
                            Tinjau Hasil Import
                        </>
                    ) : (
                        <>
                            <Upload className="size-3.5" strokeWidth={2.2} />
                            Import Excel
                        </>
                    )}
                </Button>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription className="text-[13px]">{description}</DialogDescription>
                    </DialogHeader>

                    <Stepper current={stepIndex(importState, upload !== null)} done={importState?.status === 'completed'} />

                    {error && (
                        <Callout tone="error" icon={XCircle}>
                            {error}
                        </Callout>
                    )}

                    {showUploadForm && (
                        <div className="space-y-4">
                            {importState?.status === 'failed' && (
                                <Callout tone="error" icon={XCircle} title="Import gagal. Tidak ada data yang tersimpan.">
                                    {importState.error_message}
                                </Callout>
                            )}
                            {importState?.status === 'cancelled' && (
                                <Callout tone="neutral" icon={CircleSlash}>
                                    {importState.error_message ?? 'Import dibatalkan.'}
                                </Callout>
                            )}

                            <div
                                role="button"
                                tabIndex={0}
                                onClick={() => inputRef.current?.click()}
                                onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && inputRef.current?.click()}
                                onDragOver={(e) => {
                                    e.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={onDrop}
                                className={cn(
                                    'flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed px-6 py-8 text-center transition-colors',
                                    dragging ? 'border-primary bg-accent' : 'border-border hover:border-primary/50 hover:bg-accent/50',
                                )}
                            >
                                <UploadCloud className={cn('size-9', dragging ? 'text-primary' : 'text-muted-foreground/70')} strokeWidth={1.6} />
                                <p className="text-[14px] font-semibold">Tarik & lepas file di sini, atau klik untuk memilih</p>
                                <p className="text-muted-foreground text-[12px]">
                                    .xlsx, .xls, .csv &middot; maks. 10 MB &middot; maks. 20.000 baris
                                </p>
                                <input
                                    ref={inputRef}
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    className="hidden"
                                    onChange={(e) => {
                                        pickFile(e.target.files?.[0]);
                                        e.target.value = '';
                                    }}
                                />
                            </div>

                            {file && (
                                <FileChip name={file.name} size={file.size}>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-8"
                                        onClick={() => setFile(null)}
                                        aria-label="Hapus file"
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </FileChip>
                            )}

                            <Callout tone="info" icon={ShieldCheck}>
                                File akan <b>dicek dulu tanpa menyimpan apa pun</b>. Anda akan melihat ringkasan dan daftar error sebelum memutuskan
                                untuk menyimpan.
                            </Callout>

                            <div className="flex justify-end gap-2">
                                <Button type="button" onClick={startUpload} disabled={!file} className="gap-1.5">
                                    <Upload className="size-4" />
                                    Upload & Validasi
                                </Button>
                            </div>
                        </div>
                    )}

                    {upload && (
                        <div className="space-y-4">
                            <FileChip name={upload.file.name} size={upload.file.size} />
                            <ProgressBlock
                                label="Mengupload file..."
                                detail={`${formatBytes(upload.loaded)} / ${formatBytes(upload.total)}`}
                                value={percent(upload.loaded, upload.total)}
                            />
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" onClick={upload.abort}>
                                    Batalkan Upload
                                </Button>
                            </div>
                        </div>
                    )}

                    {importState && running && !upload && (
                        <RunningView importState={importState} entityLabel={entityLabel} busy={busy} onCancel={() => act('cancel')} />
                    )}

                    {importState?.status === 'validated' && !upload && (
                        <ReviewView importState={importState} busy={busy} onCommit={() => act('commit')} onCancel={() => act('cancel')} />
                    )}

                    {importState?.status === 'failed' && importState.can_commit && !upload && (
                        <div className="space-y-4">
                            <Callout tone="error" icon={XCircle} title="Penyimpanan gagal. Tidak ada data yang tersimpan.">
                                {importState.error_message}
                            </Callout>
                            <div className="flex flex-wrap justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => act('cancel')} disabled={busy}>
                                    Upload File Lain
                                </Button>
                                <Button type="button" onClick={() => act('commit')} disabled={busy} className="gap-1.5">
                                    <RotateCcw className="size-4" />
                                    Coba Simpan Lagi
                                </Button>
                            </div>
                        </div>
                    )}

                    {importState?.status === 'completed' && (
                        <CompletedView
                            importState={importState}
                            onClose={() => {
                                setOpen(false);
                                reset();
                            }}
                            onAnother={reset}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function Stepper({ current, done }: { current: number; done: boolean }) {
    return (
        <ol className="flex items-center gap-2">
            {STEPS.map((step, index) => {
                const complete = done || index < current;
                const active = !done && index === current;
                return (
                    <li key={step} className="flex flex-1 items-center gap-2">
                        <span
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                complete && 'bg-emerald-600 text-white',
                                active && 'bg-primary text-primary-foreground',
                                !complete && !active && 'bg-accent text-muted-foreground',
                            )}
                        >
                            {complete ? <CheckCircle2 className="size-4" /> : index + 1}
                        </span>
                        <span className={cn('text-[12px] font-semibold', !active && !complete && 'text-muted-foreground')}>{step}</span>
                        {index < STEPS.length - 1 && <span className="bg-border-soft h-px flex-1" />}
                    </li>
                );
            })}
        </ol>
    );
}

function RunningView({
    importState,
    entityLabel,
    busy,
    onCancel,
}: {
    importState: MasterImport;
    entityLabel: string;
    busy: boolean;
    onCancel: () => void;
}) {
    const committing = importState.status === 'commit_queued' || importState.status === 'committing';
    const waiting = importState.status === 'queued' || importState.status === 'commit_queued';
    const value = waiting ? null : percent(importState.processed_rows, importState.total_rows);
    const unit = committing ? entityLabel : 'baris';

    return (
        <div className="space-y-4">
            <FileChip name={importState.original_filename} />
            <ProgressBlock
                label={
                    importState.cancel_requested
                        ? 'Membatalkan...'
                        : waiting
                          ? 'Menunggu giliran di antrian...'
                          : importState.total_rows === 0
                            ? 'Membaca file...'
                            : committing
                              ? `Menyimpan ${formatNumber(importState.processed_rows)} dari ${formatNumber(importState.total_rows)} ${unit}`
                              : `Memeriksa ${formatNumber(importState.processed_rows)} dari ${formatNumber(importState.total_rows)} ${unit}`
                }
                detail={value !== null ? `${Math.floor(value)}%` : undefined}
                value={value}
            />

            {waiting && importState.waiting_seconds >= WORKER_STALE_SECONDS && (
                <Callout tone="warn" icon={AlertTriangle} title={`Belum diproses setelah ${importState.waiting_seconds} detik`}>
                    Kemungkinan proses antrian (queue worker) di server sedang tidak berjalan. Hubungi admin IT untuk menjalankan
                    <code className="mx-1 rounded bg-black/5 px-1 dark:bg-white/10">php artisan queue:work</code>. Import akan otomatis lanjut begitu
                    worker aktif, jadi tidak perlu upload ulang.
                </Callout>
            )}

            {committing ? (
                <Callout tone="info" icon={ShieldCheck}>
                    Semua perubahan disimpan dalam <b>satu transaksi</b>. Kalau gagal di tengah jalan atau Anda membatalkan, semuanya dikembalikan
                    (rollback), jadi tidak akan ada data yang tersimpan setengah.
                </Callout>
            ) : (
                <Callout tone="neutral" icon={Info}>
                    Belum ada data yang disimpan. Anda boleh menutup jendela ini karena proses tetap berjalan, dan progress tetap terlihat di tombol
                    import.
                </Callout>
            )}

            <div className="flex justify-end">
                <Button type="button" variant="outline" onClick={onCancel} disabled={busy || importState.cancel_requested}>
                    {committing ? 'Batalkan & Rollback' : 'Batalkan'}
                </Button>
            </div>
        </div>
    );
}

function ReviewView({
    importState,
    busy,
    onCommit,
    onCancel,
}: {
    importState: MasterImport;
    busy: boolean;
    onCommit: () => void;
    onCancel: () => void;
}) {
    const [tab, setTab] = useState<'errors' | 'warnings'>(importState.error_rows > 0 ? 'errors' : 'warnings');
    const issues = importState.issues;
    const list = tab === 'errors' ? issues?.errors : issues?.warnings;
    const listCount = tab === 'errors' ? issues?.error_count : issues?.warning_count;

    return (
        <div className="space-y-4">
            <FileChip name={importState.original_filename} />

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <Stat label="Total baris" value={importState.total_rows} />
                <Stat label="Siap disimpan" value={importState.valid_rows} tone="good" />
                <Stat label="Error (dilewati)" value={importState.error_rows} tone={importState.error_rows > 0 ? 'bad' : undefined} />
                <Stat label="Peringatan" value={importState.warning_rows} tone={importState.warning_rows > 0 ? 'warn' : undefined} />
            </div>

            {importState.preview && <CountList title="Yang akan terjadi jika disimpan" counts={importState.preview} />}

            {issues && (issues.error_count > 0 || issues.warning_count > 0) && (
                <div className="border-border-soft rounded-2xl border">
                    <div className="border-border-soft flex flex-wrap items-center justify-between gap-2 border-b p-2">
                        <div className="flex gap-1">
                            <TabButton active={tab === 'errors'} onClick={() => setTab('errors')} tone="bad">
                                Error ({formatNumber(issues.error_count)})
                            </TabButton>
                            <TabButton active={tab === 'warnings'} onClick={() => setTab('warnings')} tone="warn">
                                Peringatan ({formatNumber(issues.warning_count)})
                            </TabButton>
                        </div>
                        {importState.issues_url && (
                            <Button asChild variant="ghost" size="sm" className="h-8 gap-1.5 text-[12px]">
                                <a href={importState.issues_url}>
                                    <Download className="size-3.5" />
                                    Unduh laporan lengkap (.xlsx)
                                </a>
                            </Button>
                        )}
                    </div>
                    <IssueTable issues={list ?? []} columns={importState.columns} />
                    {listCount !== undefined && listCount > issues.limit && (
                        <p className="text-muted-foreground border-border-soft border-t px-3 py-2 text-[12px]">
                            Menampilkan {issues.limit} dari {formatNumber(listCount)}. Unduh laporan untuk melihat semuanya.
                        </p>
                    )}
                </div>
            )}

            {importState.valid_rows === 0 ? (
                <Callout tone="error" icon={XCircle} title="Tidak ada baris yang bisa disimpan">
                    Semua baris error atau duplikat. Perbaiki file sesuai laporan di atas, lalu upload ulang.
                </Callout>
            ) : (
                <Callout tone="info" icon={ShieldCheck} title="Belum ada data yang disimpan">
                    {importState.error_rows > 0 ? (
                        <>
                            Jika Anda lanjut, <b>{formatNumber(importState.valid_rows)} baris valid</b> disimpan sekaligus, dan{' '}
                            <b>{formatNumber(importState.error_rows)} baris error dilewati</b>. Perbaiki baris error di file (lihat laporan), lalu
                            upload ulang file yang sama. Baris yang sudah tersimpan akan terdeteksi &ldquo;tidak berubah&rdquo;, jadi tidak akan
                            dobel.
                        </>
                    ) : (
                        <>Semua baris valid. Data disimpan dalam satu transaksi: berhasil semua, atau tidak ada yang berubah sama sekali.</>
                    )}
                </Callout>
            )}

            <div className="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel} disabled={busy}>
                    Batal & Upload Ulang
                </Button>
                <Button type="button" onClick={onCommit} disabled={busy || !importState.can_commit} className="gap-1.5">
                    {busy ? <Loader2 className="size-4 animate-spin" /> : <CheckCircle2 className="size-4" />}
                    Simpan {formatNumber(importState.valid_rows)} Baris Valid
                </Button>
            </div>
        </div>
    );
}

function CompletedView({ importState, onClose, onAnother }: { importState: MasterImport; onClose: () => void; onAnother: () => void }) {
    return (
        <div className="space-y-4">
            <div className="flex flex-col items-center gap-2 py-2 text-center">
                <CheckCircle2 className="size-12 text-emerald-600" strokeWidth={1.8} />
                <p className="text-[16px] font-bold">Import selesai</p>
                <p className="text-muted-foreground text-[13px]">
                    {formatNumber(importState.valid_rows)} baris dari <b>{importState.original_filename}</b> berhasil diproses.
                    {importState.error_rows > 0 && ` ${formatNumber(importState.error_rows)} baris error dilewati.`}
                </p>
            </div>
            {importState.result && <CountList title="Hasil" counts={importState.result} />}
            <div className="flex flex-wrap justify-end gap-2">
                {importState.issues_url && importState.error_rows > 0 && (
                    <Button asChild variant="outline" className="gap-1.5">
                        <a href={importState.issues_url}>
                            <Download className="size-4" />
                            Laporan Baris Error
                        </a>
                    </Button>
                )}
                <Button type="button" variant="outline" onClick={onAnother}>
                    Import File Lain
                </Button>
                <Button type="button" onClick={onClose}>
                    Selesai
                </Button>
            </div>
        </div>
    );
}

function ProgressBlock({ label, detail, value }: { label: string; detail?: string; value: number | null }) {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between gap-3 text-[13px]">
                <span className="flex items-center gap-2 font-semibold">
                    <Loader2 className="text-muted-foreground size-4 animate-spin" />
                    {label}
                </span>
                {detail && <span className="text-muted-foreground tabular-nums">{detail}</span>}
            </div>
            <Progress value={value} className="h-2.5" />
        </div>
    );
}

function FileChip({ name, size, children }: { name: string; size?: number; children?: React.ReactNode }) {
    return (
        <div className="border-border-soft bg-card flex items-center gap-3 rounded-xl border p-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950">
                <FileSpreadsheet className="size-5 text-emerald-700 dark:text-emerald-400" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="truncate text-[13.5px] font-semibold">{name}</p>
                {size !== undefined && <p className="text-muted-foreground text-[12px]">{formatBytes(size)}</p>}
            </div>
            {children}
        </div>
    );
}

const STAT_TONES = {
    good: 'text-emerald-700 dark:text-emerald-400',
    bad: 'text-red-600 dark:text-red-400',
    warn: 'text-amber-600 dark:text-amber-400',
};

function Stat({ label, value, tone }: { label: string; value: number; tone?: keyof typeof STAT_TONES }) {
    return (
        <div className="border-border-soft bg-card rounded-xl border p-3">
            <p className="text-muted-foreground text-[11.5px] font-medium">{label}</p>
            <p className={cn('text-[20px] font-bold tabular-nums', tone && STAT_TONES[tone])}>{formatNumber(value)}</p>
        </div>
    );
}

const COUNT_TONES = {
    new: 'bg-emerald-500',
    update: 'bg-sky-500',
    neutral: 'bg-zinc-400',
    warn: 'bg-amber-500',
};

function CountList({ title, counts }: { title: string; counts: Record<string, number> }) {
    const entries = Object.keys(COUNT_LABELS).filter((key) => (counts[key] ?? 0) > 0);
    if (entries.length === 0) return null;

    return (
        <div className="border-border-soft rounded-2xl border p-3">
            <p className="mb-2 text-[12.5px] font-bold">{title}</p>
            <ul className="grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                {entries.map((key) => (
                    <li key={key} className="flex items-center justify-between gap-3 text-[13px]">
                        <span className="flex items-center gap-2">
                            <span className={cn('size-2 rounded-full', COUNT_TONES[COUNT_LABELS[key].tone])} />
                            {COUNT_LABELS[key].label}
                        </span>
                        <span className="font-semibold tabular-nums">{formatNumber(counts[key])}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function TabButton({ active, onClick, tone, children }: { active: boolean; onClick: () => void; tone: 'bad' | 'warn'; children: React.ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-lg px-3 py-1.5 text-[12.5px] font-semibold transition-colors',
                active
                    ? tone === 'bad'
                        ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
                        : 'bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                    : 'text-muted-foreground hover:bg-accent',
            )}
        >
            {children}
        </button>
    );
}

function IssueTable({ issues, columns }: { issues: MasterImportIssue[]; columns: Record<string, string> }) {
    if (issues.length === 0) {
        return <p className="text-muted-foreground px-3 py-6 text-center text-[13px]">Tidak ada.</p>;
    }

    return (
        <div className="max-h-72 overflow-auto">
            <table className="w-full text-left text-[12.5px]">
                <thead className="bg-card sticky top-0">
                    <tr className="text-muted-foreground">
                        <th className="w-16 px-3 py-2 font-semibold">Baris</th>
                        <th className="px-3 py-2 font-semibold">Keterangan</th>
                        <th className="px-3 py-2 font-semibold">Isi baris</th>
                    </tr>
                </thead>
                <tbody>
                    {issues.map((issue, index) => (
                        <tr key={`${issue.row_number}-${index}`} className="border-border-soft border-t align-top">
                            <td className="px-3 py-2 font-semibold tabular-nums">{issue.row_number}</td>
                            <td className="px-3 py-2">{issue.message}</td>
                            <td className="text-muted-foreground px-3 py-2">
                                {Object.entries(columns)
                                    .map(([key, label]) => `${label}: ${issue.values?.[key] || '(kosong)'}`)
                                    .join(' · ')}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

const CALLOUT_TONES = {
    info: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/60 dark:text-sky-100',
    warn: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-100',
    error: 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/60 dark:text-red-100',
    neutral: 'border-border-soft bg-accent/60 text-foreground',
};

function Callout({
    tone,
    icon: Icon,
    title,
    children,
}: {
    tone: keyof typeof CALLOUT_TONES;
    icon: React.ComponentType<{ className?: string }>;
    title?: string;
    children: React.ReactNode;
}) {
    return (
        <div className={cn('flex gap-2.5 rounded-xl border p-3 text-[12.5px] leading-relaxed', CALLOUT_TONES[tone])}>
            <Icon className="mt-0.5 size-4 shrink-0" />
            <div>
                {title && <p className="mb-0.5 font-bold">{title}</p>}
                {children}
            </div>
        </div>
    );
}
