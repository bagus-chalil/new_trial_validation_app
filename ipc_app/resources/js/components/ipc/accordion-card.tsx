import { useWorkflowSection } from '@/components/ipc/workflow-form';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { CheckCircle2, ChevronDown } from 'lucide-react';
import { type ReactNode, useEffect, useId, useState } from 'react';

export function AccordionCard({
    title,
    progress,
    complete = false,
    defaultOpen = true,
    forceOpen = false,
    children,
}: {
    title: string;
    progress?: string;
    complete?: boolean;
    defaultOpen?: boolean;
    /** Flips the accordion open (without fighting a later manual close) when it starts/turns true — e.g. once it contains a field a failed validation flagged, so "required" never hides behind a collapsed section. */
    forceOpen?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen || forceOpen);
    const id = useId();
    useWorkflowSection({ id, title, complete, progress });
    const count = progress?.match(/(\d+)\/(\d+)/);
    const percent = count && Number(count[2]) > 0 ? Math.min(100, (Number(count[1]) / Number(count[2])) * 100) : null;
    const guidance =
        title === 'Ketersediaan'
            ? 'Periksa keberadaan setiap item. Pilih Available jika tersedia, atau Not Available jika belum tersedia.'
            : /conform|match/i.test(title)
              ? 'Bandingkan kondisi aktual dengan standar kerja. Conform berarti sesuai; Not Conform berarti ada ketidaksesuaian.'
              : /volume|weight|sample|sampling/i.test(title)
                ? 'Masukkan hasil pengukuran aktual sesuai urutan sampel. Perhatikan satuan dan standar yang tertera.'
                : /parameter/i.test(title)
                  ? 'Gunakan data produksi dan dokumen acuan. Pilih N/A hanya pada parameter yang memang tidak berlaku.'
                  : title === 'Keputusan'
                    ? 'Tinjau hasil pemeriksaan sebelum memilih keputusan akhir. Tambahkan catatan jika ada temuan.'
                    : null;

    useEffect(() => {
        if (forceOpen) setOpen(true);
    }, [forceOpen]);

    return (
        <Collapsible
            id={id}
            open={open}
            onOpenChange={setOpen}
            className="workflow-section border-border-soft bg-card scroll-mt-24 overflow-hidden rounded-3xl border shadow-[0_3px_18px_-12px_rgba(15,23,42,0.2)]"
        >
            <CollapsibleTrigger
                data-section-trigger
                className="hover:bg-muted/40 focus-visible:outline-primary flex w-full items-center justify-between gap-3 px-5 py-5 text-left transition-colors focus-visible:outline-2 md:px-6"
            >
                <span className="flex items-center gap-2.5">
                    <span className="text-[14.5px] font-bold">{title}</span>
                    {complete ? (
                        <span className="flex items-center gap-1 text-xs font-semibold text-green-700">
                            <CheckCircle2 className="size-3.5" strokeWidth={2.2} />
                            Selesai
                        </span>
                    ) : (
                        progress && <span className="text-muted-foreground/70 text-xs font-medium">{progress}</span>
                    )}
                </span>
                <span
                    className={cn(
                        'bg-background flex size-7 shrink-0 items-center justify-center rounded-full transition-transform',
                        open && 'rotate-180',
                    )}
                >
                    <ChevronDown className="text-muted-foreground size-3.5" strokeWidth={2.2} />
                </span>
            </CollapsibleTrigger>
            {percent !== null && (
                <div
                    className="bg-muted mx-5 h-1 overflow-hidden rounded-full md:mx-6"
                    role="progressbar"
                    aria-label={`Progres ${title}`}
                    aria-valuenow={Math.round(percent)}
                    aria-valuemin={0}
                    aria-valuemax={100}
                >
                    <div
                        className={cn('h-full rounded-full transition-[width] duration-300', complete ? 'bg-emerald-500' : 'bg-primary')}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
            <CollapsibleContent className="px-5 pt-4 pb-5 md:px-6 md:pb-6">
                {guidance && <p className="border-primary/30 text-muted-foreground mb-5 border-l-2 pl-3 text-xs leading-relaxed">{guidance}</p>}
                <div className="workflow-fields grid gap-5 md:grid-cols-2 md:gap-x-6 md:gap-y-5">{children}</div>
            </CollapsibleContent>
        </Collapsible>
    );
}
