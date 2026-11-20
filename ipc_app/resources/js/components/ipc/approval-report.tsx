import InputError from '@/components/input-error';
import { AccordionCard } from '@/components/ipc/accordion-card';
import { ChipToggleGroup, StatusChip } from '@/components/ipc/chip-toggle-group';

import { PhotoLightbox } from '@/components/ipc/photo-lightbox';
import { Toast, useToast } from '@/components/ipc/toast';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { Eye, LoaderCircle, Printer } from 'lucide-react';
import { FormEventHandler, type ReactNode, Suspense, lazy, useState } from 'react';

// pdf.js is large: load it only when someone actually opens a document (weak tablets, slow Wi-Fi).
const PdfViewer = lazy(() => import('@/components/ipc/pdf-viewer').then((m) => ({ default: m.PdfViewer })));

export interface ChecklistGroup {
    key: string;
    fields: Record<string, string>;
    options: string[];
}

export interface SampleGroup {
    key: string;
    label: string;
    parameters: Record<string, string>;
}

export interface ApprovalRevisionRow {
    id: number;
    revision_no: number;
    /** 1 = Level 1 (QC Verifikator), 2 = Level 2 (Team Leader / Supervisor). */
    level?: number;
    decision: string;
    remarks: string | null;
    decided_at: string;
    user?: { name: string } | null;
}

export interface ApprovalData {
    decision: string | null;
    remarks: string | null;
    approved_at: string | null;
    approver_user_id?: number | null;
    approver?: { name: string } | null;
    /** Level 2 (Team Leader / Supervisor) decision on the same stage. */
    l2_decision?: string | null;
    l2_remarks?: string | null;
    l2_decided_at?: string | null;
    l2_approver?: { name: string } | null;
    revisions?: ApprovalRevisionRow[];
}

/** Both approval levels are Approved. */
export function isFullyApproved(approval: ApprovalData | null | undefined): boolean {
    return approval?.decision === 'Approved' && approval?.l2_decision === 'Approved';
}

export interface StageInfo {
    stage: string;
    label: string;
    ready: boolean;
    approval: ApprovalData | null;
}

export interface TestTypeRow {
    id: number;
    name: string;
    label: string;
    is_performed: boolean;
}

export function formatDateTime(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export function groupLabel(key: string): string {
    return key
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

export function EmptyNote({ children }: { children: ReactNode }) {
    return (
        <div className="border-border-soft bg-card text-muted-foreground/70 rounded-[18px] border border-dashed p-4 text-center text-[13px] font-medium italic">
            {children}
        </div>
    );
}

export function InfoField({ label, value, full }: { label: string; value: string; full?: boolean }) {
    return (
        <div className={full ? 'col-span-full' : undefined}>
            <p className="text-muted-foreground/70 text-[10.5px] font-semibold tracking-wide uppercase">{label}</p>
            <p className="mt-0.5 text-[13.5px] font-bold break-words">{value}</p>
        </div>
    );
}

export function ChecklistRow({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="border-border-soft col-span-full flex items-center justify-between gap-3 border-b py-2 last:border-0">
            <p className="text-[13px] font-semibold">{label}</p>
            <StatusChip value={value} />
        </div>
    );
}

export function PhotoRow({ photos }: { photos: { key: string; label: string; url: string | null | string[] }[] }) {
    return (
        <div className="border-border-soft bg-card grid grid-cols-2 gap-4 rounded-[20px] border p-[18px] sm:grid-cols-3 md:grid-cols-5">
            {photos.map(({ key, label, url }) => {
                const urls = Array.isArray(url) ? url : url ? [url] : [];
                return (
                    <div key={key} className="flex flex-col gap-2">
                        <p className="text-muted-foreground text-xs font-semibold">{label}</p>
                        {urls.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                                {urls.map((u, i) => (
                                    <PhotoLightbox
                                        key={i}
                                        src={u}
                                        alt={`${label}${urls.length > 1 ? ` ${i + 1}` : ''}`}
                                        triggerClassName={urls.length > 1 ? 'h-24 w-[5.5rem]' : 'h-24 w-full'}
                                        imageClassName="border-border h-full w-full rounded-xl border object-cover"
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="border-border-soft bg-background text-muted-foreground/50 flex h-24 w-full items-center justify-center rounded-xl border border-dashed text-[11.5px] font-medium italic">
                                Belum ada foto
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export interface RevisionRow {
    id: number;
    revision_no: number;
    finalize: boolean;
    created_at: string;
    user?: { name: string } | null;
}

/**
 * TH_PROGRESS save history — same shape/behavior as the "Riwayat Simpan" AccordionCard already
 * shipped on the Filling/Packing/Finished Check edit pages, surfaced here too per direct user
 * request so the full save history (with timestamps) is visible from the Approval report, not
 * only from the edit form.
 */
export function RevisionHistoryCard<T extends RevisionRow>({
    title,
    revisions,
    renderSummary,
    renderRemarks,
}: {
    title: string;
    revisions: T[];
    renderSummary: (rev: T) => ReactNode;
    renderRemarks?: (rev: T) => string | null | undefined;
}) {
    if (revisions.length === 0) return null;

    const sorted = [...revisions].sort((a, b) => b.revision_no - a.revision_no);

    return (
        <AccordionCard title={title} progress={`${sorted.length}x disimpan`} defaultOpen={false}>
            <div className="col-span-full flex flex-col gap-2.5">
                {sorted.map((rev) => (
                    <div key={rev.id} className="border-border-soft rounded-xl border p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-[13px] font-bold">
                                #{rev.revision_no} {rev.finalize && '· Selesai'}
                            </span>
                            <span className="text-muted-foreground text-[11.5px] font-medium">
                                {formatDateTime(rev.created_at)} · {rev.user?.name ?? '—'}
                            </span>
                        </div>
                        <div className="text-muted-foreground mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[12px]">{renderSummary(rev)}</div>
                        {renderRemarks?.(rev) && <p className="text-muted-foreground mt-1 text-[12px]">Remarks: {renderRemarks(rev)}</p>}
                    </div>
                ))}
            </div>
        </AccordionCard>
    );
}

/**
 * Header print button — opens the Browsershot-rendered PDF in the current tab so tablet Back
 * returns to the app instead of closing a separate PDF tab. On Approval pages this is a
 * non-recorded preview; on Print pages the same href/component is
 * reused but points at PrintController::pdf(), where opening it also logs an IpcPrintLog row —
 * `label` lets each caller describe which of those two it is ("Preview Cetak" vs "Cetak").
 */
export function PrintPreviewButton({ href, label = 'Preview Cetak' }: { href: string; label?: string }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="border-border-soft bg-card text-foreground flex h-11 items-center gap-2 rounded-2xl border px-4 text-[13px] font-bold"
            >
                <Printer className="size-4" strokeWidth={2.2} />
                <span className="hidden sm:inline">{label}</span>
            </button>
            {open && (
                <Suspense fallback={null}>
                    <PdfViewer url={href} title={label} onClose={() => setOpen(false)} />
                </Suspense>
            )}
        </>
    );
}

export interface PrintInfo {
    stage: string;
    label: string;
    printCount: number;
    lastPrintedAt: string | null;
    lastPrintedBy: string | null;
}

/**
 * Bottom-of-page action on each Print detail page — mirrors ApprovalActionCard's spot on the
 * Approval detail pages, but there's no decision to make here (legacy's *_View screens are pure
 * read+print). Deliberately a *preview*, not the print action, since 2026-09-08 (direct user
 * request): this is the button people actually reach for most — including just to look at the
 * document for an audit, not to physically print it — so routing it at PrintController::preview()
 * (unlogged) instead of pdf() keeps ipc_print_logs' history meaning "actually printed," not
 * "opened." The header's PrintPreviewButton (label="Cetak") stays the one real, logged print
 * action. Still shows the accumulated print count/last-printed-by so it's obvious that history
 * exists even though this card's own button doesn't add to it.
 */
export function PrintActionCard({ batchId, info }: { batchId: number; info: PrintInfo }) {
    const [previewOpen, setPreviewOpen] = useState(false);

    return (
        <div className="border-border-soft bg-card flex flex-col gap-3 rounded-[20px] border p-[18px]">
            <div className="flex items-center justify-between gap-3">
                <p className="text-[14.5px] font-bold">Dokumen — {info.label}</p>
                {info.printCount > 0 && (
                    <span className="rounded-full bg-green-100 px-3 py-1 text-[12px] font-bold text-green-800">Sudah dicetak {info.printCount}x</span>
                )}
            </div>
            {info.lastPrintedAt && (
                <p className="text-muted-foreground/70 -mt-1 text-[12px] font-medium">
                    Terakhir dicetak oleh {info.lastPrintedBy ?? '—'} · {formatDateTime(info.lastPrintedAt)}
                </p>
            )}
            <p className="text-muted-foreground/70 -mt-1 text-[12px] font-medium">
                Tombol di bawah hanya untuk melihat dokumen (tidak tercatat). Untuk mencetak resmi, gunakan tombol "Cetak" di pojok kanan atas.
            </p>
            <button
                type="button"
                onClick={() => setPreviewOpen(true)}
                className="border-border-soft bg-background text-foreground flex h-11 items-center justify-center gap-2 rounded-xl border text-[14px] font-bold sm:w-auto sm:self-end sm:px-6"
            >
                <Eye className="size-4" strokeWidth={2.2} />
                Preview {info.label}
            </button>
            {previewOpen && (
                <Suspense fallback={null}>
                    <PdfViewer
                        url={`/batches/${batchId}/print/${info.stage}/preview`}
                        title={`Preview ${info.label}`}
                        onClose={() => setPreviewOpen(false)}
                    />
                </Suspense>
            )}
        </div>
    );
}

const DECISION_LABELS: Record<string, string> = { Revision: 'Minta Revisi' };

const L2_DECISIONS = ['Approved', 'Rejected'];

function decisionBadgeClass(decision: string | null | undefined): string {
    if (decision === 'Approved') return 'bg-green-100 text-green-800';
    if (!decision) return 'bg-muted text-muted-foreground';
    return 'bg-red-100 text-red-700';
}

function LevelSummary({
    title,
    decision,
    by,
    at,
    remarks,
}: {
    title: string;
    decision?: string | null;
    by?: string | null;
    at?: string | null;
    remarks?: string | null;
}) {
    return (
        <div className="border-border-soft bg-background flex flex-col gap-1 rounded-xl border p-3">
            <div className="flex items-center justify-between gap-2">
                <p className="text-[12.5px] font-bold">{title}</p>
                <span className={`rounded-full px-2.5 py-0.5 text-[11.5px] font-bold ${decisionBadgeClass(decision)}`}>
                    {decision ? (DECISION_LABELS[decision] ?? decision) : 'Belum diputuskan'}
                </span>
            </div>
            {decision && (
                <p className="text-muted-foreground/70 text-[12px] font-medium">
                    Oleh {by ?? '—'} · {formatDateTime(at ?? null)}
                </p>
            )}
            {decision && decision !== 'Approved' && remarks && <p className="text-[12.5px] whitespace-pre-line">{remarks}</p>}
        </div>
    );
}

/**
 * Two-level approval (2026-11-21): Level 1 = QC Verifikator, Level 2 = QC Team Leader / Part Leader /
 * Supervisor. Both must Approve before the stage counts as approved. Level 1 can re-decide until Level 2
 * approves (re-deciding clears Level 2); Level 2 decides once, only after Level 1 approved and never the
 * same person as Level 1. "Minta Revisi" belongs to Level 1.
 */
export function ApprovalActionCard({
    batchId,
    stage,
    decisions,
    reviseOptions,
}: {
    batchId: number;
    stage: StageInfo;
    decisions: string[];
    /** Combined reports only: the parts the approver can send back (a single-stage report sends itself back). */
    reviseOptions?: { value: string; label: string }[];
}) {
    const { auth, canApproveL1, canApproveL2 } = usePage<SharedData>().props;
    const existing = stage.approval;

    const l1Approved = existing?.decision === 'Approved';
    const fully = isFullyApproved(existing);
    const sameAsL1 = !!existing?.approver_user_id && existing.approver_user_id === auth.user?.id;

    // Level 1 may (re)decide until Level 2 has approved; Level 2 decides once, after Level 1 approved.
    const showL1Form = stage.ready && canApproveL1 && !fully;
    const showL2Form = stage.ready && canApproveL2 && l1Approved && !existing?.l2_decision && !sameAsL1;

    let waitingNote: string | null = null;
    if (!fully && stage.ready && !showL1Form && !showL2Form) {
        if (!l1Approved) waitingNote = 'Menunggu approval Level 1 (QC Verifikator).';
        else if (existing?.l2_decision === 'Rejected') waitingNote = 'Ditolak pada Level 2. Menunggu Level 1 memutuskan ulang.';
        else if (sameAsL1 && canApproveL2)
            waitingNote = 'Anda sudah memberi approval Level 1 pada bagian ini. Level 2 harus diputuskan oleh orang lain.';
        else waitingNote = 'Menunggu approval Level 2 (Team Leader / Supervisor).';
    }

    return (
        <div className="border-primary/20 bg-card flex flex-col gap-4 rounded-3xl border p-5 shadow-sm md:p-6">
            <div className="flex items-center justify-between gap-3">
                <p className="text-[14.5px] font-bold">Approval — {stage.label}</p>
                {fully && <span className="rounded-full bg-green-100 px-3 py-1 text-[12px] font-bold text-green-800">Approved (L1 + L2)</span>}
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <LevelSummary
                    title="Level 1 · QC Verifikator"
                    decision={existing?.decision}
                    by={existing?.approver?.name}
                    at={existing?.approved_at}
                    remarks={existing?.remarks}
                />
                <LevelSummary
                    title="Level 2 · Team Leader / Supervisor"
                    decision={existing?.l2_decision}
                    by={existing?.l2_approver?.name}
                    at={existing?.l2_decided_at}
                    remarks={existing?.l2_remarks}
                />
            </div>

            {!stage.ready && (
                <p className="text-muted-foreground/70 text-[13px] font-medium italic">
                    Selesaikan dulu tahap {stage.label} di atas sebelum bisa diputuskan.
                </p>
            )}

            {waitingNote && <p className="text-muted-foreground/80 text-[13px] font-medium italic">{waitingNote}</p>}

            {showL1Form && (
                <DecisionForm
                    key={`l1-${existing?.decision ?? 'none'}-${existing?.l2_decision ?? 'none'}`}
                    level={1}
                    batchId={batchId}
                    stage={stage}
                    decisions={decisions}
                    reviseOptions={reviseOptions}
                    initialDecision={existing?.decision ?? ''}
                    initialRemarks={existing?.remarks ?? ''}
                />
            )}
            {showL2Form && <DecisionForm level={2} batchId={batchId} stage={stage} decisions={L2_DECISIONS} initialDecision="" initialRemarks="" />}

            {(existing?.revisions?.length ?? 0) > 0 && (
                <RevisionHistoryCard
                    title="Riwayat Keputusan"
                    revisions={existing!.revisions!.map((rev) => ({ ...rev, finalize: true, created_at: rev.decided_at }))}
                    renderSummary={(rev) => (
                        <span className={rev.decision === 'Approved' ? 'font-bold text-green-700' : 'font-bold text-red-600'}>
                            Level {rev.level ?? 1} · Keputusan: {rev.decision}
                        </span>
                    )}
                    renderRemarks={(rev) => rev.remarks}
                />
            )}
        </div>
    );
}

function DecisionForm({
    level,
    batchId,
    stage,
    decisions,
    reviseOptions,
    initialDecision,
    initialRemarks,
}: {
    level: 1 | 2;
    batchId: number;
    stage: StageInfo;
    decisions: string[];
    reviseOptions?: { value: string; label: string }[];
    initialDecision: string;
    initialRemarks: string;
}) {
    const { toast, message } = useToast();

    const { data, setData, put, processing, errors, setError, clearErrors } = useForm<{
        level: number;
        decision: string;
        remarks: string;
        revise_stages: string[];
    }>({
        level,
        decision: initialDecision,
        remarks: initialRemarks,
        revise_stages: [],
    });

    const needsNote = data.decision === 'Rejected' || data.decision === 'Revision';
    const fieldId = `${stage.stage}-l${level}`;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!data.decision) {
            setError(
                'decision',
                level === 2
                    ? 'Pilih Approved untuk menyetujui atau Rejected untuk menolak.'
                    : 'Pilih Approved untuk menyetujui, Minta Revisi untuk dikembalikan ke QC, atau Rejected untuk menolak.',
            );
            toast('Pilih keputusan dulu.');
            return;
        }
        if (needsNote && !data.remarks.trim()) {
            setError(
                'remarks',
                data.decision === 'Revision'
                    ? 'Tuliskan bagian mana yang salah dan apa yang harus diperbaiki.'
                    : 'Jelaskan alasan penolakan agar petugas mengetahui hal yang perlu diperbaiki.',
            );
            document.getElementById(`approval-remarks-${fieldId}`)?.focus();
            return;
        }
        if (data.decision === 'Revision' && reviseOptions && data.revise_stages.length === 0) {
            setError('revise_stages', 'Pilih minimal satu bagian laporan yang perlu direvisi.');
            return;
        }
        put(`/batches/${batchId}/approval/${stage.stage}`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="border-border-soft flex flex-col gap-3 border-t pt-4">
            <Toast message={message} />
            <p className="text-[13.5px] font-bold">
                {level === 1 ? 'Keputusan Level 1 (QC Verifikator)' : 'Keputusan Level 2 (Team Leader / Supervisor)'}
            </p>
            <p className="text-muted-foreground text-xs leading-relaxed">
                {level === 1
                    ? 'Tinjau data pemeriksaan sebelum memilih keputusan. Jika Anda mengubah keputusan Level 1, keputusan Level 2 pada bagian ini dihapus dan harus diulang.'
                    : 'Level 1 sudah menyetujui. Tinjau ulang laporan sebelum memutuskan. Jika ditolak, Level 1 yang memutuskan ulang.'}
            </p>
            <ChipToggleGroup
                name="Keputusan approval"
                options={decisions}
                labels={DECISION_LABELS}
                value={data.decision}
                disabled={processing}
                onChange={(value) => {
                    setData('decision', value);
                    clearErrors('decision');
                    if (value !== 'Rejected' && value !== 'Revision') clearErrors('remarks');
                }}
            />
            <InputError message={errors.decision} />
            {data.decision === 'Revision' && (
                <div className="flex flex-col gap-2 rounded-xl border border-orange-300 bg-orange-50 p-3">
                    <p className="text-[12.5px] leading-relaxed font-medium text-orange-900">
                        Laporan dikembalikan ke QC. Bagian yang dipilih dibuka kembali untuk diedit; data lama tetap tersimpan di riwayat. Setelah QC
                        menyelesaikan ulang, laporan masuk antrean approval lagi.
                    </p>
                    {reviseOptions && (
                        <fieldset className="flex flex-wrap gap-2" disabled={processing}>
                            <legend className="mb-1 text-[12px] font-bold text-orange-900">Bagian yang direvisi</legend>
                            {reviseOptions.map((opt) => {
                                const checked = data.revise_stages.includes(opt.value);
                                return (
                                    <label
                                        key={opt.value}
                                        className="bg-card flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border px-3 text-[13px] font-bold"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() => {
                                                clearErrors('revise_stages');
                                                setData(
                                                    'revise_stages',
                                                    checked ? data.revise_stages.filter((s) => s !== opt.value) : [...data.revise_stages, opt.value],
                                                );
                                            }}
                                        />
                                        {opt.label}
                                    </label>
                                );
                            })}
                        </fieldset>
                    )}
                    <InputError message={errors.revise_stages} />
                </div>
            )}
            <Label htmlFor={`approval-remarks-${fieldId}`}>Catatan {needsNote ? '(wajib diisi)' : '(opsional)'}</Label>
            <Textarea
                id={`approval-remarks-${fieldId}`}
                aria-invalid={Boolean(errors.remarks)}
                aria-describedby={errors.remarks ? `approval-remarks-error-${fieldId}` : undefined}
                placeholder={level === 1 ? 'Catatan (wajib jika Revisi atau Rejected)' : 'Catatan (wajib jika Rejected)'}
                rows={2}
                className="border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px]"
                value={data.remarks}
                onChange={(e) => {
                    setData('remarks', e.target.value);
                    clearErrors('remarks');
                }}
                disabled={processing}
            />
            <InputError id={`approval-remarks-error-${fieldId}`} message={errors.remarks} />
            <button
                type="submit"
                disabled={processing}
                className="bg-primary flex h-11 w-full items-center justify-center rounded-xl text-[14px] font-bold text-white disabled:opacity-60 sm:w-auto sm:self-end sm:px-6"
            >
                {processing && <LoaderCircle className="mr-2 size-4 animate-spin" />}
                {processing ? 'Menyimpan...' : level === 1 ? 'Simpan Keputusan Level 1' : 'Simpan Keputusan Level 2'}
            </button>
        </form>
    );
}
