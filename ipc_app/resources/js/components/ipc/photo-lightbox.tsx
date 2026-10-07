import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Maximize2, RotateCcw, X, ZoomIn, ZoomOut } from 'lucide-react';
import { type PointerEvent as ReactPointerEvent, type WheelEvent as ReactWheelEvent, useRef, useState } from 'react';

const MIN_ZOOM = 1;
const MAX_ZOOM = 6;
const STEP = 0.5;

interface View {
    zoom: number;
    x: number;
    y: number;
}

const FIT: View = { zoom: 1, x: 0, y: 0 };

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

/**
 * Thumbnail that opens a tablet-friendly viewer: tap outside / X / Escape closes it, pinch or the
 * +/- buttons zoom, one finger drags when zoomed in, double-tap toggles zoom, Fit resets zoom to
 * the screen and Reset also re-centres the image.
 */
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
    const [view, setView] = useState<View>(FIT);

    // Active pointers (for pinch), the gesture's starting snapshot, and the last tap time.
    const pointers = useRef(new Map<number, { x: number; y: number }>());
    const gesture = useRef<{ dist: number; view: View; origin: { x: number; y: number } } | null>(null);
    const lastTap = useRef(0);
    const moved = useRef(false);

    function handleOpenChange(nextOpen: boolean) {
        setOpen(nextOpen);
        if (!nextOpen) setView(FIT);
    }

    function zoomTo(next: number) {
        setView((v) => {
            const zoom = clamp(Math.round(next * 100) / 100, MIN_ZOOM, MAX_ZOOM);
            return zoom === MIN_ZOOM ? FIT : { ...v, zoom };
        });
    }

    function onPointerDown(e: ReactPointerEvent<HTMLDivElement>) {
        e.currentTarget.setPointerCapture(e.pointerId);
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        moved.current = false;
        const pts = [...pointers.current.values()];
        gesture.current = {
            dist: pts.length === 2 ? Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y) : 0,
            view,
            origin: { x: e.clientX, y: e.clientY },
        };
    }

    function onPointerMove(e: ReactPointerEvent<HTMLDivElement>) {
        const g = gesture.current;
        if (!pointers.current.has(e.pointerId) || !g) return;
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const pts = [...pointers.current.values()];

        if (pts.length === 2 && g.dist > 0) {
            const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            moved.current = true;
            const zoom = clamp(g.view.zoom * (dist / g.dist), MIN_ZOOM, MAX_ZOOM);
            setView(zoom === MIN_ZOOM ? FIT : { ...g.view, zoom });
        } else if (pts.length === 1 && g.view.zoom > MIN_ZOOM) {
            const dx = e.clientX - g.origin.x;
            const dy = e.clientY - g.origin.y;
            if (Math.abs(dx) + Math.abs(dy) > 3) moved.current = true;
            setView({ zoom: g.view.zoom, x: g.view.x + dx, y: g.view.y + dy });
        }
    }

    function onPointerUp(e: ReactPointerEvent<HTMLDivElement>) {
        pointers.current.delete(e.pointerId);
        gesture.current = null;

        if (pointers.current.size === 0 && !moved.current) {
            const now = Date.now();
            if (now - lastTap.current < 300) {
                setView((v) => (v.zoom > MIN_ZOOM ? FIT : { zoom: 2.5, x: 0, y: 0 }));
                lastTap.current = 0;
            } else {
                lastTap.current = now;
            }
        }
    }

    function onWheel(e: ReactWheelEvent<HTMLDivElement>) {
        zoomTo(view.zoom + (e.deltaY < 0 ? STEP : -STEP));
    }

    const btn =
        'inline-flex size-12 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-white active:bg-white/25 disabled:opacity-40';

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
            <DialogContent className="flex h-[96dvh] w-[98vw] max-w-none flex-col gap-3 overflow-hidden border-neutral-700 bg-neutral-950 p-3 text-white sm:rounded-2xl [&>button.absolute]:hidden">
                <div className="flex min-w-0 items-center gap-2">
                    <DialogTitle className="min-w-0 flex-1 truncate text-left text-base font-semibold text-white">{alt}</DialogTitle>
                    <DialogDescription className="sr-only">
                        Cubit dua jari atau ketuk dua kali untuk zoom, geser untuk memindahkan foto.
                    </DialogDescription>
                    <button type="button" onClick={() => setOpen(false)} aria-label="Tutup" className={btn}>
                        <X className="size-5" />
                    </button>
                </div>

                <div
                    className="relative min-h-0 flex-1 touch-none overflow-hidden rounded-xl bg-black/50 select-none"
                    style={{ cursor: view.zoom > MIN_ZOOM ? 'grab' : 'zoom-in' }}
                    onPointerDown={onPointerDown}
                    onPointerMove={onPointerMove}
                    onPointerUp={onPointerUp}
                    onPointerCancel={onPointerUp}
                    onWheel={onWheel}
                    onClick={(e) => {
                        // Tapping the empty backdrop around the image closes the viewer.
                        if (e.target === e.currentTarget && !moved.current) setOpen(false);
                    }}
                >
                    <img
                        src={src}
                        alt={alt}
                        draggable={false}
                        className="pointer-events-none absolute inset-0 size-full object-contain"
                        style={{
                            transform: `translate(${view.x}px, ${view.y}px) scale(${view.zoom})`,
                            transition: gesture.current ? 'none' : 'transform 120ms ease-out',
                        }}
                    />
                </div>

                <div className="flex shrink-0 items-center justify-center gap-2">
                    <button
                        type="button"
                        onClick={() => zoomTo(view.zoom - STEP)}
                        disabled={view.zoom <= MIN_ZOOM}
                        aria-label="Perkecil"
                        className={btn}
                    >
                        <ZoomOut className="size-5" />
                    </button>
                    <span className="w-16 text-center text-sm font-semibold text-white/80 tabular-nums">
                        {view.zoom === MIN_ZOOM ? 'Fit' : `${Math.round(view.zoom * 100)}%`}
                    </span>
                    <button
                        type="button"
                        onClick={() => zoomTo(view.zoom + STEP)}
                        disabled={view.zoom >= MAX_ZOOM}
                        aria-label="Perbesar"
                        className={btn}
                    >
                        <ZoomIn className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setView(FIT)}
                        disabled={view.zoom === MIN_ZOOM}
                        className={`${btn} w-auto gap-2 px-4 text-sm font-semibold`}
                    >
                        <Maximize2 className="size-4" /> Fit
                    </button>
                    <button type="button" onClick={() => setView(FIT)} className={`${btn} w-auto gap-2 px-4 text-sm font-semibold`}>
                        <RotateCcw className="size-4" /> Reset
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
