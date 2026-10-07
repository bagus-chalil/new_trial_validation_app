import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { type ReactNode } from 'react';

export interface LatestRevisionSummary {
    revision_no: number;
    created_at: string;
    user?: { name: string } | null;
}

/**
 * Shown when "Selesaikan" is clicked on Filling / Packing / Finished Check once at least one TH
 * Progress round has been saved. QC can finalize with the latest saved round as-is — so a
 * process that really ended on a draft round doesn't force re-filling the form with no trial
 * left to measure — or finalize with what's currently in the form, as before.
 */
export function FinalizeChoiceDialog({
    open,
    onOpenChange,
    stageLabel,
    latestRevision,
    summary,
    processing,
    onUseHistory,
    onUseForm,
}: {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly stageLabel: string;
    readonly latestRevision: LatestRevisionSummary;
    readonly summary?: ReactNode;
    readonly processing: boolean;
    readonly onUseHistory: () => void;
    readonly onUseForm: () => void;
}) {
    const savedAt = new Date(latestRevision.created_at).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Selesaikan {stageLabel}?</DialogTitle>
                    <DialogDescription>
                        Proses bisa ditandai selesai memakai data TH Progress terakhir yang sudah tersimpan, tanpa mengisi form lagi.
                    </DialogDescription>
                </DialogHeader>

                <div className="border-border-soft bg-muted/30 rounded-xl border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-[13px] font-bold">Riwayat #{latestRevision.revision_no} (terakhir)</span>
                        <span className="text-muted-foreground text-[11.5px] font-medium">
                            {savedAt} · {latestRevision.user?.name ?? '—'}
                        </span>
                    </div>
                    {summary && <div className="text-muted-foreground mt-1.5 text-[12px]">{summary}</div>}
                </div>

                <DialogFooter className="flex-col gap-2 sm:flex-col sm:space-x-0">
                    <Button type="button" className="w-full" disabled={processing} onClick={onUseHistory}>
                        Selesaikan pakai riwayat #{latestRevision.revision_no}
                    </Button>
                    <Button type="button" variant="outline" className="w-full" disabled={processing} onClick={onUseForm}>
                        Selesaikan dengan isi form saat ini
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
