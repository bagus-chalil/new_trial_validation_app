import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Asked before every "Selesaikan" on Filling / Packing / Finished Check. Finalizing locks the
 * stage read-only, and the button sits right next to "Simpan Progress", so a mis-tap needs a
 * second, deliberate confirmation.
 */
export function FinalizeConfirmDialog({
    open,
    onOpenChange,
    stageLabel,
    description,
    onConfirm,
}: {
    readonly open: boolean;
    readonly onOpenChange: (open: boolean) => void;
    readonly stageLabel: string;
    /** Replaces the default locked-for-good text, for stages with a different hand-off. */
    readonly description?: string;
    readonly onConfirm: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Selesaikan {stageLabel}?</DialogTitle>
                    <DialogDescription>
                        {description ?? (
                            <>
                                Apakah Anda yakin ingin menyelesaikan {stageLabel}? Setelah selesai, data tidak bisa diubah lagi. Pilih &quot;Simpan
                                Progress&quot; jika masih ada pengecekan berikutnya.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Batal
                    </Button>
                    <Button type="button" onClick={onConfirm}>
                        Ya, Selesaikan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
