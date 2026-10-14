import { RefreshCw, RotateCcw, RotateCw, ZoomIn, ZoomOut } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { handleAttachmentImageError } from '@/lib/image-fallback';

const MIN_ZOOM = 0.5;
const MAX_ZOOM = 5;

type AttachmentImagePreviewProps = {
    src: string;
    alt: string;
    fileName: string;
    caption?: string | null;
    className?: string;
};

export function AttachmentImagePreview({
    src,
    alt,
    fileName,
    caption,
    className = 'aspect-square w-full rounded object-cover',
}: AttachmentImagePreviewProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [zoom, setZoom] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [fitToScreen, setFitToScreen] = useState(true);
    const [position, setPosition] = useState({ x: 0, y: 0 });
    const dragStart = useRef({ x: 0, y: 0 });
    const startPosition = useRef({ x: 0, y: 0 });
    const [isDragging, setIsDragging] = useState(false);

    // Rotation is view-only (never saved) — it just lets the viewer straighten
    // a photo that was taken sideways. One reset returns to the initial
    // fit-to-screen, unrotated state (fit and reset used to be two buttons
    // that did nearly the same thing).
    function resetPreview() {
        setZoom(1);
        setRotation(0);
        setFitToScreen(true);
        setPosition({ x: 0, y: 0 });
    }

    function changeZoom(amount: number) {
        setFitToScreen(false);
        setZoom((current) => {
            const next = Math.min(
                MAX_ZOOM,
                Math.max(MIN_ZOOM, Number((current + amount).toFixed(2))),
            );

            if (next <= 1) {
                setPosition({ x: 0, y: 0 });
            }

            return next;
        });
    }

    function rotate(delta: number) {
        setRotation((current) => (current + delta + 360) % 360);
        setPosition({ x: 0, y: 0 });
    }

    function zoomWithWheel(event: React.WheelEvent<HTMLDivElement>) {
        changeZoom(event.deltaY < 0 ? 0.25 : -0.25);
    }

    function startDragging(event: React.PointerEvent<HTMLDivElement>) {
        if (zoom <= 1) {
            return;
        }

        setIsDragging(true);
        dragStart.current = { x: event.clientX, y: event.clientY };
        startPosition.current = position;
        event.currentTarget.setPointerCapture(event.pointerId);
    }

    function dragImage(event: React.PointerEvent<HTMLDivElement>) {
        if (!isDragging) {
            return;
        }

        setPosition({
            x: startPosition.current.x + event.clientX - dragStart.current.x,
            y: startPosition.current.y + event.clientY - dragStart.current.y,
        });
    }

    function stopDragging(event: React.PointerEvent<HTMLDivElement>) {
        setIsDragging(false);
        event.currentTarget.releasePointerCapture?.(event.pointerId);
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (nextOpen) {
                    resetPreview();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="group relative h-auto w-full overflow-hidden rounded p-0"
                    aria-label={t('common.image_preview.view', {
                        name: fileName,
                    })}
                >
                    <img
                        src={src}
                        alt={alt}
                        onError={handleAttachmentImageError}
                        className={className}
                    />
                    <span className="absolute inset-0 flex items-center justify-center bg-black/0 text-white opacity-0 transition group-hover:bg-black/35 group-hover:opacity-100 group-focus-visible:bg-black/35 group-focus-visible:opacity-100">
                        <ZoomIn className="size-6" aria-hidden="true" />
                        <span className="sr-only">
                            {t('common.image_preview.zoom')}
                        </span>
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[96vh] max-w-[96vw] overflow-hidden p-4 sm:max-w-[96vw] sm:p-6">
                <DialogTitle className="pr-8">
                    {caption || fileName}
                </DialogTitle>
                <DialogDescription className="break-all">
                    {caption ? fileName : t('common.image_preview.close_hint')}
                </DialogDescription>
                <div
                    className={`[container-type:size] flex h-[72vh] items-center justify-center overflow-hidden rounded-md border bg-muted/30 p-2 ${zoom <= 1 ? 'cursor-default' : 'cursor-grab active:cursor-grabbing'}`}
                    onPointerDown={startDragging}
                    onPointerMove={dragImage}
                    onPointerUp={stopDragging}
                    onPointerCancel={stopDragging}
                    onPointerLeave={stopDragging}
                    onWheel={zoomWithWheel}
                >
                    <img
                        src={src}
                        alt={alt}
                        onError={handleAttachmentImageError}
                        draggable={false}
                        className="rounded object-contain select-none"
                        style={{
                            // A quarter-turn swaps the image's width/height,
                            // so the fit box is swapped too (container query
                            // units) — otherwise a rotated photo would be
                            // clipped by the viewport.
                            maxWidth: rotation % 180 === 0 ? '100%' : '100cqh',
                            maxHeight: rotation % 180 === 0 ? '100%' : '100cqw',
                            transform: `translate(${position.x}px, ${position.y}px) rotate(${rotation}deg) scale(${zoom})`,
                            transition: isDragging
                                ? 'none'
                                : 'transform 150ms ease-out',
                        }}
                    />
                </div>
                <div className="flex flex-wrap items-center justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => changeZoom(-0.25)}
                        disabled={zoom <= MIN_ZOOM}
                        title={t('common.image_preview.zoom_out')}
                        aria-label={t('common.image_preview.zoom_out')}
                    >
                        <ZoomOut className="size-4" />
                    </Button>
                    <span className="min-w-14 text-center text-sm text-muted-foreground tabular-nums">
                        {fitToScreen
                            ? t('common.image_preview.fit')
                            : `${Math.round(zoom * 100)}%`}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => changeZoom(0.25)}
                        disabled={zoom >= MAX_ZOOM}
                        title={t('common.image_preview.zoom_in')}
                        aria-label={t('common.image_preview.zoom_in')}
                    >
                        <ZoomIn className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => rotate(-90)}
                        title={t('common.image_preview.rotate_left')}
                        aria-label={t('common.image_preview.rotate_left')}
                    >
                        <RotateCcw className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => rotate(90)}
                        title={t('common.image_preview.rotate_right')}
                        aria-label={t('common.image_preview.rotate_right')}
                    >
                        <RotateCw className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={resetPreview}
                        disabled={fitToScreen && rotation === 0}
                        title={t('common.image_preview.reset_title')}
                    >
                        <RefreshCw className="size-4" />
                        {t('common.image_preview.reset')}
                    </Button>
                </div>
                {caption && (
                    <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                        {caption}
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
