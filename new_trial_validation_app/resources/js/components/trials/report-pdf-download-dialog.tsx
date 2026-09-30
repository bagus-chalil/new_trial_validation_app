import { useState } from 'react';
import TrialReportController from '@/actions/App/Http/Controllers/TrialReportController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type Props = {
    trialId: number;
    hasLineConfigurationReport: boolean;
};

/**
 * Lets the user choose what goes into the trial report PDF: the trial data
 * is always included, attachments and the Line Configuration Report (latest
 * version only — see TrialReportController::pdf()) are optional.
 */
export function ReportPdfDownloadDialog({
    trialId,
    hasLineConfigurationReport,
}: Props) {
    const [open, setOpen] = useState(false);
    const [attachments, setAttachments] = useState(false);
    const [lineConfiguration, setLineConfiguration] = useState(false);

    const query: Record<string, number> = {};

    if (attachments) {
        query.attachments = 1;
    }

    if (lineConfiguration) {
        query.line_configuration = 1;
    }

    const href = TrialReportController.pdf(trialId, { query }).url;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>Unduh PDF</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Unduh PDF</DialogTitle>
                    <DialogDescription>
                        Data trial (header, validation, weighing, review, dan
                        keputusan) selalu disertakan. Pilih tambahan yang ingin
                        ikut diunduh.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="pdf-attachments"
                            checked={attachments}
                            onCheckedChange={(v) => setAttachments(v === true)}
                        />
                        <div className="space-y-1">
                            <Label htmlFor="pdf-attachments">Attachment</Label>
                            <p className="text-sm text-muted-foreground">
                                Foto evidence dan Additional Attachment
                                (gambar). File PDF hanya dicantumkan namanya.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="pdf-line-configuration"
                            checked={lineConfiguration}
                            disabled={!hasLineConfigurationReport}
                            onCheckedChange={(v) =>
                                setLineConfiguration(v === true)
                            }
                        />
                        <div className="space-y-1">
                            <Label htmlFor="pdf-line-configuration">
                                Line Configuration Report
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                {hasLineConfigurationReport
                                    ? 'Hanya versi paling baru. Versi lama tetap bisa diunduh satu per satu dari bagian Line Configuration.'
                                    : 'Trial ini belum punya Line Configuration Report.'}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Batal</Button>
                    </DialogClose>
                    <Button asChild>
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={() => setOpen(false)}
                        >
                            Unduh
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
