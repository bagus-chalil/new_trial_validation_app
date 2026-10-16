import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { AlertTriangle, ArrowLeft, Download, ExternalLink, Loader2, ZoomIn, ZoomOut } from 'lucide-react';
// Legacy build: the modern one needs APIs (Promise.withResolvers etc.) that Chrome 106, UC Browser
// and Huawei Browser don't have, so its worker dies silently and the viewer looks frozen.
import * as pdfjs from 'pdfjs-dist/legacy/build/pdf.mjs';
import PdfWorker from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker';
import { useEffect, useRef, useState } from 'react';

// Bundled by Vite as a plain .js worker; a separate .mjs asset fails on servers that don't serve
// .mjs with a JavaScript MIME type. One worker per document (see the load effect): a shared
// workerPort gets destroyed together with the first loading task and breaks every later open.
const LOAD_TIMEOUT_MS = 60_000;
const MAX_CANVAS_PIXELS = 6_000_000;
const MIN_ZOOM = 1;
const MAX_ZOOM = 3;
const STEP = 0.25;

function PdfPage({ doc, pageNumber, width }: { doc: pdfjs.PDFDocumentProxy; pageNumber: number; width: number }) {
    const wrapRef = useRef<HTMLDivElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [visible, setVisible] = useState(typeof IntersectionObserver === 'undefined');
    const [height, setHeight] = useState(Math.round(width * 1.414));

    // Render only pages near the viewport: weak tablets choke when every page is rasterized at once.
    useEffect(() => {
        const el = wrapRef.current;
        if (!el || visible) return;
        const io = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    setVisible(true);
                    io.disconnect();
                }
            },
            { rootMargin: '600px 0px' },
        );
        io.observe(el);
        return () => io.disconnect();
    }, [visible]);

    useEffect(() => {
        if (!visible || width <= 0) return;
        let cancelled = false;
        let task: pdfjs.RenderTask | null = null;

        void doc.getPage(pageNumber).then((page) => {
            const canvas = canvasRef.current;
            if (cancelled || !canvas) return;
            const base = page.getViewport({ scale: 1 });
            let dpr = Math.min(window.devicePixelRatio || 1, 1.5);
            const cssScale = width / base.width;
            // Keep the bitmap under a safe size, old WebViews fail to allocate big canvases.
            while (dpr > 1 && base.width * base.height * (cssScale * dpr) ** 2 > MAX_CANVAS_PIXELS) dpr -= 0.25;
            const viewport = page.getViewport({ scale: cssScale * dpr });
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = `${width}px`;
            canvas.style.height = `${viewport.height / dpr}px`;
            setHeight(Math.round(viewport.height / dpr));
            task = page.render({ canvas, viewport });
            task.promise.catch(() => undefined);
        });

        return () => {
            cancelled = true;
            task?.cancel();
        };
    }, [doc, pageNumber, width, visible]);

    return (
        <div ref={wrapRef} className="mx-auto bg-white shadow-md" style={{ width, minHeight: height }}>
            <canvas ref={canvasRef} className="block" />
        </div>
    );
}

/**
 * In-app PDF viewer. Renders the PDF to canvases with pdf.js (tablet browsers often can't show an
 * inline PDF, or show it without any way back), inside a full-screen overlay with its own
 * "Kembali ke aplikasi" button. The overlay also owns one history entry, so the tablet's Back
 * gesture/button closes the viewer instead of leaving the app for the portal.
 */
export function PdfViewer({ url, title, onClose }: { readonly url: string; readonly title: string; readonly onClose: () => void }) {
    const [doc, setDoc] = useState<pdfjs.PDFDocumentProxy | null>(null);
    const [blobUrl, setBlobUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [zoom, setZoom] = useState(1);
    const [boxWidth, setBoxWidth] = useState(0);
    // Callback-ref state, not useRef: Radix mounts the dialog content after the first render, so a
    // plain ref is still null when a mount effect runs and the width would never be measured.
    const [box, setBox] = useState<HTMLDivElement | null>(null);
    const closedByPop = useRef(false);

    // Own one history entry so the system Back closes the viewer first.
    useEffect(() => {
        window.history.pushState({ pdfViewer: true }, '');
        const onPop = () => {
            closedByPop.current = true;
            onClose();
        };
        window.addEventListener('popstate', onPop);
        return () => {
            window.removeEventListener('popstate', onPop);
            if (!closedByPop.current && window.history.state?.pdfViewer) window.history.back();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        let objectUrl: string | null = null;
        let task: pdfjs.PDFDocumentLoadingTask | null = null;
        let worker: pdfjs.PDFWorker | null = null;
        const timer = window.setTimeout(() => {
            setError((prev) => prev ?? 'Dokumen terlalu lama dimuat di perangkat ini.');
        }, LOAD_TIMEOUT_MS);

        (async () => {
            try {
                const res = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
                if (!res.ok) throw new Error(`Gagal memuat dokumen (${res.status}).`);
                const buffer = await res.arrayBuffer();
                if (controller.signal.aborted) return;
                objectUrl = URL.createObjectURL(new Blob([buffer], { type: 'application/pdf' }));
                setBlobUrl(objectUrl);
                // The bundled typings only allow `port: null`, but a real Worker is accepted at runtime.
                worker = new pdfjs.PDFWorker({ port: new PdfWorker() as unknown as null });
                task = pdfjs.getDocument({ data: new Uint8Array(buffer), worker });
                const loaded = await task.promise;
                if (controller.signal.aborted) return;
                window.clearTimeout(timer);
                setDoc(loaded);
            } catch (e) {
                if (!controller.signal.aborted) setError(e instanceof Error ? e.message : 'Gagal memuat dokumen.');
            }
        })();

        return () => {
            controller.abort();
            window.clearTimeout(timer);
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            void Promise.resolve(task?.destroy())
                .catch(() => undefined)
                .then(() => worker?.destroy());
        };
    }, [url]);

    useEffect(() => {
        if (!box) return;
        const observer = new ResizeObserver(() => setBoxWidth(box.clientWidth));
        observer.observe(box);
        setBoxWidth(box.clientWidth);
        return () => observer.disconnect();
    }, [box]);

    const btn =
        'inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-3 text-sm font-semibold text-white active:bg-white/25 disabled:opacity-40';
    const pageWidth = Math.max(0, (boxWidth - 24) * zoom);

    return (
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="flex h-screen max-h-dvh w-screen max-w-none flex-col gap-0 rounded-none border-0 bg-neutral-900 p-0 text-white sm:rounded-none [&>button.absolute]:hidden">
                <div className="flex shrink-0 items-center gap-2 border-b border-white/10 bg-neutral-950 px-3 py-2">
                    <button type="button" onClick={onClose} className={`${btn} bg-white text-neutral-900 active:bg-white/80`}>
                        <ArrowLeft className="size-5" /> Kembali ke aplikasi
                    </button>
                    <DialogTitle className="min-w-0 flex-1 truncate px-2 text-left text-base font-semibold text-white">{title}</DialogTitle>
                    <DialogDescription className="sr-only">Pratinjau dokumen PDF</DialogDescription>
                    <button
                        type="button"
                        aria-label="Perkecil"
                        disabled={zoom <= MIN_ZOOM}
                        onClick={() => setZoom((z) => Math.max(MIN_ZOOM, z - STEP))}
                        className={`${btn} w-12 px-0`}
                    >
                        <ZoomOut className="size-5" />
                    </button>
                    <span className="w-14 text-center text-sm font-semibold text-white/80 tabular-nums">{Math.round(zoom * 100)}%</span>
                    <button
                        type="button"
                        aria-label="Perbesar"
                        disabled={zoom >= MAX_ZOOM}
                        onClick={() => setZoom((z) => Math.min(MAX_ZOOM, z + STEP))}
                        className={`${btn} w-12 px-0`}
                    >
                        <ZoomIn className="size-5" />
                    </button>
                    {blobUrl && (
                        <a href={blobUrl} download={`${title}.pdf`} className={btn}>
                            <Download className="size-5" /> <span className="hidden sm:inline">Unduh</span>
                        </a>
                    )}
                </div>

                <div ref={setBox} className="min-h-0 flex-1 overflow-auto px-3 py-3">
                    {error && (
                        <div className="mt-10 flex flex-col items-center gap-4 px-4 text-center">
                            <AlertTriangle className="size-8 text-red-300" />
                            <p className="text-sm font-medium text-red-300">{error}</p>
                            <p className="max-w-md text-sm text-white/70">
                                Pratinjau tidak bisa ditampilkan di browser ini. Buka dokumen langsung atau unduh, lalu lihat dengan aplikasi PDF di
                                perangkat.
                            </p>
                            <div className="flex flex-wrap justify-center gap-2">
                                <a href={url} target="_blank" rel="noreferrer" className={btn}>
                                    <ExternalLink className="size-5" /> Buka PDF langsung
                                </a>
                                {blobUrl && (
                                    <a href={blobUrl} download={`${title}.pdf`} className={btn}>
                                        <Download className="size-5" /> Unduh
                                    </a>
                                )}
                            </div>
                        </div>
                    )}
                    {!error && !doc && (
                        <div className="mt-10 flex flex-col items-center gap-3 text-white/70">
                            <Loader2 className="size-7 animate-spin" />
                            <p className="text-sm font-medium">Menyiapkan dokumen… (bisa beberapa detik)</p>
                        </div>
                    )}
                    {!error && doc && boxWidth > 0 && (
                        <div className="flex flex-col gap-3" style={{ width: pageWidth, margin: '0 auto' }}>
                            {Array.from({ length: doc.numPages }, (_, i) => (
                                <PdfPage key={i} doc={doc} pageNumber={i + 1} width={pageWidth} />
                            ))}
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
