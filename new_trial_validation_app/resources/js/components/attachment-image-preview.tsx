import { useState } from 'react';
import { ZoomIn } from 'lucide-react';
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

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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
            <DialogContent className="max-h-[95vh] max-w-[min(95vw,1200px)] overflow-auto p-4 sm:p-6">
                <DialogTitle className="pr-8">{caption || fileName}</DialogTitle>
                <DialogDescription className="break-all">
                    {caption ? fileName : 'Klik di luar gambar atau tekan Escape untuk menutup.'}
                </DialogDescription>
                <img
                    src={src}
                    alt={alt}
                    onError={handleAttachmentImageError}
                    className="max-h-[calc(95vh-9rem)] w-full rounded object-contain"
                />
                {caption && (
                    <p className="whitespace-pre-wrap text-sm text-muted-foreground">
                        {caption}
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
