import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import { CheckCircle2, ChevronDown } from 'lucide-react';
import { type ReactNode, useState } from 'react';

export function AccordionCard({
    title,
    progress,
    complete = false,
    defaultOpen = true,
    children,
}: {
    title: string;
    progress?: string;
    complete?: boolean;
    defaultOpen?: boolean;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="border-border-soft bg-card overflow-hidden rounded-[20px] border">
            <CollapsibleTrigger className="flex w-full items-center justify-between gap-3 px-[18px] py-4 text-left">
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
            <CollapsibleContent className="px-[18px] pb-[18px]">
                <div className="grid gap-3.5 md:grid-cols-2 md:gap-x-[18px] md:gap-y-3.5">{children}</div>
            </CollapsibleContent>
        </Collapsible>
    );
}
