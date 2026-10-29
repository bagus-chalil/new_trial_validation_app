import { ArrowRight, LoaderCircle, Save } from 'lucide-react';
import { type ReactNode } from 'react';

export function StickySaveBar({
    label,
    processing,
    note,
    secondaryLabel,
    onSecondaryClick,
}: {
    label: string;
    processing: boolean;
    note?: ReactNode;
    secondaryLabel?: string;
    onSecondaryClick?: () => void;
}) {
    return (
        <div
            className="border-border bg-card/95 sticky bottom-0 z-30 mt-auto flex flex-col gap-3 border-t px-5 py-4 shadow-[0_-8px_30px_-18px_rgba(15,23,42,0.25)] backdrop-blur-xl md:flex-row md:items-center md:justify-between md:gap-5 md:px-8"
            aria-busy={processing}
        >
            {note && (
                <div className="text-muted-foreground flex items-center gap-2.5 text-xs font-medium md:max-w-[55%]">
                    <Save className="text-primary hidden size-4 shrink-0 md:block" />
                    <div>{note}</div>
                </div>
            )}
            <div className="flex flex-col gap-2 md:flex-row md:gap-2.5">
                {secondaryLabel && onSecondaryClick && (
                    <button
                        type="button"
                        disabled={processing}
                        onClick={onSecondaryClick}
                        className="border-border bg-background text-foreground flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl border-[1.5px] text-[15px] font-bold disabled:opacity-60 md:h-[50px] md:w-auto md:px-[26px] md:text-[14.5px]"
                    >
                        {secondaryLabel}
                    </button>
                )}
                <button
                    type="submit"
                    disabled={processing}
                    className="bg-primary flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl text-[15px] font-bold text-white disabled:opacity-60 md:h-[50px] md:w-auto md:px-[26px] md:text-[14.5px]"
                >
                    {processing ? <LoaderCircle className="size-4 animate-spin" /> : null}
                    {processing ? 'Menyimpan...' : label}
                    {!processing && <ArrowRight className="size-4" />}
                </button>
            </div>
        </div>
    );
}
