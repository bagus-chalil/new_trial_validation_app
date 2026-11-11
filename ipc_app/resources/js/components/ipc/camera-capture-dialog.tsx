import { PhotoLightbox } from '@/components/ipc/photo-lightbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useEffect, useRef, useState } from 'react';

// Deliberately does NOT fall back to a plain <input type="file"> picker: the whole point is to
// guarantee the photo comes straight from a live camera frame at capture time, not a
// pre-existing/edited image chosen from the gallery — a direct user request (2026-09-03,
// concerned about QC evidence photos being manipulable if a file picker were allowed).
//
// Resolution: without width/height constraints browsers fall back to their default stream
// size (640x480 on most phones/tablets), which made small printed codes (EXP/batch coding,
// Label Bulk) too blurry for the Vision Service to read reliably — 139 of 144 sampled
// production photos were 480x640 (2026-11-11). We ask for ~1080p as an `ideal` (never
// `exact`/`min`, so devices that can't do it still open the camera at their best size), then
// cap the saved JPEG's long side at CAPTURE_MAX_LONG_SIDE to keep storage modest
// (~200-350 KB/photo instead of ~45 KB).
const CAPTURE_MAX_LONG_SIDE = 1600;
const CAPTURE_JPEG_QUALITY = 0.85;
const RESOLUTION_CONSTRAINTS = { width: { ideal: 1920 }, height: { ideal: 1080 } };

async function openCameraStream(): Promise<MediaStream> {
    try {
        return await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', ...RESOLUTION_CONSTRAINTS }, audio: false });
    } catch {
        try {
            return await navigator.mediaDevices.getUserMedia({ video: RESOLUTION_CONSTRAINTS, audio: false });
        } catch {
            return await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        }
    }
}

export function CameraCaptureDialog({
    open,
    onOpenChange,
    onCapture,
    title = 'Ambil Foto',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCapture: (file: File) => void;
    title?: string;
}) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [previewBlob, setPreviewBlob] = useState<Blob | null>(null);

    const stopStream = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    };

    const startStream = async () => {
        setError(null);
        try {
            const stream = await openCameraStream();
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }
        } catch {
            setError('Tidak bisa mengakses kamera. Pastikan izin kamera diaktifkan di browser.');
        }
    };

    useEffect(() => {
        if (!open) {
            stopStream();
            setPreviewUrl(null);
            setPreviewBlob(null);
            setError(null);
            return;
        }

        let cancelled = false;

        (async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                if (!cancelled) setError('Kamera tidak tersedia di perangkat/browser ini.');
                return;
            }
            await startStream();
        })();

        return () => {
            cancelled = true;
            stopStream();
        };
    }, [open]);

    const capture = () => {
        const video = videoRef.current;
        if (!video || video.videoWidth === 0) return;

        const scale = Math.min(1, CAPTURE_MAX_LONG_SIDE / Math.max(video.videoWidth, video.videoHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            (blob) => {
                if (!blob) return;
                setPreviewBlob(blob);
                setPreviewUrl(URL.createObjectURL(blob));
                stopStream();
            },
            'image/jpeg',
            CAPTURE_JPEG_QUALITY,
        );
    };

    const retake = () => {
        setPreviewUrl(null);
        setPreviewBlob(null);
        void startStream();
    };

    const confirm = () => {
        if (!previewBlob) return;
        onCapture(new File([previewBlob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' }));
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                {error && <p className="text-sm font-medium text-red-600">{error}</p>}

                {!error && !previewUrl && (
                    <video ref={videoRef} autoPlay playsInline muted className="aspect-video w-full rounded-xl bg-black object-cover" />
                )}
                {previewUrl && (
                    <PhotoLightbox
                        src={previewUrl}
                        alt="Preview foto"
                        triggerClassName="w-full"
                        imageClassName="aspect-video w-full rounded-xl object-cover"
                    />
                )}

                <div className="flex justify-end gap-2.5">
                    {!error && !previewUrl && (
                        <button
                            type="button"
                            onClick={capture}
                            className="bg-primary flex h-11 items-center justify-center rounded-xl px-5 text-sm font-bold text-white"
                        >
                            Ambil Foto
                        </button>
                    )}
                    {previewUrl && (
                        <>
                            <button
                                type="button"
                                onClick={retake}
                                className="border-border bg-background flex h-11 items-center justify-center rounded-xl border-[1.5px] px-5 text-sm font-bold"
                            >
                                Ambil Ulang
                            </button>
                            <button
                                type="button"
                                onClick={confirm}
                                className="bg-primary flex h-11 items-center justify-center rounded-xl px-5 text-sm font-bold text-white"
                            >
                                Gunakan Foto
                            </button>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
