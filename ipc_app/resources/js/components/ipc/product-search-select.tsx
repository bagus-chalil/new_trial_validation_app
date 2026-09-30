import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { Check, ChevronDown, Loader2, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface ProductOption {
    id: number;
    fg_code: string;
    product_name: string;
    shelf_life_months?: number | null;
    bulk_codes: { id: number; bulk_code: string }[];
}

/**
 * Searchable product picker backed by `lookup.products` (server-side, max 30 results).
 * The master has thousands of products, so the page never ships the full list — only
 * whatever matches what the user types, debounced.
 */
export function ProductSearchSelect({
    id,
    value,
    onChange,
    placeholder = 'Pilih FG Code',
    className,
    flagMissingBulkCode = false,
}: {
    id?: string;
    value: ProductOption | null;
    onChange: (product: ProductOption | null) => void;
    placeholder?: string;
    className?: string;
    /** Mark results that have no active bulk code (batches can't be created for those). */
    flagMissingBulkCode?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const [results, setResults] = useState<ProductOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [highlight, setHighlight] = useState(0);
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            setFailed(false);
            try {
                const response = await fetch(route('lookup.products', { q: q.trim() }), {
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error(String(response.status));
                setResults(await response.json());
                setHighlight(0);
            } catch (e) {
                if ((e as Error).name !== 'AbortError') setFailed(true);
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [q, open]);

    useEffect(() => {
        if (!open) return;
        inputRef.current?.focus();
        const onPointerDown = (e: PointerEvent) => {
            if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('pointerdown', onPointerDown);
        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [open]);

    const choose = (product: ProductOption) => {
        onChange(product);
        setOpen(false);
        setQ('');
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlight((h) => Math.min(h + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlight((h) => Math.max(h - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (results[highlight]) choose(results[highlight]);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div ref={rootRef} className="relative min-w-0">
            <button
                id={id}
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={cn(
                    'border-input bg-background focus-visible:ring-ring/50 flex min-h-11 w-full items-center justify-between gap-2 rounded-md border px-3 py-2 text-left text-sm shadow-xs outline-none focus-visible:ring-[3px]',
                    className,
                )}
            >
                <span className={cn('min-w-0 flex-1 truncate', !value && 'text-muted-foreground')}>
                    {value ? `${value.fg_code} — ${value.product_name}` : placeholder}
                </span>
                <ChevronDown className="size-4 shrink-0 opacity-50" />
            </button>

            {open && (
                <div className="bg-popover text-popover-foreground absolute top-full right-0 left-0 z-50 mt-1 overflow-hidden rounded-md border shadow-md">
                    <div className="relative border-b p-2">
                        <Search className="text-muted-foreground/60 absolute top-1/2 left-4 size-4 -translate-y-1/2" />
                        <Input
                            ref={inputRef}
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            onKeyDown={onKeyDown}
                            placeholder="Cari FG code atau nama produk..."
                            className="h-10 pl-8"
                            autoComplete="off"
                        />
                        {loading && <Loader2 className="text-muted-foreground absolute top-1/2 right-4 size-4 -translate-y-1/2 animate-spin" />}
                    </div>
                    <div className="max-h-72 overflow-y-auto p-1" role="listbox">
                        {results.map((product, i) => (
                            <button
                                key={product.id}
                                type="button"
                                role="option"
                                aria-selected={value?.id === product.id}
                                onPointerEnter={() => setHighlight(i)}
                                onClick={() => choose(product)}
                                className={cn(
                                    'flex min-h-10 w-full items-center gap-2 rounded-sm px-2 py-2 text-left text-sm',
                                    i === highlight && 'bg-accent text-accent-foreground',
                                )}
                            >
                                <Check className={cn('size-4 shrink-0', value?.id === product.id ? 'opacity-100' : 'opacity-0')} />
                                <span className="min-w-0 flex-1">
                                    <span className="font-semibold">{product.fg_code}</span> — {product.product_name}
                                </span>
                                {flagMissingBulkCode && product.bulk_codes.length === 0 && (
                                    <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                        Tanpa bulk code
                                    </span>
                                )}
                            </button>
                        ))}
                        {!loading && failed && <p className="text-destructive p-3 text-center text-sm">Gagal memuat produk. Coba lagi.</p>}
                        {!loading && !failed && results.length === 0 && (
                            <p className="text-muted-foreground p-3 text-center text-sm">Produk tidak ditemukan.</p>
                        )}
                        {results.length >= 30 && (
                            <p className="text-muted-foreground px-2 py-1.5 text-center text-xs">
                                Menampilkan 30 teratas — ketik untuk mempersempit.
                            </p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
