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
// production photos were 480x640 (2026-11-11). We ask for a resolution as an `ideal` (never
// `exact`/`min`, so devices that can't do it still open the camera at their best size), then
// cap the saved JPEG's long side to keep storage modest.
//
// Camera aids (2026-11-12, user request: help admins take sharper photos): bigger dialog,
// camera picker, focus (tap-to-refocus, manual distance), zoom, torch and a quality choice.
// Every aid is shown ONLY when the browser/device reports it in track.getCapabilities() and is
// applied with try/catch, so a device without it (or an old Chrome) simply does not see it.
// The photo still always comes from the live frame.
const QUALITY = {
    standard: { label: 'Standar (1080p)', width: 1920, height: 1080, maxLongSide: 1600 },
    high: { label: 'Tinggi (maks. kamera)', width: 3840, height: 2160, maxLongSide: 2048 },
} as const;
type QualityKey = keyof typeof QUALITY;

const CAPTURE_JPEG_QUALITY = 0.88;
const STORAGE_DEVICE = 'ipc-camera-device';
const STORAGE_QUALITY = 'ipc-camera-quality';

// getCapabilities() types in older TypeScript DOM libs omit these fields.
type Range = { min: number; max: number; step?: number };
type ExtendedCapabilities = {
    focusMode?: string[];
    focusDistance?: Range;
    zoom?: Range;
    torch?: boolean;
};

function readStored(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStored(key: string, value: string) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // private mode / blocked storage: just don't remember the choice
    }
}

async function openCameraStream(deviceId: string | null, quality: QualityKey): Promise<MediaStream> {
    const size = { width: { ideal: QUALITY[quality].width }, height: { ideal: QUALITY[quality].height } };
    const attempts: MediaStreamConstraints[] = [];
    if (deviceId) attempts.push({ video: { deviceId: { exact: deviceId }, ...size }, audio: false });
    attempts.push({ video: { facingMode: 'environment', ...size }, audio: false }, { video: size, audio: false }, { video: true, audio: false });

    let lastError: unknown;
    for (const constraints of attempts) {
        try {
            return await navigator.mediaDevices.getUserMedia(constraints);
        } catch (error) {
            lastError = error;
        }
    }
    throw lastError;
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

    const [devices, setDevices] = useState<MediaDeviceInfo[]>([]);
    const [deviceId, setDeviceId] = useState<string | null>(() => readStored(STORAGE_DEVICE));
    const [quality, setQuality] = useState<QualityKey>(() => (readStored(STORAGE_QUALITY) === 'high' ? 'high' : 'standard'));
    const [caps, setCaps] = useState<ExtendedCapabilities>({});
    const [resolution, setResolution] = useState<string>('');
    const [focusManual, setFocusManual] = useState(false);
    const [focusDistance, setFocusDistance] = useState<number | null>(null);
    const [zoom, setZoom] = useState<number | null>(null);
    const [torch, setTorch] = useState(false);

    const stopStream = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    };

    // Applies one advanced constraint to the live track; silently ignored when unsupported.
    const applyAdvanced = async (constraint: Record<string, unknown>) => {
        const track = streamRef.current?.getVideoTracks()[0];
        if (!track) return;
        try {
            await track.applyConstraints({ advanced: [constraint] } as MediaTrackConstraints);
        } catch {
            // not supported by this device: leave the stream as is
        }
    };

    const startStream = async (nextDeviceId: string | null = deviceId, nextQuality: QualityKey = quality) => {
        setError(null);
        stopStream();
        try {
            const stream = await openCameraStream(nextDeviceId, nextQuality);
            streamRef.current = stream;
            if (videoRef.current) {
                videoRef.current.srcObject = stream;
            }

            const track = stream.getVideoTracks()[0];
            const capabilities = (track?.getCapabilities?.() ?? {}) as ExtendedCapabilities;
            const settings = track?.getSettings?.() ?? {};
            setCaps(capabilities);
            setResolution(settings.width && settings.height ? `${settings.width}×${settings.height}` : '');
            setFocusManual(false);
            setFocusDistance(null);
            setZoom(capabilities.zoom ? ((settings as { zoom?: number }).zoom ?? capabilities.zoom.min) : null);
            setTorch(false);
            if (capabilities.focusMode?.includes('continuous')) void applyAdvanced({ focusMode: 'continuous' });

            // Camera labels are only exposed after permission has been granted.
            try {
                const all = await navigator.mediaDevices.enumerateDevices();
                setDevices(all.filter((d) => d.kind === 'videoinput'));
            } catch {
                setDevices([]);
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
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const changeDevice = (id: string) => {
        setDeviceId(id);
        writeStored(STORAGE_DEVICE, id);
        void startStream(id, quality);
    };

    const changeQuality = (next: QualityKey) => {
        setQuality(next);
        writeStored(STORAGE_QUALITY, next);
        void startStream(deviceId, next);
    };

    // Tap the picture to re-focus on that moment (single-shot), then go back to continuous AF.
    const refocus = async () => {
        if (!caps.focusMode?.includes('single-shot')) return;
        setFocusManual(false);
        await applyAdvanced({ focusMode: 'single-shot' });
        if (caps.focusMode.includes('continuous')) {
            window.setTimeout(() => void applyAdvanced({ focusMode: 'continuous' }), 1500);
        }
    };

    const toggleManualFocus = async () => {
        if (focusManual) {
            setFocusManual(false);
            setFocusDistance(null);
            await applyAdvanced({ focusMode: caps.focusMode?.includes('continuous') ? 'continuous' : 'single-shot' });
            return;
        }
        const range = caps.focusDistance;
        if (!range) return;
        const initial = range.min + (range.max - range.min) / 4; // nearer end: documents are held close
        setFocusManual(true);
        setFocusDistance(initial);
        await applyAdvanced({ focusMode: 'manual', focusDistance: initial });
    };

    const changeFocusDistance = (value: number) => {
        setFocusDistance(value);
        void applyAdvanced({ focusMode: 'manual', focusDistance: value });
    };

    const changeZoom = (value: number) => {
        setZoom(value);
        void applyAdvanced({ zoom: value });
    };

    const toggleTorch = () => {
        const next = !torch;
        setTorch(next);
        void applyAdvanced({ torch: next });
    };

    const capture = () => {
        const video = videoRef.current;
        if (!video || video.videoWidth === 0) return;

        const scale = Math.min(1, QUALITY[quality].maxLongSide / Math.max(video.videoWidth, video.videoHeight));
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

    const live = !error && !previewUrl;
    const buttonClass = 'border-border bg-background flex h-11 items-center justify-center rounded-xl border-[1.5px] px-4 text-sm font-bold';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                onOpenAutoFocus={(event) => event.preventDefault()}
                className="max-h-[96vh] w-[96vw] max-w-[1100px] overflow-y-auto supports-[height:100dvh]:max-h-[96dvh] sm:max-w-[1100px]"
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                {error && <p className="text-sm font-medium text-red-600">{error}</p>}

                {live && (
                    // object-contain (not cover) so the preview shows the WHOLE frame that will be saved.
                    <video
                        ref={videoRef}
                        autoPlay
                        playsInline
                        muted
                        onClick={() => void refocus()}
                        className="max-h-[58vh] w-full rounded-xl bg-black object-contain supports-[height:100dvh]:max-h-[58dvh]"
                    />
                )}
                {previewUrl && (
                    <PhotoLightbox
                        src={previewUrl}
                        alt="Preview foto"
                        triggerClassName="w-full"
                        imageClassName="max-h-[58vh] w-full rounded-xl bg-black object-contain supports-[height:100dvh]:max-h-[58dvh]"
                    />
                )}

                {live && (
                    <div className="flex flex-col gap-3 text-[13px]">
                        <p className="text-muted-foreground">
                            Dekatkan ke tulisan sampai memenuhi layar, hindari pantulan cahaya, tunggu gambar tajam
                            {caps.focusMode?.includes('single-shot') ? ' (sentuh gambar untuk fokus ulang)' : ''}, lalu ambil foto.
                            {resolution && <span className="ml-1 font-semibold">Resolusi kamera: {resolution}</span>}
                        </p>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {devices.length > 1 && (
                                <label className="flex flex-col gap-1">
                                    <span className="text-muted-foreground text-xs font-semibold">Kamera</span>
                                    <select
                                        className="border-border bg-background h-11 rounded-xl border-[1.5px] px-3 text-sm font-semibold"
                                        value={deviceId ?? streamRef.current?.getVideoTracks()[0]?.getSettings().deviceId ?? ''}
                                        onChange={(e) => changeDevice(e.target.value)}
                                    >
                                        {devices.map((device, index) => (
                                            <option key={device.deviceId || index} value={device.deviceId}>
                                                {device.label || `Kamera ${index + 1}`}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}

                            <label className="flex flex-col gap-1">
                                <span className="text-muted-foreground text-xs font-semibold">Kualitas foto</span>
                                <select
                                    className="border-border bg-background h-11 rounded-xl border-[1.5px] px-3 text-sm font-semibold"
                                    value={quality}
                                    onChange={(e) => changeQuality(e.target.value as QualityKey)}
                                >
                                    {(Object.keys(QUALITY) as QualityKey[]).map((key) => (
                                        <option key={key} value={key}>
                                            {QUALITY[key].label}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            {caps.zoom && zoom !== null && (
                                <label className="flex flex-col gap-1">
                                    <span className="text-muted-foreground text-xs font-semibold">Zoom ({zoom.toFixed(1)}×)</span>
                                    <input
                                        type="range"
                                        min={caps.zoom.min}
                                        max={caps.zoom.max}
                                        step={caps.zoom.step || 0.1}
                                        value={zoom}
                                        onChange={(e) => changeZoom(Number(e.target.value))}
                                        className="h-11 w-full"
                                    />
                                </label>
                            )}

                            {focusManual && caps.focusDistance && focusDistance !== null && (
                                <label className="flex flex-col gap-1">
                                    <span className="text-muted-foreground text-xs font-semibold">Jarak fokus (geser sampai tulisan tajam)</span>
                                    <input
                                        type="range"
                                        min={caps.focusDistance.min}
                                        max={caps.focusDistance.max}
                                        step={caps.focusDistance.step || 0.01}
                                        value={focusDistance}
                                        onChange={(e) => changeFocusDistance(Number(e.target.value))}
                                        className="h-11 w-full"
                                    />
                                </label>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {caps.focusMode?.includes('single-shot') && (
                                <button type="button" onClick={() => void refocus()} className={buttonClass}>
                                    Fokus ulang
                                </button>
                            )}
                            {caps.focusMode?.includes('manual') && caps.focusDistance && (
                                <button type="button" onClick={() => void toggleManualFocus()} className={buttonClass} aria-pressed={focusManual}>
                                    {focusManual ? 'Fokus otomatis' : 'Fokus manual'}
                                </button>
                            )}
                            {caps.torch && (
                                <button type="button" onClick={toggleTorch} className={buttonClass} aria-pressed={torch}>
                                    {torch ? 'Matikan senter' : 'Nyalakan senter'}
                                </button>
                            )}
                        </div>
                    </div>
                )}

                <div className="flex justify-end gap-2.5">
                    {live && (
                        <button
                            type="button"
                            onClick={capture}
                            className="bg-primary flex h-12 items-center justify-center rounded-xl px-6 text-sm font-bold text-white"
                        >
                            Ambil Foto
                        </button>
                    )}
                    {previewUrl && (
                        <>
                            <button type="button" onClick={retake} className={`${buttonClass} h-12 px-6`}>
                                Ambil Ulang
                            </button>
                            <button
                                type="button"
                                onClick={confirm}
                                className="bg-primary flex h-12 items-center justify-center rounded-xl px-6 text-sm font-bold text-white"
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
