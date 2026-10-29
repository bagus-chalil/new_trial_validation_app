import { SearchableSelect } from '@/components/ipc/searchable-select';
import { router } from '@inertiajs/react';
import { ListFilter, Search, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

export interface BatchFilterValues {
    q?: string;
    stage?: string;
    status?: string;
    date_from?: string;
    date_to?: string;
    created_by?: number | string;
    master_line_id?: number | string;
    flow?: string;
}

export interface BatchFilterOptions {
    lines: { id: number; name: string; code: string }[];
    creators: { id: number; name: string }[];
    flows: { value: string; label: string }[];
}

export interface ExtraFilterField {
    key: 'status';
    label: string;
    options: { value: string; label: string }[];
}

interface Props {
    /** Page path the filters are applied to, e.g. `/batches`. */
    path: string;
    filters: BatchFilterValues;
    options: BatchFilterOptions;
    /** Show a search box inside the bar (pages that don't already have their own). */
    withSearch?: boolean;
    searchPlaceholder?: string;
    /** Page-specific selects (e.g. Micro Check's "Belum diisi / Progress tersimpan"). */
    extraFields?: ExtraFilterField[];
    /** Params that belong to the page, not the bar, and must survive a filter change (q, stage). */
    keep?: Partial<BatchFilterValues>;
}

const FILTER_KEYS = ['date_from', 'date_to', 'created_by', 'master_line_id', 'flow', 'status'] as const;

const fieldClass =
    'border-border-soft bg-card text-foreground h-11 w-full rounded-xl border px-3 text-[13px] font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary/30';

const toIso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

function presets(): { label: string; from: string; to: string }[] {
    const today = new Date();
    const weekAgo = new Date();
    weekAgo.setDate(today.getDate() - 6);
    return [
        { label: 'Hari ini', from: toIso(today), to: toIso(today) },
        { label: '7 hari terakhir', from: toIso(weekAgo), to: toIso(today) },
        { label: 'Bulan ini', from: toIso(new Date(today.getFullYear(), today.getMonth(), 1)), to: toIso(today) },
    ];
}

function formatDay(iso: string): string {
    const [y, m, d] = iso.split('-');
    return `${d}/${m}/${y}`;
}

function FilterField({ label, children }: Readonly<{ label: string; children: ReactNode }>) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5">
            <span className="text-muted-foreground text-[12px] font-bold">{label}</span>
            {children}
        </div>
    );
}

export function BatchFilterBar({
    path,
    filters,
    options,
    withSearch = false,
    searchPlaceholder = 'Cari no batch / produk...',
    extraFields = [],
    keep = {},
}: Readonly<Props>) {
    const activeCount = FILTER_KEYS.filter((key) => filters[key] !== undefined && filters[key] !== '').length;
    const [open, setOpen] = useState(activeCount > 0);
    const [q, setQ] = useState(filters.q ?? '');

    const visit = (patch: Partial<BatchFilterValues>, nextQ: string | undefined = filters.q) => {
        const params: Record<string, string | number> = {};
        const merged: BatchFilterValues = { ...keep, ...filters, q: nextQ, ...patch };
        Object.entries(merged).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') params[key] = value;
        });
        router.get(path, params, { preserveState: true, preserveScroll: true, replace: true });
    };

    const submitSearch = (e: FormEvent) => {
        e.preventDefault();
        visit({}, q.trim());
    };

    const clearAll = () => visit(Object.fromEntries(FILTER_KEYS.map((key) => [key, undefined])));

    const lineName = options.lines.find((l) => String(l.id) === String(filters.master_line_id))?.name;
    const creatorName = options.creators.find((c) => String(c.id) === String(filters.created_by))?.name;
    const flowLabel = options.flows.find((f) => f.value === filters.flow)?.label;

    const chips: { key: (typeof FILTER_KEYS)[number][]; label: string }[] = [];
    if (filters.date_from || filters.date_to) {
        const range =
            filters.date_from && filters.date_to
                ? filters.date_from === filters.date_to
                    ? formatDay(filters.date_from)
                    : `${formatDay(filters.date_from)} – ${formatDay(filters.date_to)}`
                : filters.date_from
                  ? `Sejak ${formatDay(filters.date_from)}`
                  : `Sampai ${formatDay(filters.date_to as string)}`;
        chips.push({ key: ['date_from', 'date_to'], label: range });
    }
    if (filters.created_by) chips.push({ key: ['created_by'], label: `Dibuat: ${creatorName ?? filters.created_by}` });
    if (filters.master_line_id) chips.push({ key: ['master_line_id'], label: `Line: ${lineName ?? filters.master_line_id}` });
    if (filters.flow) chips.push({ key: ['flow'], label: `Flow: ${flowLabel ?? filters.flow}` });
    extraFields.forEach((field) => {
        const current = filters[field.key];
        if (current) chips.push({ key: [field.key], label: field.options.find((o) => o.value === current)?.label ?? current });
    });

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
                {withSearch && (
                    <form onSubmit={submitSearch} className="relative min-w-0 flex-1">
                        <Search
                            className="text-muted-foreground/70 pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2"
                            strokeWidth={2}
                        />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="border-border-soft bg-card focus-visible:ring-primary/30 h-12 w-full rounded-2xl border pr-10 pl-10 text-sm outline-none focus-visible:ring-2"
                        />
                        {q && (
                            <button
                                type="button"
                                aria-label="Bersihkan pencarian"
                                onClick={() => {
                                    setQ('');
                                    visit({}, '');
                                }}
                                className="text-muted-foreground absolute top-1/2 right-3 -translate-y-1/2"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </form>
                )}
                <button
                    type="button"
                    onClick={() => setOpen((v) => !v)}
                    aria-expanded={open}
                    className={`flex h-12 shrink-0 items-center gap-2 rounded-2xl border px-4 text-[13px] font-bold ${
                        open || activeCount > 0
                            ? 'border-primary/40 bg-primary/[0.07] text-primary'
                            : 'border-border-soft bg-card text-muted-foreground'
                    }`}
                >
                    <ListFilter className="size-4" strokeWidth={2.2} />
                    Filter
                    {activeCount > 0 && (
                        <span className="bg-primary flex size-5 items-center justify-center rounded-full text-[11px] text-white">{activeCount}</span>
                    )}
                </button>
            </div>

            {open && (
                <div className="border-border-soft bg-card flex flex-col gap-3.5 rounded-2xl border p-4">
                    <div>
                        <p className="text-muted-foreground mb-1.5 text-[12px] font-bold">Tanggal dibuat</p>
                        <div className="grid grid-cols-2 gap-2">
                            <input
                                type="date"
                                aria-label="Dari tanggal"
                                value={filters.date_from ?? ''}
                                max={filters.date_to || undefined}
                                onChange={(e) => visit({ date_from: e.target.value || undefined })}
                                className={fieldClass}
                            />
                            <input
                                type="date"
                                aria-label="Sampai tanggal"
                                value={filters.date_to ?? ''}
                                min={filters.date_from || undefined}
                                onChange={(e) => visit({ date_to: e.target.value || undefined })}
                                className={fieldClass}
                            />
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {presets().map((p) => (
                                <button
                                    key={p.label}
                                    type="button"
                                    onClick={() => visit({ date_from: p.from, date_to: p.to })}
                                    className="border-border-soft text-muted-foreground hover:border-primary/40 rounded-full border px-3 py-1.5 text-[12px] font-bold"
                                >
                                    {p.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <FilterField label="Dibuat oleh">
                            <SearchableSelect
                                aria-label="Dibuat oleh"
                                placeholder="Cari nama..."
                                value={String(filters.created_by ?? '')}
                                onChange={(v) => visit({ created_by: v || undefined })}
                                options={options.creators.map((c) => ({ value: String(c.id), label: c.name }))}
                            />
                        </FilterField>

                        <FilterField label="Line">
                            <SearchableSelect
                                aria-label="Line"
                                placeholder="Cari line..."
                                value={String(filters.master_line_id ?? '')}
                                onChange={(v) => visit({ master_line_id: v || undefined })}
                                options={options.lines.map((l) => ({ value: String(l.id), label: l.name }))}
                            />
                        </FilterField>

                        <FilterField label="Flow">
                            <SearchableSelect
                                aria-label="Flow"
                                value={filters.flow ?? ''}
                                onChange={(v) => visit({ flow: v || undefined })}
                                options={options.flows.map((f) => ({ value: f.value, label: f.label }))}
                            />
                        </FilterField>

                        {extraFields.map((field) => (
                            <FilterField key={field.key} label={field.label}>
                                <SearchableSelect
                                    aria-label={field.label}
                                    value={filters[field.key] ?? ''}
                                    onChange={(v) => visit({ [field.key]: v || undefined })}
                                    options={field.options}
                                />
                            </FilterField>
                        ))}
                    </div>
                </div>
            )}

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {chips.map((chip) => (
                        <button
                            key={chip.label}
                            type="button"
                            onClick={() => visit(Object.fromEntries(chip.key.map((k) => [k, undefined])))}
                            className="bg-primary/[0.08] text-primary flex items-center gap-1 rounded-full py-1 pr-2 pl-3 text-[12px] font-bold"
                        >
                            {chip.label}
                            <X className="size-3.5" strokeWidth={2.4} />
                        </button>
                    ))}
                    <button type="button" onClick={clearAll} className="text-muted-foreground px-2 text-[12px] font-bold underline">
                        Reset
                    </button>
                </div>
            )}
        </div>
    );
}
