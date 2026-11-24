import { Head, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
    truncated: boolean;
};

const DAY = 86_400_000;
const LABEL_WIDTH = 240;
const MONTH_WIDTH = 96;

const toTime = (value: string | null): number | null => {
    if (!value) {
        return null;
    }

    const time = Date.parse(`${value}T00:00:00Z`);

    return Number.isNaN(time) ? null : time;
};

function startOfMonth(time: number): number {
    const d = new Date(time);

    return Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1);
}

function addMonths(time: number, count: number): number {
    const d = new Date(time);

    return Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + count, 1);
}

export default function ProjectGantt({
    projects,
    filters,
    options,
    truncated,
}: PageProps) {
    const { t, intlLocale } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = gantt().url;

    const times: number[] = [];

    projects.forEach((p) => {
        [p.start_date, p.end_date].forEach((v) => {
            const time = toTime(v);

            if (time !== null) {
                times.push(time);
            }
        });
        p.stages.forEach((s) =>
            [s.plan_start, s.plan_end, s.actual_start, s.actual_end].forEach(
                (v) => {
                    const time = toTime(v);

                    if (time !== null) {
                        times.push(time);
                    }
                },
            ),
        );
        p.milestones.forEach((m) => {
            const time = toTime(m.due_date);

            if (time !== null) {
                times.push(time);
            }
        });
    });

    const now = new Date();
    const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());

    times.push(today);

    const rangeStart = startOfMonth(Math.min(...times));
    const rangeEnd = addMonths(startOfMonth(Math.max(...times)), 1);
    const total = rangeEnd - rangeStart;

    const months: { time: number; label: string }[] = [];

    for (let m = rangeStart; m < rangeEnd; m = addMonths(m, 1)) {
        months.push({
            time: m,
            label: new Intl.DateTimeFormat(intlLocale, {
                month: 'short',
                year: '2-digit',
                timeZone: 'UTC',
            }).format(m),
        });
    }

    const timelineWidth = months.length * MONTH_WIDTH;
    const pct = (time: number) => ((time - rangeStart) / total) * 100;

    function bar(start: string | null, end: string | null) {
        const s = toTime(start);
        const e = toTime(end);

        if (s === null && e === null) {
            return null;
        }

        const from = s ?? (e as number);
        const to = Math.max(e ?? from, from) + DAY;

        return { left: pct(from), width: Math.max(pct(to) - pct(from), 0.6) };
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        setForm({ project: '', pic: '', status: '' });
        router.get(url);
    }

    function setField(patch: Partial<Filters>) {
        const next = { ...form, ...patch };
        setForm(next);
        router.get(url, next, { preserveState: true, replace: true });
    }

    const todayLeft = pct(today);

    return (
        <>
            <Head title={t('projects.gantt.head_title')} />

            <div className="space-y-6 p-4">
                <Heading
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

                <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-6 rounded-sm border border-brand/60 bg-brand/15" />
                        {t('projects.gantt.legend_plan')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2.5 w-6 rounded-sm bg-brand" />
                        {t('projects.gantt.legend_actual')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rotate-45 bg-amber-500" />
                        {t('projects.gantt.legend_milestone')}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-3 w-px bg-red-500" />
                        {t('projects.gantt.today')}
                    </span>
                </div>

                {truncated && (
                    <p className="text-sm text-muted-foreground">
                        {t('projects.gantt.truncated', {
                            count: projects.length,
                        })}
                    </p>
                )}

                {projects.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            {t('projects.gantt.empty')}
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="overflow-hidden py-0">
                        <div className="overflow-x-auto">
                            <div
                                style={{
                                    minWidth: LABEL_WIDTH + timelineWidth,
                                }}
                            >
                                {/* Month header */}
                                <div className="flex border-b bg-muted/40 text-xs text-muted-foreground">
                                    <div
                                        className="sticky left-0 z-20 shrink-0 border-r bg-muted px-3 py-2 font-medium"
                                        style={{ width: LABEL_WIDTH }}
                                    >
                                        {t('projects.gantt.project')}
                                    </div>
                                    <div
                                        className="relative flex-1"
                                        style={{ minWidth: timelineWidth }}
                                    >
                                        <div className="flex">
                                            {months.map((m) => (
                                                <div
                                                    key={m.time}
                                                    className="border-r px-2 py-2 text-center last:border-r-0"
                                                    style={{
                                                        width: `${(100 / months.length).toFixed(4)}%`,
                                                    }}
                                                >
                                                    {m.label}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {projects.map((p) => (
                                    <ProjectRows
                                        key={p.id}
                                        project={p}
                                        months={months.length}
                                        todayLeft={todayLeft}
                                        timelineWidth={timelineWidth}
                                        bar={bar}
                                        pct={pct}
                                    />
                                ))}
                            </div>
                        </div>
                    </Card>
                )}

                <p className="text-xs text-muted-foreground">
                    {t('projects.gantt.no_dates')}
                </p>
            </div>
        </>
    );
}

function Grid({
    months,
    todayLeft,
}: {
    readonly months: number;
    readonly todayLeft: number;
}) {
    return (
        <>
            <div className="pointer-events-none absolute inset-0 flex">
                {Array.from({ length: months }).map((_, i) => (
                    <div
                        key={i}
                        className="border-r last:border-r-0"
                        style={{ width: `${100 / months}%` }}
                    />
                ))}
            </div>
            <div
                className="pointer-events-none absolute inset-y-0 z-[1] w-px bg-red-500/70"
                style={{ left: `${todayLeft}%` }}
            />
        </>
    );
}

function ProjectRows({
    project,
    months,
    todayLeft,
    timelineWidth,
    bar,
    pct,
}: {
    readonly project: GanttProject;
    readonly months: number;
    readonly todayLeft: number;
    readonly timelineWidth: number;
    readonly bar: (
        start: string | null,
        end: string | null,
    ) => { left: number; width: number } | null;
    readonly pct: (time: number) => number;
}) {
    const { t } = useTranslation();
    const projectBar = bar(project.start_date, project.end_date);

    return (
        <div className="border-b last:border-b-0">
            {/* Project summary row */}
            <div className="flex bg-muted/20">
                <div
                    className="sticky left-0 z-10 shrink-0 space-y-1 border-r bg-card px-3 py-2"
                    style={{ width: LABEL_WIDTH }}
                >
                    <Link
                        href={show(project.id).url}
                        className="block truncate text-sm font-semibold underline-offset-2 hover:underline"
                        title={project.name}
                    >
                        {project.name}
                    </Link>
                    <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                        <Badge
                            variant="outline"
                            className={projectStatusClassName(project.status)}
                        >
                            {projectStatusLabel(t, project.status)}
                        </Badge>
                        {project.pic_name && <span>{project.pic_name}</span>}
                    </div>
                </div>
                <div
                    className="relative flex-1"
                    style={{ minWidth: timelineWidth }}
                >
                    <Grid months={months} todayLeft={todayLeft} />
                    {projectBar && (
                        <div
                            className="absolute top-1/2 h-3 -translate-y-1/2 overflow-hidden rounded-sm bg-foreground/15"
                            style={{
                                left: `${projectBar.left}%`,
                                width: `${projectBar.width}%`,
                            }}
                            title={`${project.name} · ${project.progress}%`}
                        >
                            <div
                                className="h-full bg-foreground/50"
                                style={{ width: `${project.progress}%` }}
                            />
                        </div>
                    )}
                    {project.milestones.map((m) => {
                        const time = toTime(m.due_date);

                        if (time === null) {
                            return null;
                        }

                        return (
                            <div
                                key={m.id}
                                className={cn(
                                    'absolute top-1/2 z-[2] size-3 -translate-x-1/2 -translate-y-1/2 rotate-45',
                                    m.status === 'Done'
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500',
                                )}
                                style={{ left: `${pct(time)}%` }}
                                title={`${t('projects.gantt.milestone')}: ${m.name} (${m.due_date})`}
                            />
                        );
                    })}
                </div>
            </div>

            {project.stages.length === 0 && (
                <div className="flex">
                    <div
                        className="sticky left-0 z-10 shrink-0 border-r bg-card px-3 py-1.5 pl-6 text-xs text-muted-foreground"
                        style={{ width: LABEL_WIDTH }}
                    >
                        {t('projects.gantt.no_stages')}
                    </div>
                    <div
                        className="relative flex-1"
                        style={{ minWidth: timelineWidth }}
                    >
                        <Grid months={months} todayLeft={todayLeft} />
                    </div>
                </div>
            )}

            {project.stages.map((s) => {
                const plan = bar(s.plan_start, s.plan_end);
                const actual = bar(s.actual_start, s.actual_end);
                const late = s.status === 'Delayed';

                return (
                    <div key={s.id} className="flex">
                        <div
                            className="sticky left-0 z-10 flex shrink-0 items-center gap-1.5 border-r bg-card px-3 py-1.5 pl-6 text-xs"
                            style={{ width: LABEL_WIDTH }}
                        >
                            <span className="truncate" title={s.name}>
                                {s.name}
                            </span>
                            {s.is_parallel && (
                                <Badge variant="outline" className="shrink-0">
                                    {t('projects.gantt.parallel')}
                                </Badge>
                            )}
                        </div>
                        <div
                            className="relative h-9 flex-1"
                            style={{ minWidth: timelineWidth }}
                        >
                            <Grid months={months} todayLeft={todayLeft} />
                            {plan && (
                                <div
                                    className="absolute top-1.5 h-2.5 rounded-sm border border-brand/60 bg-brand/15"
                                    style={{
                                        left: `${plan.left}%`,
                                        width: `${plan.width}%`,
                                    }}
                                    title={`${t('projects.gantt.plan')}: ${s.plan_start ?? '-'} → ${s.plan_end ?? '-'}`}
                                />
                            )}
                            {actual && (
                                <div
                                    className={cn(
                                        'absolute top-5 h-2.5 overflow-hidden rounded-sm',
                                        late ? 'bg-red-500/30' : 'bg-brand/30',
                                    )}
                                    style={{
                                        left: `${actual.left}%`,
                                        width: `${actual.width}%`,
                                    }}
                                    title={`${t('projects.gantt.actual')}: ${s.actual_start ?? '-'} → ${s.actual_end ?? '-'} · ${s.progress}%`}
                                >
                                    <div
                                        className={cn(
                                            'h-full',
                                            late ? 'bg-red-500' : 'bg-brand',
                                        )}
                                        style={{ width: `${s.progress}%` }}
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
