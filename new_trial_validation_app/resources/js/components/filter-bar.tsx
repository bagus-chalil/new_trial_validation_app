import {
    RotateCcw,
    Search as SearchIcon,
    SlidersHorizontal,
    X as ClearIcon,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const ALL_VALUE = '__all__';

type FilterOption = { value: string; label: string };

function normalizeOptions(options: (string | FilterOption)[]): FilterOption[] {
    return options.map((option) =>
        typeof option === 'string' ? { value: option, label: option } : option,
    );
}

type FilterFieldProps = {
    label: string;
    className?: string;
    children: ReactNode;
};

/** Generic labeled slot for a custom filter control inside <FilterBar>. */
export function FilterField({ label, className, children }: FilterFieldProps) {
    return (
        <div className={cn('grid min-w-36 gap-1.5', className)}>
            <span className="text-xs font-medium text-muted-foreground">
                {label}
            </span>
            {children}
        </div>
    );
}

type FilterSelectProps = {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: (string | FilterOption)[];
    placeholder?: string;
    className?: string;
};

/**
 * Dropdown filter backed by shadcn's Radix Select. Radix reserves the empty
 * string for "no value", which conflicts with an "all" filter option — this
 * maps "" <-> an internal sentinel so callers can keep using "" for "all".
 */
export function FilterSelect({
    label,
    value,
    onChange,
    options,
    placeholder,
    className,
}: FilterSelectProps) {
    const normalized = normalizeOptions(options);
    const allLabel = placeholder ?? `Semua ${label.toLowerCase()}`;

    return (
        <FilterField label={label} className={className}>
            <Select
                value={value === '' ? ALL_VALUE : value}
                onValueChange={(next) =>
                    onChange(next === ALL_VALUE ? '' : next)
                }
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder={allLabel} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL_VALUE}>{allLabel}</SelectItem>
                    {normalized.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </FilterField>
    );
}

export type ActiveFilterChip = {
    key: string;
    label: string;
    onClear: () => void;
};

type FilterBarProps = {
    searchValue?: string;
    onSearchChange?: (value: string) => void;
    searchPlaceholder?: string;
    onSubmit: (e: FormEvent) => void;
    onReset: () => void;
    hasActiveFilters: boolean;
    activeChips?: ActiveFilterChip[];
    children?: ReactNode;
    className?: string;
};

/**
 * Modern search/filter toolbar: an icon-adorned search field, arbitrary
 * filter controls (<FilterSelect>/<FilterField>) that wrap on small screens,
 * and — when filters are active — a row of removable chips summarizing them.
 */
export function FilterBar({
    searchValue,
    onSearchChange,
    searchPlaceholder = 'Cari...',
    onSubmit,
    onReset,
    hasActiveFilters,
    activeChips = [],
    children,
    className,
}: FilterBarProps) {
    return (
        <Card className={cn('border-none bg-muted/40 shadow-none', className)}>
            <CardContent>
                <form onSubmit={onSubmit} className="space-y-4">
                    <div className="flex flex-wrap items-end gap-3">
                        {onSearchChange && (
                            <FilterField
                                label="Search"
                                className="min-w-56 flex-1 sm:max-w-sm"
                            >
                                <div className="relative">
                                    <SearchIcon className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        value={searchValue}
                                        onChange={(e) =>
                                            onSearchChange(e.target.value)
                                        }
                                        placeholder={searchPlaceholder}
                                        className="pl-9"
                                    />
                                </div>
                            </FilterField>
                        )}

                        {children}

                        <div className="ml-auto flex shrink-0 gap-2">
                            <Button type="submit" className="gap-1.5">
                                <SearchIcon className="size-4" />
                                Search
                            </Button>
                            {hasActiveFilters && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={onReset}
                                    className="gap-1.5 text-muted-foreground"
                                >
                                    <RotateCcw className="size-4" />
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>

                    {hasActiveFilters && activeChips.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <SlidersHorizontal className="size-3.5" />
                                Filter aktif:
                            </span>
                            {activeChips.map((chip) => (
                                <Badge
                                    key={chip.key}
                                    variant="secondary"
                                    className="gap-1 py-1 pr-1 pl-2.5"
                                >
                                    {chip.label}
                                    <button
                                        type="button"
                                        onClick={chip.onClear}
                                        className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                                        aria-label={`Hapus filter ${chip.label}`}
                                    >
                                        <ClearIcon className="size-3" />
                                    </button>
                                </Badge>
                            ))}
                        </div>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}
