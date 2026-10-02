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
import { useTranslation } from '@/hooks/use-translation';

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
    const { t } = useTranslation();
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
                <Button>{t('report.pdf.button')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('report.pdf.title')}</DialogTitle>
                    <DialogDescription>
                        {t('report.pdf.description')}
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
                            <Label htmlFor="pdf-attachments">
                                {t('report.pdf.attachments')}
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                {t('report.pdf.attachments_hint')}
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
                                {t('report.pdf.line_configuration')}
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                {hasLineConfigurationReport
                                    ? t('report.pdf.line_configuration_hint')
                                    : t('report.pdf.line_configuration_none')}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">
                            {t('common.actions.cancel')}
                        </Button>
                    </DialogClose>
                    <Button asChild>
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={() => setOpen(false)}
                        >
                            {t('report.pdf.download')}
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
