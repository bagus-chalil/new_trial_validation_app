import * as SelectPrimitive from '@radix-ui/react-select';
import { Check, ChevronDown, ChevronUp, Search } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

const Select = SelectPrimitive.Root;

const SelectGroup = SelectPrimitive.Group;

const SelectValue = SelectPrimitive.Value;

const SelectTrigger = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Trigger>,
    React.ComponentPropsWithoutRef<typeof SelectPrimitive.Trigger>
>(({ className, children, ...props }, ref) => (
    <SelectPrimitive.Trigger
        ref={ref}
        className={cn(
            'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 [&>span]:line-clamp-1',
            className,
        )}
        {...props}
    >
        {children}
        <SelectPrimitive.Icon asChild>
            <ChevronDown className="h-4 w-4 opacity-50" />
        </SelectPrimitive.Icon>
    </SelectPrimitive.Trigger>
));
SelectTrigger.displayName = SelectPrimitive.Trigger.displayName;

const SelectScrollUpButton = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.ScrollUpButton>,
    React.ComponentPropsWithoutRef<typeof SelectPrimitive.ScrollUpButton>
>(({ className, ...props }, ref) => (
    <SelectPrimitive.ScrollUpButton ref={ref} className={cn('flex cursor-default items-center justify-center py-1', className)} {...props}>
        <ChevronUp className="h-4 w-4" />
    </SelectPrimitive.ScrollUpButton>
));
SelectScrollUpButton.displayName = SelectPrimitive.ScrollUpButton.displayName;

const SelectScrollDownButton = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.ScrollDownButton>,
    React.ComponentPropsWithoutRef<typeof SelectPrimitive.ScrollDownButton>
>(({ className, ...props }, ref) => (
    <SelectPrimitive.ScrollDownButton ref={ref} className={cn('flex cursor-default items-center justify-center py-1', className)} {...props}>
        <ChevronDown className="h-4 w-4" />
    </SelectPrimitive.ScrollDownButton>
));
SelectScrollDownButton.displayName = SelectPrimitive.ScrollDownButton.displayName;

function optionText(node: React.ReactNode): string {
    return React.Children.toArray(node)
        .map((child) => {
            if (typeof child === 'string' || typeof child === 'number') return String(child);
            return React.isValidElement<{ children?: React.ReactNode }>(child) ? optionText(child.props.children) : '';
        })
        .join('');
}

/** Keep filtered items mounted so Radix can still display the selected label. */
function SearchableOptions({ children, position }: { children: React.ReactNode; position: 'popper' | 'item-aligned' }) {
    const [query, setQuery] = React.useState('');
    const inputRef = React.useRef<HTMLInputElement>(null);
    const viewportRef = React.useRef<HTMLDivElement>(null);
    const searchId = React.useId();
    const term = query.trim().toLocaleLowerCase();
    let matchCount = 0;
    const filter = (nodes: React.ReactNode): React.ReactNode =>
        React.Children.map(nodes, (child) => {
            if (!React.isValidElement<React.ComponentPropsWithoutRef<typeof SelectPrimitive.Item>>(child)) return child;
            if (child.type === SelectItem) {
                const matches = (child.props.textValue ?? optionText(child.props.children)).toLocaleLowerCase().includes(term);
                if (matches) matchCount++;
                return React.cloneElement(child, {
                    hidden: !matches,
                    disabled: child.props.disabled || !matches,
                    style: { ...child.props.style, ...(!matches ? { display: 'none' } : {}) },
                });
            }
            return child.props.children ? React.cloneElement(child, {}, filter(child.props.children)) : child;
        });
    const filtered = filter(children);

    React.useEffect(() => {
        // Touch devices: focusing the search input pops up the virtual keyboard, which shrinks the
        // viewport and makes the page + dropdown jump. Only auto-focus with a precise pointer.
        if (typeof window.matchMedia === 'function' && !window.matchMedia('(pointer: fine)').matches) return;
        // Radix focuses the selected option on open; move focus to search afterwards.
        const frame = requestAnimationFrame(() => inputRef.current?.focus({ preventScroll: true }));
        return () => cancelAnimationFrame(frame);
    }, []);

    return (
        <>
            <div className="relative shrink-0 border-b p-2">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2" />
                <input
                    ref={inputRef}
                    type="search"
                    aria-label="Cari pilihan"
                    aria-controls={searchId}
                    placeholder="Cari pilihan..."
                    autoComplete="off"
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        viewportRef.current?.scrollTo({ top: 0 });
                    }}
                    onKeyDown={(event) => {
                        // Prevent Radix typeahead and option selection while editing the query.
                        if (event.key !== 'Escape' && event.key !== 'Tab') event.stopPropagation();
                        if (event.key === 'Enter') event.preventDefault();
                        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                            event.preventDefault();
                            const options = viewportRef.current?.querySelectorAll<HTMLElement>('[role="option"]:not([data-disabled])');
                            const target = event.key === 'ArrowDown' ? options?.[0] : options?.[options.length - 1];
                            target?.focus();
                        }
                    }}
                    className="border-input bg-background focus:ring-ring h-10 w-full rounded-md border pr-3 pl-8 text-sm outline-none focus:ring-2 [@media(pointer:coarse)]:text-base"
                />
            </div>
            <SelectScrollUpButton />
            <SelectPrimitive.Viewport
                ref={viewportRef}
                id={searchId}
                className={cn('p-1', position === 'popper' && 'w-full min-w-[var(--radix-select-trigger-width)]')}
            >
                {filtered}
                {matchCount === 0 && <p role="status" className="text-muted-foreground p-3 text-center text-sm">Tidak ditemukan.</p>}
            </SelectPrimitive.Viewport>
            <SelectScrollDownButton />
        </>
    );
}

const SelectContent = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Content>,
    React.ComponentPropsWithoutRef<typeof SelectPrimitive.Content>
>(({ className, children, position = 'popper', ...props }, ref) => (
    <SelectPrimitive.Portal>
        <SelectPrimitive.Content
            ref={ref}
            className={cn(
                'relative z-50 flex max-h-[min(24rem,var(--radix-select-content-available-height))] min-w-[8rem] flex-col overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                position === 'popper' &&
                    'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                className,
            )}
            position={position}
            {...props}
        >
            <SearchableOptions position={position}>{children}</SearchableOptions>
        </SelectPrimitive.Content>
    </SelectPrimitive.Portal>
));
SelectContent.displayName = SelectPrimitive.Content.displayName;

const SelectLabel = React.forwardRef<React.ElementRef<typeof SelectPrimitive.Label>, React.ComponentPropsWithoutRef<typeof SelectPrimitive.Label>>(
    ({ className, ...props }, ref) => (
        <SelectPrimitive.Label ref={ref} className={cn('py-1.5 pl-8 pr-2 text-sm font-semibold', className)} {...props} />
    ),
);
SelectLabel.displayName = SelectPrimitive.Label.displayName;

const SelectItem = React.forwardRef<React.ElementRef<typeof SelectPrimitive.Item>, React.ComponentPropsWithoutRef<typeof SelectPrimitive.Item>>(
    ({ className, children, ...props }, ref) => (
        <SelectPrimitive.Item
            ref={ref}
            className={cn(
                'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-hidden focus:bg-accent focus:text-accent-foreground data-disabled:pointer-events-none data-disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <span className="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
                <SelectPrimitive.ItemIndicator>
                    <Check className="h-4 w-4" />
                </SelectPrimitive.ItemIndicator>
            </span>

            <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
        </SelectPrimitive.Item>
    ),
);
SelectItem.displayName = SelectPrimitive.Item.displayName;

const SelectSeparator = React.forwardRef<
    React.ElementRef<typeof SelectPrimitive.Separator>,
    React.ComponentPropsWithoutRef<typeof SelectPrimitive.Separator>
>(({ className, ...props }, ref) => <SelectPrimitive.Separator ref={ref} className={cn('-mx-1 my-1 h-px bg-muted', className)} {...props} />);
SelectSeparator.displayName = SelectPrimitive.Separator.displayName;

export {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectScrollDownButton,
    SelectScrollUpButton,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
};
