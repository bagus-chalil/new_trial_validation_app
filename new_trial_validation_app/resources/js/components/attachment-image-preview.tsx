import { Maximize2, RotateCcw, ZoomIn, ZoomOut } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { handleAttachmentImageError } from '@/lib/image-fallback';

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
    const [open, setOpen] = useState(false);
    const [zoom, setZoom] = useState(1);
    const [fitToScreen, setFitToScreen] = useState(true);
    const [position, setPosition] = useState({ x: 0, y: 0 });
    const dragStart = useRef({ x: 0, y: 0 });
    const startPosition = useRef({ x: 0, y: 0 });
    const isDragging = useRef(false);

    function resetPreview() {
        setZoom(1);
        setFitToScreen(false);
        setPosition({ x: 0, y: 0 });
    }

    function fitPreview() {
        setZoom(1);
        setFitToScreen(true);
        setPosition({ x: 0, y: 0 });
    }

    function changeZoom(amount: number) {
        setFitToScreen(false);
        setPosition({ x: 0, y: 0 });
        setZoom((current) =>
            Math.min(3, Math.max(0.5, Number((current + amount).toFixed(2)))),
        );
    }

    function startDragging(event: React.PointerEvent<HTMLDivElement>) {
        if (fitToScreen || zoom <= 1) {
            return;
        }

        isDragging.current = true;
        dragStart.current = { x: event.clientX, y: event.clientY };
        startPosition.current = position;
        event.currentTarget.setPointerCapture(event.pointerId);
    }

    function dragImage(event: React.PointerEvent<HTMLDivElement>) {
        if (!isDragging.current) {
            return;
        }

        setPosition({
            x: startPosition.current.x + event.clientX - dragStart.current.x,
            y: startPosition.current.y + event.clientY - dragStart.current.y,
        });
    }

    function stopDragging(event: React.PointerEvent<HTMLDivElement>) {
        isDragging.current = false;
        event.currentTarget.releasePointerCapture?.(event.pointerId);
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (nextOpen) {
                    fitPreview();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="group relative h-auto w-full overflow-hidden rounded p-0"
                    aria-label={`Lihat detail gambar ${fileName}`}
                >
                    <img
                        src={src}
                        alt={alt}
                        onError={handleAttachmentImageError}
                        className={className}
                    />
                    <span className="absolute inset-0 flex items-center justify-center bg-black/0 text-white opacity-0 transition group-hover:bg-black/35 group-hover:opacity-100 group-focus-visible:bg-black/35 group-focus-visible:opacity-100">
                        <ZoomIn className="size-6" aria-hidden="true" />
                        <span className="sr-only">Zoom gambar</span>
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[95vh] max-w-[min(95vw,1200px)] overflow-hidden p-4 sm:p-6">
                <DialogTitle className="pr-8">{caption || fileName}</DialogTitle>
                <DialogDescription className="break-all">
                    {caption ? fileName : 'Klik di luar gambar atau tekan Escape untuk menutup.'}
                </DialogDescription>
                <div
                    className={`flex h-[min(65vh,700px)] items-center justify-center overflow-hidden rounded-md border bg-muted/30 p-2 ${fitToScreen || zoom <= 1 ? 'cursor-default' : 'cursor-grab active:cursor-grabbing'}`}
                    onPointerDown={startDragging}
                    onPointerMove={dragImage}
                    onPointerUp={stopDragging}
                    onPointerCancel={stopDragging}
                    onPointerLeave={stopDragging}
                >
                    <img
                        src={src}
                        alt={alt}
                        onError={handleAttachmentImageError}
                        draggable={false}
                        className="max-h-full max-w-full rounded object-contain select-none"
                        style={{
                            transform: `translate(${position.x}px, ${position.y}px) scale(${zoom})`,
                            transition: isDragging.current ? 'none' : 'transform 150ms ease-out',
                        }}
                    />
                </div>
                <div className="flex flex-wrap items-center justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => changeZoom(-0.25)}
                        disabled={zoom <= 0.5}
                        title="Zoom out"
                        aria-label="Zoom out"
                    >
                        <ZoomOut className="size-4" />
                    </Button>
                    <span className="min-w-14 text-center text-sm tabular-nums text-muted-foreground">
                        {fitToScreen ? 'Fit' : `${Math.round(zoom * 100)}%`}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => changeZoom(0.25)}
                        disabled={zoom >= 3}
                        title="Zoom in"
                        aria-label="Zoom in"
                    >
                        <ZoomIn className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={fitPreview}
                        disabled={fitToScreen}
                        title="Fit to screen"
                    >
                        <Maximize2 className="size-4" />
                        Fit
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={resetPreview}
                        disabled={!fitToScreen && zoom === 1}
                        title="Reset default"
                    >
                        <RotateCcw className="size-4" />
                        Reset
                    </Button>
                </div>
                {caption && (
                    <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                        {caption}
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
