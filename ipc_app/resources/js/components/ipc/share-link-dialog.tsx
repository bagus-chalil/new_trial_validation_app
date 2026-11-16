import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Check, Copy, ExternalLink, QrCode, RefreshCw } from 'lucide-react';
import { type ReactNode, useState } from 'react';

/** Copy text to the clipboard; falls back to a hidden textarea where the Clipboard API is blocked (plain HTTP). */
export async function copyText(text: string) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
}

export function useCopied(): [boolean, (text: string) => void] {
    const [copied, setCopied] = useState(false);
    return [
        copied,
        (text: string) => {
            void copyText(text).then(() => {
                setCopied(true);
                setTimeout(() => setCopied(false), 1800);
            });
        },
    ];
}

export interface ShareLinkInfo {
    url: string;
    qr_svg: string;
    poster_url: string;
}

/**
 * Popup to share a public (no-login) link: QR to scan, link to copy, open-in-new-tab, printable
 * poster, and an optional "make a new link" action (revokes the old link/QR).
 */
export function ShareLinkDialog({
    open,
    onOpenChange,
    title,
    description,
    share,
    onRegenerate,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    share: ShareLinkInfo;
    onRegenerate?: () => void;
    children?: ReactNode;
}) {
    const [copied, copy] = useCopied();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                {children}

                <div className="flex flex-col items-center gap-2">
                    <div
                        className="rounded-2xl border bg-white p-3 [&_svg]:size-50"
                        aria-label="QR code"
                        dangerouslySetInnerHTML={{ __html: share.qr_svg }}
                    />
                    <p className="text-muted-foreground text-center text-[12px]">Scan dengan kamera HP / tablet</p>
                </div>

                <div className="flex items-center gap-2">
                    <input
                        readOnly
                        value={share.url}
                        onFocus={(e) => e.currentTarget.select()}
                        className="border-border-soft bg-muted/40 h-10 min-w-0 flex-1 rounded-xl border px-3 font-mono text-[12px]"
                    />
                    <button
                        type="button"
                        onClick={() => copy(share.url)}
                        className="bg-foreground text-background flex h-10 shrink-0 items-center gap-1.5 rounded-xl px-3 text-[12.5px] font-bold"
                    >
                        {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                        {copied ? 'Tersalin' : 'Salin'}
                    </button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <a
                        href={share.url}
                        target="_blank"
                        rel="noreferrer"
                        className="border-border-soft hover:bg-muted flex h-9 items-center gap-1.5 rounded-xl border px-3 text-[12.5px] font-semibold"
                    >
                        <ExternalLink className="size-4" /> Buka tanpa login
                    </a>
                    <a
                        href={share.poster_url}
                        target="_blank"
                        rel="noreferrer"
                        className="border-border-soft hover:bg-muted flex h-9 items-center gap-1.5 rounded-xl border px-3 text-[12.5px] font-semibold"
                    >
                        <QrCode className="size-4" /> Poster QR (cetak)
                    </a>
                    {onRegenerate && (
                        <button
                            type="button"
                            onClick={onRegenerate}
                            className="ml-auto flex h-9 items-center gap-1.5 rounded-xl border border-red-200 px-3 text-[12.5px] font-semibold text-red-700 hover:bg-red-50"
                        >
                            <RefreshCw className="size-4" /> Buat link baru
                        </button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
