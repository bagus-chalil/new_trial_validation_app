import { type ReactNode } from 'react';

export function StickySaveBar({ label, processing, note }: { label: string; processing: boolean; note?: ReactNode }) {
    return (
        <div className="border-border-soft bg-card/95 sticky bottom-0 flex flex-col gap-2 border-t px-5 py-3.5 backdrop-blur md:flex-row md:items-center md:justify-end md:gap-3.5 md:px-8">
            {note && <p className="text-muted-foreground/70 text-center text-xs font-medium md:text-right md:text-[12.5px]">{note}</p>}
            <button
                type="submit"
                disabled={processing}
                className="bg-primary flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl text-[15px] font-bold text-white disabled:opacity-60 md:h-[50px] md:w-auto md:px-[26px] md:text-[14.5px]"
            >
                {label}
            </button>
        </div>
    );
}
