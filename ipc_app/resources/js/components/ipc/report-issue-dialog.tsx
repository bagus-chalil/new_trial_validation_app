import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { reportClientEvent } from '@/lib/client-reporter';
import { useState } from 'react';

/**
 * "Lapor Kendala": for the problems that produce no technical error at all (a button that feels dead,
 * a screen that looks wrong). The page, device and connection facts are attached automatically.
 */
export function ReportIssueDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    const [text, setText] = useState('');
    const [sent, setSent] = useState(false);

    const close = (next: boolean) => {
        onOpenChange(next);
        if (!next) {
            setSent(false);
            setText('');
        }
    };

    const submit = () => {
        if (text.trim().length < 5) return;
        reportClientEvent('user_report', text.trim());
        setSent(true);
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Lapor Kendala</DialogTitle>
                    <DialogDescription>Ceritakan singkat apa yang terjadi. Halaman, perangkat, dan koneksi ikut terkirim otomatis.</DialogDescription>
                </DialogHeader>
                {sent ? (
                    <div className="flex flex-col gap-4">
                        <p className="text-sm font-medium text-green-700">Terima kasih, laporan sudah dikirim ke admin.</p>
                        <Button type="button" onClick={() => close(false)}>
                            Tutup
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4">
                        <Textarea
                            value={text}
                            onChange={(event) => setText(event.target.value)}
                            rows={4}
                            maxLength={500}
                            placeholder="Contoh: tombol Ambil Foto tidak merespon di halaman Packing Check"
                        />
                        <Button type="button" onClick={submit} disabled={text.trim().length < 5}>
                            Kirim Laporan
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
