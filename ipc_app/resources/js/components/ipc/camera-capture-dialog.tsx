import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useEffect, useRef, useState } from 'react';

// Deliberately does NOT fall back to a plain <input type="file"> picker: the whole point is to
// guarantee the photo comes straight from a live camera frame at capture time, not a
// pre-existing/edited image chosen from the gallery — a direct user request (2026-09-03,
// concerned about QC evidence photos being manipulable if a file picker were allowed).
async function openCameraStream(): Promise<MediaStream> {
    try {
        return await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    } catch {
        return await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
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

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d')?.drawImage(video, 0, 0);

        canvas.toBlob(
            (blob) => {
                if (!blob) return;
                setPreviewBlob(blob);
                setPreviewUrl(URL.createObjectURL(blob));
                stopStream();
            },
            'image/jpeg',
            0.9,
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
                {previewUrl && <img src={previewUrl} alt="Preview foto" className="aspect-video w-full rounded-xl object-cover" />}

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
