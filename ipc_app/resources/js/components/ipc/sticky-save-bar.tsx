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
        <div className="border-border-soft bg-card/95 sticky bottom-0 flex flex-col gap-2 border-t px-5 py-3.5 backdrop-blur md:flex-row md:items-center md:justify-end md:gap-3.5 md:px-8">
            {note && <p className="text-muted-foreground/70 text-center text-xs font-medium md:text-right md:text-[12.5px]">{note}</p>}
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
                    {label}
                </button>
            </div>
        </div>
    );
}
