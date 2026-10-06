import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Maximize, ZoomIn, ZoomOut } from 'lucide-react';
import { useState } from 'react';

export function PhotoLightbox({
    src,
    alt,
    triggerClassName = '',
    imageClassName = '',
}: {
    readonly src: string;
    readonly alt: string;
    readonly triggerClassName?: string;
    readonly imageClassName?: string;
}) {
    const [open, setOpen] = useState(false);
    const [zoom, setZoom] = useState(1);

    function handleOpenChange(nextOpen: boolean) {
        setOpen(nextOpen);
        if (!nextOpen) setZoom(1);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label={`Perbesar foto: ${alt}`}
                className={`focus-visible:ring-ring inline-flex max-w-full overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:ring-offset-2 ${triggerClassName}`}
            >
                <img src={src} alt={alt} className={imageClassName} />
            </button>
            <DialogContent className="flex h-[94dvh] w-[96vw] max-w-none grid-rows-[auto_minmax(0,1fr)] gap-3 overflow-hidden border-neutral-700 bg-neutral-950 p-3 text-white sm:rounded-lg sm:p-4">
                <div className="flex min-w-0 items-center gap-2 pr-10">
                    <DialogTitle className="min-w-0 flex-1 truncate text-left text-sm font-semibold text-white">{alt}</DialogTitle>
                    <button
                        type="button"
                        onClick={() => setZoom((value) => Math.max(0.25, Math.round((value - 0.25) * 100) / 100))}
                        disabled={zoom <= 0.25}
                        aria-label="Zoom out"
                        title="Zoom out"
                        className="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-white/20 hover:bg-white/10 disabled:opacity-40"
                    >
                        <ZoomOut className="size-4" />
                    </button>
                    <span className="w-12 shrink-0 text-center text-xs text-white/75 tabular-nums">{Math.round(zoom * 100)}%</span>
                    <button
                        type="button"
                        onClick={() => setZoom((value) => Math.min(4, Math.round((value + 0.25) * 100) / 100))}
                        disabled={zoom >= 4}
                        aria-label="Zoom in"
                        title="Zoom in"
                        className="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-white/20 hover:bg-white/10 disabled:opacity-40"
                    >
                        <ZoomIn className="size-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setZoom(1)}
                        aria-label="Fit layar"
                        title="Fit layar"
                        className="inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-white/20 hover:bg-white/10"
                    >
                        <Maximize className="size-4" />
                    </button>
                </div>
                <div className="grid min-h-0 min-w-0 place-items-center overflow-auto rounded-md bg-black/40">
                    <img
                        src={src}
                        alt={alt}
                        draggable={false}
                        className="select-none"
                        style={{ width: `${zoom * 100}%`, height: `${zoom * 100}%`, objectFit: 'contain' }}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
