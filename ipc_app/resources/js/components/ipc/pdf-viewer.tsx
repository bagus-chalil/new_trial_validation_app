import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { ArrowLeft, Download, Loader2, ZoomIn, ZoomOut } from 'lucide-react';
import * as pdfjs from 'pdfjs-dist';
import PdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?worker';
import { useEffect, useRef, useState } from 'react';

// Bundled by Vite as a plain .js worker; a separate .mjs asset fails on servers that don't serve
// .mjs with a JavaScript MIME type.
pdfjs.GlobalWorkerOptions.workerPort = new PdfWorker();

const MIN_ZOOM = 1;
const MAX_ZOOM = 3;
const STEP = 0.25;

function PdfPage({ doc, pageNumber, width }: { doc: pdfjs.PDFDocumentProxy; pageNumber: number; width: number }) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        let cancelled = false;
        let task: pdfjs.RenderTask | null = null;

        void doc.getPage(pageNumber).then((page) => {
            const canvas = canvasRef.current;
            if (cancelled || !canvas) return;
            const base = page.getViewport({ scale: 1 });
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const viewport = page.getViewport({ scale: (width / base.width) * dpr });
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${viewport.height / dpr}px`;
            task = page.render({ canvas, viewport });
            task.promise.catch(() => undefined);
        });

        return () => {
            cancelled = true;
            task?.cancel();
        };
    }, [doc, pageNumber, width]);

    return <canvas ref={canvasRef} className="mx-auto block bg-white shadow-md" />;
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
    const boxRef = useRef<HTMLDivElement>(null);
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

        (async () => {
            try {
                const res = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
                if (!res.ok) throw new Error(`Gagal memuat dokumen (${res.status}).`);
                const buffer = await res.arrayBuffer();
                objectUrl = URL.createObjectURL(new Blob([buffer], { type: 'application/pdf' }));
                setBlobUrl(objectUrl);
                task = pdfjs.getDocument({ data: new Uint8Array(buffer) });
                setDoc(await task.promise);
            } catch (e) {
                if (!controller.signal.aborted) setError(e instanceof Error ? e.message : 'Gagal memuat dokumen.');
            }
        })();

        return () => {
            controller.abort();
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            void task?.destroy();
        };
    }, [url]);

    useEffect(() => {
        const el = boxRef.current;
        if (!el) return;
        const observer = new ResizeObserver(() => setBoxWidth(el.clientWidth));
        observer.observe(el);
        setBoxWidth(el.clientWidth);
        return () => observer.disconnect();
    }, []);

    const btn =
        'inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-3 text-sm font-semibold text-white active:bg-white/25 disabled:opacity-40';
    const pageWidth = Math.max(0, (boxWidth - 24) * zoom);

    return (
        <Dialog open onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="flex h-dvh w-screen max-w-none flex-col gap-0 rounded-none border-0 bg-neutral-900 p-0 text-white sm:rounded-none [&>button.absolute]:hidden">
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

                <div ref={boxRef} className="min-h-0 flex-1 overflow-auto px-3 py-3">
                    {error && <p className="mt-10 text-center text-sm font-medium text-red-300">{error}</p>}
                    {!error && !doc && (
                        <div className="mt-10 flex flex-col items-center gap-3 text-white/70">
                            <Loader2 className="size-7 animate-spin" />
                            <p className="text-sm font-medium">Menyiapkan dokumen… (bisa beberapa detik)</p>
                        </div>
                    )}
                    {doc && boxWidth > 0 && (
                        <div className="flex flex-col gap-3" style={{ width: pageWidth + 0, margin: '0 auto' }}>
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
