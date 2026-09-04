import InputError from '@/components/input-error';
import { ChipToggleGroup, StatusChip } from '@/components/ipc/chip-toggle-group';
import { Toast, useToast } from '@/components/ipc/toast';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { FormEventHandler, type ReactNode } from 'react';

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

export interface ApprovalData {
    decision: string | null;
    remarks: string | null;
    approved_at: string | null;
    approver?: { name: string } | null;
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

export function PhotoRow({ photos }: { photos: { key: string; label: string; url: string | null }[] }) {
    return (
        <div className="border-border-soft bg-card grid grid-cols-2 gap-4 rounded-[20px] border p-[18px] sm:grid-cols-3 md:grid-cols-5">
            {photos.map(({ key, label, url }) => (
                <div key={key} className="flex flex-col gap-2">
                    <p className="text-muted-foreground text-xs font-semibold">{label}</p>
                    {url ? (
                        <img src={url} alt={label} className="border-border h-24 w-full rounded-xl border object-cover" />
                    ) : (
                        <div className="border-border-soft bg-background text-muted-foreground/50 flex h-24 w-full items-center justify-center rounded-xl border border-dashed text-[11.5px] font-medium italic">
                            Belum ada foto
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

/** Header "Preview Cetak" button — opens the Browsershot-rendered PDF twin of the page in a new tab. */
export function PrintPreviewButton({ href }: { href: string }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="border-border-soft bg-card text-foreground flex h-11 items-center gap-2 rounded-2xl border px-4 text-[13px] font-bold"
        >
            <Printer className="size-4" strokeWidth={2.2} />
            <span className="hidden sm:inline">Preview Cetak</span>
        </a>
    );
}

export function ApprovalActionCard({ batchId, stage, decisions }: { batchId: number; stage: StageInfo; decisions: string[] }) {
    const { toast, message } = useToast();
    const existing = stage.approval;

    const { data, setData, put, processing, errors } = useForm<{ decision: string; remarks: string }>({
        decision: existing?.decision ?? '',
        remarks: existing?.remarks ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!data.decision) {
            toast('Pilih keputusan (Approved/Rejected) dulu.');
            return;
        }
        put(`/batches/${batchId}/approval/${stage.stage}`, { preserveScroll: true });
    };

    return (
        <div className="border-border-soft bg-card flex flex-col gap-3.5 rounded-[20px] border p-[18px]">
            <Toast message={message} />
            <div className="flex items-center justify-between gap-3">
                <p className="text-[14.5px] font-bold">Approval — {stage.label}</p>
                {existing?.decision && (
                    <span
                        className={`rounded-full px-3 py-1 text-[12px] font-bold ${
                            existing.decision === 'Approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'
                        }`}
                    >
                        {existing.decision}
                    </span>
                )}
            </div>

            {existing && (
                <p className="text-muted-foreground/70 -mt-2 text-[12px] font-medium">
                    Oleh {existing.approver?.name ?? '—'} · {formatDateTime(existing.approved_at)}
                </p>
            )}

            {!stage.ready ? (
                <p className="text-muted-foreground/70 text-[13px] font-medium italic">
                    Selesaikan dulu tahap {stage.label} di atas sebelum bisa diputuskan.
                </p>
            ) : (
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <ChipToggleGroup name="decision" options={decisions} value={data.decision} onChange={(value) => setData('decision', value)} />
                    <InputError message={errors.decision} />
                    <Textarea
                        placeholder="Catatan (wajib jika Rejected)"
                        rows={2}
                        className="border-border bg-background resize-none rounded-xl border-[1.5px] text-[14px]"
                        value={data.remarks}
                        onChange={(e) => setData('remarks', e.target.value)}
                    />
                    <InputError message={errors.remarks} />
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-primary flex h-11 w-full items-center justify-center rounded-xl text-[14px] font-bold text-white disabled:opacity-60 sm:w-auto sm:self-end sm:px-6"
                    >
                        Simpan Keputusan
                    </button>
                </form>
            )}
        </div>
    );
}
