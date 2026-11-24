import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    ChevronsDownUp,
    AlertTriangle,
    CheckCircle2,
    Circle,
    CircleDashed,
    Flag,
    FolderKanban,
    PauseCircle,
    Split,
    ChevronsUpDown,
    CalendarClock,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { SectionTabs } from '@/components/projects/section-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/use-translation';
import { projectStatusClassName, projectStatusLabel } from '@/lib/project';
import { cn } from '@/lib/utils';
import { gantt, show } from '@/routes/projects';

type Stage = {
    id: number;
    name: string;
    status: string;
    progress: number;
    is_parallel: boolean;
    plan_start: string | null;
    plan_end: string | null;
    actual_start: string | null;
    actual_end: string | null;
};

type Milestone = {
    id: number;
    name: string;
    due_date: string | null;
    status: string;
};

type GanttProject = {
    id: number;
    name: string;
    code: string;
    status: string;
    category: string | null;
    pic_name: string;
    progress: number;
    start_date: string | null;
    end_date: string | null;
    stages: Stage[];
    milestones: Milestone[];
};

type Filters = { project: string; pic: string; status: string };

type PageProps = {
    projects: GanttProject[];
    filters: Filters;
    options: {
        projects: { value: string; label: string }[];
        pics: string[];
        statuses: string[];
    };
    showCompleted: boolean;
    grouped: boolean;
    categories: string[];
    completedCount: number;
    pagination: { current_page: number; last_page: number; total: number };
};

const DAY = 86_400_000;
const SCALES = [
    { key: 'week', px: 24, label: 'projects.gantt.scale_week' },
    { key: 'month', px: 8, label: 'projects.gantt.scale_month' },
    { key: 'quarter', px: 3, label: 'projects.gantt.scale_quarter' },
] as const;

/** Colour per project category (index in the sorted category list). */
const CATEGORY_PALETTE = [
    { solid: 'bg-blue-500', soft: 'bg-blue-500/20' },
    { solid: 'bg-purple-500', soft: 'bg-purple-500/20' },
    { solid: 'bg-amber-500', soft: 'bg-amber-500/20' },
    { solid: 'bg-pink-500', soft: 'bg-pink-500/20' },
    { solid: 'bg-teal-500', soft: 'bg-teal-500/20' },
    { solid: 'bg-orange-500', soft: 'bg-orange-500/20' },
];
const ROW_H = 44;
const HEADER_H = 56;

/** Solid / soft colour pair per stage status (works in light + dark). */
type StatusStyle = {
    solid: string;
    soft: string;
    text: string;
    Icon: typeof Circle;
    spin?: boolean;
};

const STATUS_STYLE: Record<string, StatusStyle> = {
    Completed: {
        solid: 'bg-emerald-500',
        soft: 'bg-emerald-500/20 ring-emerald-500/40',
        text: 'text-emerald-500',
        Icon: CheckCircle2,
    },
    'On Progress': {
        solid: 'bg-sky-500',
        soft: 'bg-sky-500/20 ring-sky-500/40',
        text: 'text-sky-500',
        Icon: CircleDashed,
        spin: true,
    },
    Delayed: {
        solid: 'bg-red-500',
        soft: 'bg-red-500/20 ring-red-500/40',
        text: 'text-red-500',
        Icon: AlertTriangle,
    },
    'On Hold': {
        solid: 'bg-amber-500',
        soft: 'bg-amber-500/20 ring-amber-500/40',
        text: 'text-amber-500',
        Icon: PauseCircle,
    },
    'Not Started': {
        solid: 'bg-slate-400',
        soft: 'bg-slate-400/20 ring-slate-400/40',
        text: 'text-slate-400',
        Icon: Circle,
    },
};

const styleFor = (status: string) =>
    STATUS_STYLE[status] ?? STATUS_STYLE['Not Started'];

const toTime = (value: string | null): number | null => {
    if (!value) {
        return null;
    }

    const time = Date.parse(`${value}T00:00:00Z`);

    return Number.isNaN(time) ? null : time;
};

export default function ProjectGantt({
    projects,
    filters,
    options,
    showCompleted,
    grouped,
    categories,
    completedCount,
    pagination,
}: PageProps) {
    const { t, intlLocale, formatDateOnly } = useTranslation();
    const isMobile = useIsMobile();
    const labelW = isMobile ? 148 : 248;
    const [form, setForm] = useState<Filters>(filters);
    // Projects start collapsed (a single project starts open); this set holds
    // the ids the user flipped away from that default.
    const [flipped, setFlipped] = useState<Set<number>>(new Set());
    const [zoom, setZoom] = useState<number | null>(null); // null = fit
    const [width, setWidth] = useState(0);
    const [showActual, setShowActual] = useState(true);
    const scroller = useRef<HTMLDivElement>(null);
    const url = gantt().url;

    // --- Visible date range: fit the data, not a fixed year -----------------
    const { rangeStart, rangeEnd, today } = useMemo(() => {
        const times: number[] = [];
        const push = (v: string | null) => {
            const time = toTime(v);

            if (time !== null) {
                times.push(time);
            }
        };

        projects.forEach((p) => {
            push(p.start_date);
            push(p.end_date);
            p.stages.forEach((s) =>
                [
                    s.plan_start,
                    s.plan_end,
                    s.actual_start,
                    s.actual_end,
                ].forEach(push),
            );
            p.milestones.forEach((m) => push(m.due_date));
        });

        const now = new Date();
        const todayTime = Date.UTC(
            now.getFullYear(),
            now.getMonth(),
            now.getDate(),
        );

        if (times.length === 0) {
            times.push(todayTime);
        }

        const min = Math.min(...times);
        const max = Math.max(...times);
        // Show today only when it is reasonably close to the data.
        const todayVisible =
            todayTime >= min - 30 * DAY && todayTime <= max + 30 * DAY;
        const lo = todayVisible ? Math.min(min, todayTime) : min;
        const hi = todayVisible ? Math.max(max, todayTime) : max;

        return {
            rangeStart: lo - 7 * DAY,
            rangeEnd: hi + 14 * DAY,
            today: todayVisible ? todayTime : null,
        };
    }, [projects]);

    const days = Math.max(1, Math.round((rangeEnd - rangeStart) / DAY));

    useEffect(() => {
        const el = scroller.current;

        if (!el) {
            return;
        }

        const observer = new ResizeObserver(() => setWidth(el.clientWidth));
        observer.observe(el);
        setWidth(el.clientWidth);

        return () => observer.disconnect();
    }, [projects.length]);

    const fitPx = Math.min(40, Math.max(3, (width - labelW - 24) / days || 3));
    const px = zoom ?? fitPx;
    const timelineW = Math.ceil(days * px);
    const x = useCallback(
        (time: number) => ((time - rangeStart) / DAY) * px,
        [rangeStart, px],
    );

    // Centre the view on today once the timeline is wider than the viewport.
    const scrollToToday = useCallback(() => {
        const el = scroller.current;

        if (!el || today === null) {
            return;
        }

        el.scrollTo({
            left: Math.max(0, x(today) - (el.clientWidth - labelW) / 3),
            behavior: 'smooth',
        });
    }, [today, x, labelW]);

    useEffect(() => {
        if (zoom !== null) {
            return;
        }

        scroller.current?.scrollTo({ left: 0 });
    }, [zoom, projects]);

    // --- Axis ---------------------------------------------------------------
    const months = useMemo(() => {
        const list: { left: number; width: number; label: string }[] = [];
        const start = new Date(rangeStart);
        let cursor = Date.UTC(start.getUTCFullYear(), start.getUTCMonth(), 1);
        const long = new Intl.DateTimeFormat(intlLocale, {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        });
        const short = new Intl.DateTimeFormat(intlLocale, {
            month: 'short',
            year: '2-digit',
            timeZone: 'UTC',
        });

        while (cursor < rangeEnd) {
            const d = new Date(cursor);
            const next = Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 1);
            const from = Math.max(cursor, rangeStart);
            const to = Math.min(next, rangeEnd);

            list.push({
                left: ((from - rangeStart) / DAY) * px,
                width: ((to - from) / DAY) * px,
                label: (((to - from) / DAY) * px >= 120 ? long : short).format(
                    cursor,
                ),
            });
            cursor = next;
        }

        return list;
    }, [rangeStart, rangeEnd, px, intlLocale]);

    const weeks = useMemo(() => {
        if (px < 10) {
            return [];
        }

        const list: { left: number; label: string }[] = [];
        const first = new Date(rangeStart);
        // next Monday on/after rangeStart
        const offset = (8 - first.getUTCDay()) % 7;
        let cursor = rangeStart + offset * DAY;

        while (cursor < rangeEnd) {
            list.push({
                left: ((cursor - rangeStart) / DAY) * px,
                label: String(new Date(cursor).getUTCDate()),
            });
            cursor += 7 * DAY;
        }

        return list;
    }, [rangeStart, rangeEnd, px]);

    // --- Bars ----------------------------------------------------------------
    function span(start: string | null, end: string | null) {
        const s = toTime(start);
        const e = toTime(end);

        if (s === null && e === null) {
            return null;
        }

        const from = s ?? (e as number);
        const to = Math.max(e ?? from, from) + DAY;

        return { left: x(from), width: Math.max(x(to) - x(from), 10) };
    }

    function visit(
        patch: { show_completed?: number; group?: number } = {},
        f: Filters = form,
    ) {
        router.get(
            url,
            {
                ...f,
                show_completed: showCompleted ? 1 : undefined,
                group: grouped ? 1 : undefined,
                ...patch,
            },
            { preserveState: true, replace: true },
        );
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        visit();
    }

    function reset() {
        setForm({ project: '', pic: '', status: '' });
        router.get(url);
    }

    function toggleCompleted(next: boolean) {
        visit({ show_completed: next ? 1 : undefined });
    }

    function toggleGrouped(next: boolean) {
        visit({ group: next ? 1 : undefined });
    }

    function setField(patch: Partial<Filters>) {
        const next = { ...form, ...patch };
        setForm(next);
        visit({}, next);
    }

    const defaultOpen = projects.length === 1;
    const isOpen = (id: number) => defaultOpen !== flipped.has(id);
    const allOpen = projects.length > 0 && projects.every((p) => isOpen(p.id));

    function toggle(id: number) {
        setFlipped((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    }

    function toggleAll() {
        // Target state: everything closed if currently all open, else all open.
        const wantOpen = !allOpen;
        setFlipped(
            wantOpen === defaultOpen
                ? new Set()
                : new Set(projects.map((p) => p.id)),
        );
    }

    const categoryStyle = (category: string | null) => {
        const i = category ? categories.indexOf(category) : -1;

        return i < 0
            ? { solid: 'bg-foreground/60', soft: 'bg-foreground/15' }
            : CATEGORY_PALETTE[i % CATEGORY_PALETTE.length];
    };
    const dates = (a: string | null, b: string | null) =>
        a || b
            ? `${a ? formatDateOnly(a) : '-'} → ${b ? formatDateOnly(b) : '-'}`
            : '-';

    return (
        <>
            <Head title={t('projects.gantt.head_title')} />

            <TooltipProvider delayDuration={80}>
                <div className="space-y-5 p-4">
                    <SectionTabs section="portfolio" active="gantt" />
                    <Heading
                        className="space-y-0.5"
                        title={t('projects.gantt.title')}
                        description={t('projects.gantt.description')}
                    />

                    <FilterBar
                        onSubmit={submit}
                        onReset={reset}
                        hasActiveFilters={Object.values(filters).some(Boolean)}
                    >
                        <FilterSelect
                            label={t('projects.gantt.project')}
                            value={form.project}
                            onChange={(project) => setField({ project })}
                            options={options.projects}
                            placeholder={t('projects.gantt.all_projects')}
                        />
                        <FilterSelect
                            label={t('projects.fields.pic')}
                            value={form.pic}
                            onChange={(pic) => setField({ pic })}
                            options={options.pics}
                            placeholder={t('projects.gantt.all_pics')}
                        />
                        <FilterSelect
                            label={t('projects.fields.status')}
                            value={form.status}
                            onChange={(status) => setField({ status })}
                            options={options.statuses.map((value) => ({
                                value,
                                label: projectStatusLabel(t, value),
                            }))}
                            placeholder={t('projects.gantt.all_statuses')}
                        />
                    </FilterBar>

                    {projects.length === 0 ? (
                        <Card>
                            <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                {t('projects.gantt.empty')}
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="gap-0 overflow-hidden py-0">
                            {/* Toolbar */}
                            <div className="space-y-2.5 border-b px-3 py-2.5">
                                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    <div
                                        role="group"
                                        aria-label={t('projects.gantt.scale')}
                                        className="inline-flex rounded-lg border bg-muted/50 p-0.5"
                                    >
                                        {[
                                            ...SCALES.map((sc) => ({
                                                key: sc.key,
                                                label: t(sc.label),
                                                active: zoom === sc.px,
                                                set: () => setZoom(sc.px),
                                            })),
                                            {
                                                key: 'fit',
                                                label: t(
                                                    'projects.gantt.scale_fit',
                                                ),
                                                active: zoom === null,
                                                set: () => setZoom(null),
                                            },
                                        ].map((o) => (
                                            <button
                                                key={o.key}
                                                type="button"
                                                aria-pressed={o.active}
                                                onClick={o.set}
                                                className={cn(
                                                    'rounded-md px-3 py-1 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                                    o.active
                                                        ? 'bg-brand text-white shadow-sm'
                                                        : 'text-muted-foreground hover:text-foreground',
                                                )}
                                            >
                                                {o.label}
                                            </button>
                                        ))}
                                    </div>

                                    <ToggleCheck
                                        checked={grouped}
                                        onChange={toggleGrouped}
                                        label={t(
                                            'projects.gantt.group_by_category',
                                        )}
                                    />
                                    <ToggleCheck
                                        checked={showActual}
                                        onChange={setShowActual}
                                        label={t(
                                            'projects.gantt.plan_vs_actual',
                                        )}
                                    />
                                    <ToggleCheck
                                        checked={showCompleted}
                                        onChange={toggleCompleted}
                                        label={t(
                                            'projects.gantt.show_completed',
                                        )}
                                        count={completedCount}
                                    />

                                    <div className="ml-auto flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={toggleAll}
                                        >
                                            {allOpen ? (
                                                <ChevronsDownUp />
                                            ) : (
                                                <ChevronsUpDown />
                                            )}
                                            {allOpen
                                                ? t(
                                                      'projects.gantt.collapse_all',
                                                  )
                                                : t(
                                                      'projects.gantt.expand_all',
                                                  )}
                                        </Button>
                                        {today !== null && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={scrollToToday}
                                            >
                                                <CalendarClock />
                                                {t('projects.gantt.today')}
                                            </Button>
                                        )}
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted-foreground">
                                    {categories.map((cat) => (
                                        <span
                                            key={cat}
                                            className="flex items-center gap-1.5"
                                        >
                                            <span
                                                className={cn(
                                                    'size-2.5 rounded-sm',
                                                    categoryStyle(cat).solid,
                                                )}
                                            />
                                            {cat}
                                        </span>
                                    ))}
                                    {categories.length > 0 && (
                                        <span className="h-3 w-px bg-border" />
                                    )}
                                    {Object.entries(STATUS_STYLE).map(
                                        ([status, c]) => (
                                            <span
                                                key={status}
                                                className="flex items-center gap-1.5"
                                            >
                                                <c.Icon
                                                    className={cn(
                                                        'size-3.5',
                                                        c.text,
                                                    )}
                                                />
                                                {projectStatusLabel(t, status)}
                                            </span>
                                        ),
                                    )}
                                    <span className="flex items-center gap-1.5">
                                        <Flag className="size-3.5 fill-amber-500 text-amber-500" />
                                        {t('projects.gantt.legend_milestone')}
                                    </span>
                                </div>
                            </div>

                            {/* Chart */}
                            <div
                                ref={scroller}
                                className="max-h-[70vh] overflow-auto"
                            >
                                <div
                                    style={{ width: labelW + timelineW }}
                                    className="relative"
                                >
                                    {/* Axis header */}
                                    <div
                                        className="sticky top-0 z-30 flex border-b bg-card"
                                        style={{ height: HEADER_H }}
                                    >
                                        <div
                                            className="sticky left-0 z-40 flex shrink-0 items-end border-r bg-card px-3 pb-2 text-xs font-medium text-muted-foreground"
                                            style={{ width: labelW }}
                                        >
                                            {t('projects.gantt.project')} /{' '}
                                            {t('projects.stage.title')}
                                        </div>
                                        <div
                                            className="relative"
                                            style={{ width: timelineW }}
                                        >
                                            {months.map((m) => (
                                                <div
                                                    key={m.left}
                                                    className="absolute top-0 truncate border-l px-2 pt-2 text-xs font-medium"
                                                    style={{
                                                        left: m.left,
                                                        width: m.width,
                                                        height: HEADER_H,
                                                    }}
                                                >
                                                    {m.label}
                                                </div>
                                            ))}
                                            {weeks.map((w) => (
                                                <div
                                                    key={w.left}
                                                    className="absolute bottom-1 -translate-x-1/2 text-[10px] text-muted-foreground"
                                                    style={{ left: w.left }}
                                                >
                                                    {w.label}
                                                </div>
                                            ))}
                                            {today !== null && (
                                                <div
                                                    className="absolute bottom-0 z-10 flex -translate-x-1/2 items-center gap-1 rounded-t bg-red-500 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                                                    style={{ left: x(today) }}
                                                >
                                                    <span className="relative flex size-1.5">
                                                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-white/80" />
                                                        <span className="relative inline-flex size-1.5 rounded-full bg-white" />
                                                    </span>
                                                    {t('projects.gantt.today')}
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {projects.map((p, i) => {
                                        const open = isOpen(p.id);
                                        const catStyle = categoryStyle(
                                            p.category,
                                        );
                                        const newGroup =
                                            grouped &&
                                            (i === 0 ||
                                                projects[i - 1].category !==
                                                    p.category);
                                        const bar = span(
                                            p.start_date,
                                            p.end_date,
                                        );

                                        return (
                                            <div
                                                key={p.id}
                                                className="border-b"
                                            >
                                                {newGroup && (
                                                    <div
                                                        className="flex border-b bg-muted/60"
                                                        style={{
                                                            width:
                                                                labelW +
                                                                timelineW,
                                                        }}
                                                    >
                                                        <div className="sticky left-0 z-20 flex items-center gap-2 px-3 py-1.5 text-xs font-semibold tracking-wide uppercase">
                                                            <span
                                                                className={cn(
                                                                    'size-2.5 rounded-sm',
                                                                    catStyle.solid,
                                                                )}
                                                            />
                                                            {p.category ||
                                                                t(
                                                                    'projects.gantt.uncategorized',
                                                                )}
                                                            <span className="font-normal text-muted-foreground">
                                                                (
                                                                {
                                                                    projects.filter(
                                                                        (q) =>
                                                                            q.category ===
                                                                            p.category,
                                                                    ).length
                                                                }
                                                                )
                                                            </span>
                                                        </div>
                                                    </div>
                                                )}
                                                <Row
                                                    labelW={labelW}
                                                    timelineW={timelineW}
                                                    months={months}
                                                    todayX={
                                                        today === null
                                                            ? null
                                                            : x(today)
                                                    }
                                                    className="bg-muted/30"
                                                    label={
                                                        <div className="flex min-w-0 items-center gap-1.5">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    toggle(p.id)
                                                                }
                                                                aria-expanded={
                                                                    open
                                                                }
                                                                aria-label={
                                                                    p.name
                                                                }
                                                                className="shrink-0 rounded p-0.5 text-muted-foreground outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                                                            >
                                                                {open ? (
                                                                    <ChevronDown className="size-4" />
                                                                ) : (
                                                                    <ChevronRight className="size-4" />
                                                                )}
                                                            </button>
                                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-brand/10 text-brand">
                                                                <FolderKanban className="size-4" />
                                                            </span>
                                                            <div className="min-w-0">
                                                                <Link
                                                                    href={
                                                                        show(
                                                                            p.id,
                                                                        ).url
                                                                    }
                                                                    className="block truncate text-sm font-semibold underline-offset-2 hover:underline"
                                                                    title={
                                                                        p.name
                                                                    }
                                                                >
                                                                    {p.name}
                                                                </Link>
                                                                <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={cn(
                                                                            'h-4 px-1.5 text-[10px]',
                                                                            projectStatusClassName(
                                                                                p.status,
                                                                            ),
                                                                        )}
                                                                    >
                                                                        {projectStatusLabel(
                                                                            t,
                                                                            p.status,
                                                                        )}
                                                                    </Badge>
                                                                    {p.pic_name && (
                                                                        <span className="truncate">
                                                                            {
                                                                                p.pic_name
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    }
                                                >
                                                    {bar && (
                                                        <Tip
                                                            title={p.name}
                                                            lines={[
                                                                dates(
                                                                    p.start_date,
                                                                    p.end_date,
                                                                ),
                                                                `${p.progress}%`,
                                                            ]}
                                                        >
                                                            <div
                                                                className={cn(
                                                                    'absolute top-1/2 h-4 -translate-y-1/2 overflow-hidden rounded-full',
                                                                    catStyle.soft,
                                                                )}
                                                                style={{
                                                                    left: bar.left,
                                                                    width: bar.width,
                                                                }}
                                                            >
                                                                <div
                                                                    className={cn(
                                                                        'h-full rounded-full',
                                                                        catStyle.solid,
                                                                    )}
                                                                    style={{
                                                                        width: `${p.progress}%`,
                                                                    }}
                                                                />
                                                            </div>
                                                        </Tip>
                                                    )}
                                                    {p.milestones.map((m) => {
                                                        const time = toTime(
                                                            m.due_date,
                                                        );

                                                        if (time === null) {
                                                            return null;
                                                        }

                                                        return (
                                                            <Tip
                                                                key={m.id}
                                                                title={`${t('projects.gantt.milestone')}: ${m.name}`}
                                                                lines={[
                                                                    formatDateOnly(
                                                                        m.due_date as string,
                                                                    ),
                                                                ]}
                                                            >
                                                                <Flag
                                                                    className={cn(
                                                                        'absolute top-1/2 z-[2] size-4 -translate-x-1/2 -translate-y-1/2 cursor-pointer drop-shadow transition-transform hover:scale-125',
                                                                        m.status ===
                                                                            'Done'
                                                                            ? 'fill-emerald-500 text-emerald-500'
                                                                            : 'fill-amber-500 text-amber-500',
                                                                    )}
                                                                    style={{
                                                                        left: x(
                                                                            time,
                                                                        ),
                                                                    }}
                                                                />
                                                            </Tip>
                                                        );
                                                    })}
                                                </Row>

                                                {open &&
                                                    p.stages.length === 0 && (
                                                        <Row
                                                            labelW={labelW}
                                                            timelineW={
                                                                timelineW
                                                            }
                                                            months={months}
                                                            todayX={
                                                                today === null
                                                                    ? null
                                                                    : x(today)
                                                            }
                                                            height={32}
                                                            label={
                                                                <span className="pl-7 text-xs text-muted-foreground">
                                                                    {t(
                                                                        'projects.gantt.no_stages',
                                                                    )}
                                                                </span>
                                                            }
                                                        />
                                                    )}

                                                {open &&
                                                    p.stages.map((s) => {
                                                        const plan = span(
                                                            s.plan_start,
                                                            s.plan_end,
                                                        );
                                                        const actual = span(
                                                            s.actual_start,
                                                            s.actual_end,
                                                        );
                                                        const main =
                                                            plan ?? actual;
                                                        const c = styleFor(
                                                            s.status,
                                                        );

                                                        return (
                                                            <Row
                                                                key={s.id}
                                                                labelW={labelW}
                                                                timelineW={
                                                                    timelineW
                                                                }
                                                                months={months}
                                                                todayX={
                                                                    today ===
                                                                    null
                                                                        ? null
                                                                        : x(
                                                                              today,
                                                                          )
                                                                }
                                                                label={
                                                                    <div className="flex min-w-0 items-center gap-1.5 pl-7 text-xs">
                                                                        <c.Icon
                                                                            className={cn(
                                                                                'size-4 shrink-0',
                                                                                c.text,
                                                                                c.spin &&
                                                                                    'animate-[spin_3s_linear_infinite]',
                                                                            )}
                                                                        />
                                                                        <span
                                                                            className="truncate"
                                                                            title={
                                                                                s.name
                                                                            }
                                                                        >
                                                                            {
                                                                                s.name
                                                                            }
                                                                        </span>
                                                                        {s.is_parallel && (
                                                                            <Split
                                                                                className="size-3.5 shrink-0 text-muted-foreground"
                                                                                aria-label={t(
                                                                                    'projects.gantt.parallel',
                                                                                )}
                                                                            />
                                                                        )}
                                                                    </div>
                                                                }
                                                            >
                                                                {main && (
                                                                    <Tip
                                                                        title={
                                                                            s.name
                                                                        }
                                                                        lines={[
                                                                            `${t('projects.gantt.plan')}: ${dates(s.plan_start, s.plan_end)}`,
                                                                            `${t('projects.gantt.actual')}: ${dates(s.actual_start, s.actual_end)}`,
                                                                            `${projectStatusLabel(t, s.status)} · ${s.progress}%`,
                                                                        ]}
                                                                    >
                                                                        <div
                                                                            className={cn(
                                                                                'absolute top-1/2 flex h-[18px] -translate-y-[calc(50%+3px)] cursor-pointer items-center overflow-hidden rounded-full ring-1 transition-all ring-inset hover:scale-y-110 hover:shadow-md',
                                                                                c.soft,
                                                                            )}
                                                                            style={{
                                                                                left: main.left,
                                                                                width: main.width,
                                                                            }}
                                                                        >
                                                                            <div
                                                                                className={cn(
                                                                                    'h-full rounded-full transition-[width] duration-700',
                                                                                    c.solid,
                                                                                )}
                                                                                style={{
                                                                                    width: `${s.progress}%`,
                                                                                }}
                                                                            />
                                                                            {main.width >
                                                                                44 && (
                                                                                <span className="absolute inset-0 flex items-center justify-center text-[10px] font-semibold text-foreground mix-blend-normal">
                                                                                    {
                                                                                        s.progress
                                                                                    }

                                                                                    %
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                    </Tip>
                                                                )}
                                                                {showActual &&
                                                                    plan &&
                                                                    actual && (
                                                                        <div
                                                                            className="absolute top-1/2 h-1 translate-y-[9px] rounded-full bg-foreground/55"
                                                                            style={{
                                                                                left: actual.left,
                                                                                width: actual.width,
                                                                            }}
                                                                        />
                                                                    )}
                                                            </Row>
                                                        );
                                                    })}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </Card>
                    )}

                    {pagination.last_page > 1 && (
                        <PaginationFooter
                            url={url}
                            query={{
                                ...filters,
                                group: grouped ? 1 : undefined,
                                show_completed: showCompleted ? 1 : undefined,
                            }}
                            currentPage={pagination.current_page}
                            lastPage={pagination.last_page}
                            total={pagination.total}
                            itemLabel={t('projects.gantt.item_label')}
                        />
                    )}

                    <p className="text-xs text-muted-foreground">
                        {t('projects.gantt.no_dates')}{' '}
                        {t('projects.gantt.hint')}
                    </p>
                </div>
            </TooltipProvider>
        </>
    );
}

function Tip({
    title,
    lines,
    children,
}: {
    readonly title: string;
    readonly lines: string[];
    readonly children: React.ReactElement;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent side="top" className="max-w-72 space-y-0.5">
                <p className="font-semibold">{title}</p>
                {lines.map((l) => (
                    <p key={l} className="opacity-90">
                        {l}
                    </p>
                ))}
            </TooltipContent>
        </Tooltip>
    );
}

function Row({
    label,
    labelW,
    timelineW,
    months,
    todayX,
    height = ROW_H,
    className,
    children,
}: {
    readonly label: React.ReactNode;
    readonly labelW: number;
    readonly timelineW: number;
    readonly months: { left: number }[];
    readonly todayX: number | null;
    readonly height?: number;
    readonly className?: string;
    readonly children?: React.ReactNode;
}) {
    return (
        <div className={cn('group flex hover:bg-muted/40', className)}>
            <div
                className="sticky left-0 z-20 flex shrink-0 items-center border-r bg-card px-3 group-hover:bg-muted/60"
                style={{ width: labelW, height }}
            >
                {label}
            </div>
            <div className="relative" style={{ width: timelineW, height }}>
                {months.map((m) => (
                    <div
                        key={m.left}
                        className="pointer-events-none absolute inset-y-0 border-l border-border/60"
                        style={{ left: m.left }}
                    />
                ))}
                {todayX !== null && (
                    <div
                        className="pointer-events-none absolute inset-y-0 z-[1] w-px bg-red-500/70"
                        style={{ left: todayX }}
                    />
                )}
                {children}
            </div>
        </div>
    );
}

function ToggleCheck({
    checked,
    onChange,
    label,
    count,
}: {
    readonly checked: boolean;
    readonly onChange: (next: boolean) => void;
    readonly label: string;
    readonly count?: number;
}) {
    return (
        <label className="flex cursor-pointer items-center gap-2 rounded-md border bg-background px-2.5 py-1.5 text-sm hover:bg-muted">
            <Checkbox
                checked={checked}
                onCheckedChange={(v) => onChange(v === true)}
            />
            <span>
                {label}
                {count !== undefined && (
                    <span className="ml-1 text-muted-foreground">
                        ({count})
                    </span>
                )}
            </span>
        </label>
    );
}
