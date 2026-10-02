import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { Archive, ArchiveRestore, MoreVertical, Trash2 } from 'lucide-react';
import { useState } from 'react';

export interface BatchActionTarget {
    id: number;
    no_batch: string;
    current_stage: string;
    archived_at?: string | null;
}

/**
 * Admin-only ⋮ menu on a batch card: Arsipkan (finished batches only), Keluarkan dari Arsip,
 * and Hapus (soft delete → Tempat Sampah, restorable there).
 */
export function BatchActionsMenu({ batch }: { batch: BatchActionTarget }) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    const archived = !!batch.archived_at;
    const canArchive = !archived && batch.current_stage === 'completed';

    const visit = (method: 'post' | 'delete', url: string, onSuccess?: () => void) =>
        router.visit(url, {
            method,
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess,
        });

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label={`Aksi batch ${batch.no_batch}`}
                        className="text-muted-foreground hover:bg-muted relative z-10 -mr-1.5 flex size-9 shrink-0 items-center justify-center rounded-xl"
                    >
                        <MoreVertical className="size-[18px]" strokeWidth={2} />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                    {canArchive && (
                        <DropdownMenuItem disabled={processing} onSelect={() => visit('post', route('archive.store', batch.id))}>
                            <Archive className="size-4" />
                            Pindahkan ke Arsip
                        </DropdownMenuItem>
                    )}
                    {archived && (
                        <DropdownMenuItem disabled={processing} onSelect={() => visit('delete', route('archive.destroy', batch.id))}>
                            <ArchiveRestore className="size-4" />
                            Keluarkan dari Arsip
                        </DropdownMenuItem>
                    )}
                    {(canArchive || archived) && <DropdownMenuSeparator />}
                    <DropdownMenuItem
                        className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                        onSelect={() => setConfirmDelete(true)}
                    >
                        <Trash2 className="size-4" />
                        Hapus
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus batch {batch.no_batch}?</DialogTitle>
                        <DialogDescription>
                            Batch akan dipindahkan ke Tempat Sampah dan tidak tampil lagi di daftar. Data pemeriksaannya tetap tersimpan dan bisa
                            dikembalikan dari menu Tempat Sampah.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setConfirmDelete(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={processing}
                            onClick={() => visit('delete', route('batches.destroy', batch.id), () => setConfirmDelete(false))}
                        >
                            Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
