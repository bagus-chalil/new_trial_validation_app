import { cn } from '@/lib/utils';
import { Check, ChevronDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export interface SelectOption {
    value: string;
    label: string;
}

/**
 * Tablet-friendly select with search, for the filter bar. The native <select> is a scroll-only
 * list on a tablet; this one filters as you type. Details that matter on a tablet:
 *  - every list has a search box, including lists with only a few options;
 *  - the list height follows the *dynamic* viewport (dvh), so it shrinks above the keyboard
 *    instead of being hidden behind it;
 *  - on open the field is scrolled into view, so the list isn't below the fold.
 */
export function SearchableSelect({
    value,
    onChange,
    options,
    allLabel = 'Semua',
    placeholder = 'Cari...',
    className,
    'aria-label': ariaLabel,
}: Readonly<{
    value: string;
    onChange: (value: string) => void;
    options: SelectOption[];
    allLabel?: string;
    placeholder?: string;
    className?: string;
    'aria-label'?: string;
}>) {
    const [open, setOpen] = useState(false);
    const [q, setQ] = useState('');
    const [highlight, setHighlight] = useState(0);
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const shown = useMemo(() => {
        const term = q.trim().toLowerCase();
        const matches = term ? options.filter((o) => o.label.toLowerCase().includes(term)) : options;
        return term ? matches : [{ value: '', label: allLabel }, ...matches];
    }, [q, options, allLabel]);

    useEffect(() => {
        if (!open) return;
        setQ('');
        setHighlight(0);
        // Touch devices: don't auto-focus the search (the virtual keyboard resizes the viewport and
        // makes the page jump); the user taps the search box when they actually want to type.
        const preciseSearch = typeof window.matchMedia !== 'function' || window.matchMedia('(pointer: fine)').matches;
        if (preciseSearch) inputRef.current?.focus({ preventScroll: true });
        const timer = setTimeout(() => rootRef.current?.scrollIntoView({ block: 'nearest', behavior: preciseSearch ? 'smooth' : 'auto' }), 120);
        const onPointerDown = (e: PointerEvent) => {
            if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('pointerdown', onPointerDown);
        return () => {
            clearTimeout(timer);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, [open]);

    const choose = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlight((h) => Math.min(h + 1, shown.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlight((h) => Math.max(h - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (shown[highlight]) choose(shown[highlight].value);
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    const current = options.find((o) => o.value === value);

    return (
        <div ref={rootRef} className="relative min-w-0">
            <button
                type="button"
                aria-label={ariaLabel}
                aria-expanded={open}
                onClick={() => setOpen((o) => !o)}
                className={cn(
                    'border-border-soft bg-card focus-visible:ring-primary/30 flex h-11 w-full items-center justify-between gap-2 rounded-xl border px-3 text-left text-[13px] font-medium outline-none focus-visible:ring-2',
                    className,
                )}
            >
                <span className={cn('min-w-0 flex-1 truncate', !current && 'text-muted-foreground')}>{current?.label ?? allLabel}</span>
                <ChevronDown className="size-4 shrink-0 opacity-50" />
            </button>

            {open && (
                <div className="bg-popover text-popover-foreground absolute top-full right-0 left-0 z-50 mt-1 overflow-hidden rounded-xl border shadow-md">
                    <div className="relative border-b p-2">
                        <Search className="text-muted-foreground/60 absolute top-1/2 left-4 size-4 -translate-y-1/2" />
                        <input
                            ref={inputRef}
                            value={q}
                            onChange={(e) => {
                                setQ(e.target.value);
                                setHighlight(0);
                            }}
                            onKeyDown={onKeyDown}
                            placeholder={placeholder}
                            inputMode="search"
                            enterKeyHint="done"
                            autoComplete="off"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck={false}
                            className="border-border-soft focus-visible:ring-primary/30 h-10 w-full rounded-lg border pr-3 pl-8 text-sm outline-none focus-visible:ring-2 [@media(pointer:coarse)]:text-base"
                        />
                    </div>
                    <div className="max-h-[min(18rem,38dvh)] overflow-y-auto overscroll-contain p-1" role="listbox">
                        {shown.map((option, i) => (
                            <button
                                key={option.value || '__all'}
                                type="button"
                                role="option"
                                aria-selected={option.value === value}
                                onPointerEnter={() => setHighlight(i)}
                                onClick={() => choose(option.value)}
                                className={cn(
                                    'flex min-h-11 w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm',
                                    i === highlight && 'bg-accent text-accent-foreground',
                                )}
                            >
                                <Check className={cn('size-4 shrink-0', option.value === value ? 'opacity-100' : 'opacity-0')} />
                                <span className="min-w-0 flex-1">{option.label}</span>
                            </button>
                        ))}
                        {shown.length === 0 && <p className="text-muted-foreground p-3 text-center text-sm">Tidak ditemukan.</p>}
                    </div>
                </div>
            )}
        </div>
    );
}
