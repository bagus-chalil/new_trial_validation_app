import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CalendarRange } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { ProgressBar } from '@/components/projects/progress-bar';
import { SectionTabs } from '@/components/projects/section-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { projectStatusClassName, projectStatusLabel } from '@/lib/project';
import { dashboard, index as portfolioIndex, show } from '@/routes/projects';
import { index as improvementsIndex } from '@/routes/projects/improvements';
import { index as purchaseOrdersIndex } from '@/routes/projects/purchase-orders';
import { index as quotationsIndex } from '@/routes/projects/quotations';
import {
    index as toolingsIndex,
    show as toolingShow,
} from '@/routes/projects/toolings';

type ProjectRef = {
    id: number;
    code: string;
    name: string;
    pic_name: string;
};

type Reminder = {
    kind: 'project' | 'stage' | 'milestone' | 'tooling';
    label: string;
    date: string;
    days: number;
    project: ProjectRef | null;
    tooling: {
        id: number;
        npd_no: string;
        status: string;
        is_critical: boolean;
    } | null;
};

type AttentionItem = {
    project: ProjectRef;
    sev: number;
    tag:
        | 'late'
        | 'at_risk'
        | 'on_hold'
        | 'deadline'
        | 'late_stage'
        | 'late_start';
    days: number | null;
    progress: number;
    planned: number;
    items: string[];
};

type StageDot = { name: string; status: string };

type BoardRow = {
    id: number;
    code: string;
    name: string;
    category: string;
    pic_name: string;
    status: string;
    progress: number;
    planned: number | null;
    at_risk: boolean;
    start_date: string | null;
    end_date: string | null;
    completed_on: string | null;
    stages_total: number;
    stages_done: number;
    late_stages: number;
    current_stage: string | null;
    stage_strip: StageDot[];
};

type Cards = {
    improvement: { count: number; new: number; avg_progress: number };
    tooling: { count: number; active: number; avg_progress: number };
    quotation: { count: number; waiting: number } | null;
    po: { count: number; waiting: number } | null;
};

type PageProps = {
    range: {
        period: string;
        start: string | null;
        end: string | null;
        valid: boolean;
        unlisted: number;
    };
    reminderPeriod: 'day' | 'week' | 'month';
    kpi: {
        total: number;
        on_progress: number;
        completed: number;
        delayed: number;
        on_hold: number;
        not_started: number;
        avg_progress: number;
    };
    statusDonut: { label: string; value: number }[];
    categoryDonut: { label: string; value: number }[];
    cards: Cards;
    reminders: Reminder[];
    attention: AttentionItem[];
    board: BoardRow[];
    can: { finance: boolean };
};

// Semantic status colours, always paired with a text label in the legend.
const STATUS_COLOR: Record<string, string> = {
    'On Progress': '#0284c7',
    Delayed: '#dc2626',
    'On Hold': '#d97706',
    'Not Started': '#94a3b8',
    Completed: '#059669',
};

const TAG_COLOR: Record<AttentionItem['tag'], string> = {
    late: 'bg-red-600',
    at_risk: 'bg-orange-500',
    on_hold: 'bg-amber-500',
    deadline: 'bg-sky-600',
    late_stage: 'bg-red-500',
    late_start: 'bg-orange-500',
};

function StatCard({
    label,
    value,
    sub,
    href,
    tone,
    progress,
}: {
    readonly label: string;
    readonly value: string | number;
    readonly sub?: string;
    readonly href?: string;
    readonly tone?: 'danger';
    readonly progress?: number;
}) {
    const body = (
        <Card className={href ? 'transition-colors hover:bg-muted/40' : ''}>
            <CardContent className="space-y-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    className={`text-2xl font-semibold tabular-nums ${tone === 'danger' && Number(value) > 0 ? 'text-red-600 dark:text-red-400' : ''}`}
                >
                    {value}
                </p>
                {sub && <p className="text-xs text-muted-foreground">{sub}</p>}
                {progress !== undefined && (
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-brand"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                )}
            </CardContent>
        </Card>
    );

    return href ? (
        <Link
            href={href}
            className="block rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            {body}
        </Link>
    ) : (
        body
    );
}

function Donut({
    data,
    centerLabel,
    hrefFor,
}: {
    readonly data: {
        label: string;
        value: number;
        color: string;
        text: string;
    }[];
    readonly centerLabel: string;
    readonly hrefFor: (label: string) => string;
}) {
    const total = data.reduce((sum, d) => sum + d.value, 0);
    const radius = 50;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    return (
        <div className="flex flex-wrap items-center gap-6">
            <svg
                viewBox="0 0 140 140"
                className="size-40 shrink-0"
                role="img"
                aria-label={`${total} ${centerLabel}`}
            >
                <circle
                    cx="70"
                    cy="70"
                    r={radius}
                    fill="none"
                    stroke="var(--muted)"
                    strokeWidth="20"
                />
                {total > 0 &&
                    data
                        .filter((d) => d.value > 0)
                        .map((d) => {
                            const length = (d.value / total) * circumference;
                            const el = (
                                <circle
                                    key={d.label}
                                    cx="70"
                                    cy="70"
                                    r={radius}
                                    fill="none"
                                    stroke={d.color}
                                    strokeWidth="20"
                                    strokeDasharray={`${length} ${circumference - length}`}
                                    strokeDashoffset={-offset}
                                    transform="rotate(-90 70 70)"
                                />
                            );
                            offset += length;

                            return el;
                        })}
                <text
                    x="70"
                    y="68"
                    textAnchor="middle"
                    fontSize="26"
                    fontWeight="700"
                    fill="currentColor"
                >
                    {total}
                </text>
                <text
                    x="70"
                    y="86"
                    textAnchor="middle"
                    fontSize="10"
                    fill="var(--muted-foreground)"
                >
                    {centerLabel}
                </text>
            </svg>
            <ul className="min-w-40 flex-1 space-y-1.5 text-sm">
                {data.map((d) => (
                    <li key={d.label}>
                        <Link
                            href={hrefFor(d.label)}
                            className="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-muted"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-full"
                                style={{ background: d.color }}
                            />
                            <span className="flex-1">{d.text}</span>
                            <b className="tabular-nums">{d.value}</b>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function dueLabel(
    t: (k: string, r?: Record<string, string | number>) => string,
    days: number,
): string {
    if (days < 0) {
        return t('projects.dashboard.reminders.overdue', {
            days: Math.abs(days),
        });
    }

    return days === 0
        ? t('projects.dashboard.reminders.today')
        : t('projects.dashboard.reminders.in_days', { days });
}

export default function ProjectDashboard({
    range,
    reminderPeriod,
    kpi,
    statusDonut,
    categoryDonut,
    cards,
    reminders,
    attention,
    board,
    can,
}: PageProps) {
    const { t } = useTranslation();
    const [start, setStart] = useState(range.start ?? '');
    const [end, setEnd] = useState(range.end ?? '');

    const go = (params: Record<string, string>) =>
        router.get(
            dashboard().url,
            {
                period: range.period,
                ...(range.period === 'custom'
                    ? { start: range.start ?? '', end: range.end ?? '' }
                    : {}),
                reminder: reminderPeriod,
                ...params,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    const listFor = (status: string) =>
        portfolioIndex({ query: status ? { status } : {} }).url;
    const categoryTotal = categoryDonut.reduce((s, c) => s + c.value, 0);
    const maxCategory = Math.max(1, ...categoryDonut.map((c) => c.value));

    const reminderTitle = t(
        `projects.dashboard.reminders.title_${reminderPeriod}`,
    );

    const attentionText = (a: AttentionItem): string => {
        const base = {
            days: a.days ?? 0,
            progress: a.progress,
            planned: a.planned,
            items: a.items.join(', '),
        };

        switch (a.tag) {
            case 'late':
                return a.days === null
                    ? t('projects.dashboard.attention.text.late_flagged', base)
                    : t('projects.dashboard.attention.text.late', base);
            default:
                return t(`projects.dashboard.attention.text.${a.tag}`, base);
        }
    };

    return (
        <>
            <Head title={t('projects.dashboard.head_title')} />

            <div className="space-y-6 p-4">
                <SectionTabs section="portfolio" active="dashboard" />

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        className="space-y-0.5"
                        title={t('projects.dashboard.title')}
                        description={t('projects.dashboard.description')}
                    />
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label className="flex items-center gap-1 text-xs text-muted-foreground">
                                <CalendarRange className="size-3.5" />
                                {t('projects.dashboard.period.label')}
                            </Label>
                            <Select
                                value={range.period}
                                onValueChange={(value) =>
                                    value === 'custom'
                                        ? go({
                                              period: 'custom',
                                              start: start || '',
                                              end: end || '',
                                          })
                                        : go({
                                              period: value,
                                              start: '',
                                              end: '',
                                          })
                                }
                            >
                                <SelectTrigger className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {[
                                        'all',
                                        'month',
                                        'quarter',
                                        'year',
                                        'custom',
                                    ].map((p) => (
                                        <SelectItem key={p} value={p}>
                                            {t(
                                                `projects.dashboard.period.${p}`,
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {range.period === 'custom' && (
                            <>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="dash-from"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {t('projects.dashboard.period.from')}
                                    </Label>
                                    <Input
                                        id="dash-from"
                                        type="date"
                                        value={start}
                                        onChange={(e) =>
                                            setStart(e.target.value)
                                        }
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor="dash-to"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {t('projects.dashboard.period.to')}
                                    </Label>
                                    <Input
                                        id="dash-to"
                                        type="date"
                                        value={end}
                                        onChange={(e) => setEnd(e.target.value)}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    onClick={() =>
                                        go({ period: 'custom', start, end })
                                    }
                                >
                                    {t('projects.dashboard.period.apply')}
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <p className="-mt-3 text-xs text-muted-foreground">
                    {range.valid
                        ? t('projects.dashboard.period.hint')
                        : t('projects.dashboard.period.invalid')}
                    {range.unlisted > 0 && (
                        <>
                            {' '}
                            {t('projects.dashboard.period.unlisted', {
                                count: range.unlisted,
                            })}
                        </>
                    )}
                </p>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <StatCard
                        label={t('projects.dashboard.kpi.total')}
                        value={kpi.total}
                        href={listFor('')}
                    />
                    <StatCard
                        label={t('projects.dashboard.kpi.on_progress')}
                        value={kpi.on_progress}
                        href={listFor('On Progress')}
                    />
                    <StatCard
                        label={t('projects.dashboard.kpi.completed')}
                        value={kpi.completed}
                        href={listFor('Completed')}
                    />
                    <StatCard
                        label={t('projects.dashboard.kpi.delayed')}
                        value={kpi.delayed}
                        href={listFor('Delayed')}
                        tone="danger"
                    />
                    <StatCard
                        label={t('projects.dashboard.kpi.on_hold')}
                        value={kpi.on_hold}
                        href={listFor('On Hold')}
                    />
                    <StatCard
                        label={t('projects.dashboard.kpi.not_started')}
                        value={kpi.not_started}
                        href={listFor('Not Started')}
                    />
                    <StatCard
                        label={t('projects.dashboard.kpi.avg_progress')}
                        value={`${kpi.avg_progress}%`}
                        progress={kpi.avg_progress}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label={t('projects.dashboard.cards.improvement')}
                        value={cards.improvement.count}
                        sub={t('projects.dashboard.cards.improvement_sub', {
                            new: cards.improvement.new,
                            avg: cards.improvement.avg_progress,
                        })}
                        progress={cards.improvement.avg_progress}
                        href={improvementsIndex().url}
                    />
                    <StatCard
                        label={t('projects.dashboard.cards.tooling')}
                        value={cards.tooling.count}
                        sub={t('projects.dashboard.cards.tooling_sub', {
                            active: cards.tooling.active,
                            avg: cards.tooling.avg_progress,
                        })}
                        progress={cards.tooling.avg_progress}
                        href={toolingsIndex().url}
                    />
                    {can.finance && cards.quotation && (
                        <StatCard
                            label={t('projects.dashboard.cards.quotation')}
                            value={cards.quotation.count}
                            sub={t('projects.dashboard.cards.quotation_sub', {
                                count: cards.quotation.waiting,
                            })}
                            href={quotationsIndex().url}
                        />
                    )}
                    {can.finance && cards.po && (
                        <StatCard
                            label={t('projects.dashboard.cards.po')}
                            value={cards.po.count}
                            sub={t('projects.dashboard.cards.po_sub', {
                                count: cards.po.waiting,
                            })}
                            href={purchaseOrdersIndex().url}
                        />
                    )}
                </div>

                <Card
                    className={
                        reminders.some((r) => r.days < 0)
                            ? 'border-red-300/60 dark:border-red-500/30'
                            : ''
                    }
                >
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <h3 className="font-semibold">
                            {reminderTitle}{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                ({reminders.length})
                            </span>
                        </h3>
                        <div
                            className="inline-flex rounded-lg bg-muted p-1"
                            role="group"
                        >
                            {(['day', 'week', 'month'] as const).map((p) => (
                                <button
                                    key={p}
                                    type="button"
                                    aria-pressed={reminderPeriod === p}
                                    onClick={() => go({ reminder: p })}
                                    className={`rounded-md px-3 py-1 text-sm font-medium ${reminderPeriod === p ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                                >
                                    {t(`projects.dashboard.reminders.${p}`)}
                                </button>
                            ))}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {reminders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('projects.dashboard.reminders.empty')}
                            </p>
                        ) : (
                            <ul className="max-h-96 divide-y overflow-y-auto">
                                {reminders.map((r, i) => {
                                    const href = r.tooling
                                        ? toolingShow(r.tooling.id).url
                                        : show(r.project!.id).url;
                                    const title =
                                        r.kind === 'project'
                                            ? `${t('projects.dashboard.reminders.project')} · ${r.project!.name}`
                                            : r.kind === 'tooling'
                                              ? t(
                                                    'projects.dashboard.reminders.tooling',
                                                    { name: r.label || '-' },
                                                )
                                              : `${t(`projects.dashboard.reminders.${r.kind}`, { name: r.label })} · ${r.project!.name}`;
                                    const tone =
                                        r.days < 0
                                            ? 'bg-red-600'
                                            : r.days <= 2
                                              ? 'bg-amber-500'
                                              : 'bg-sky-600';

                                    return (
                                        <li key={`${r.kind}-${i}`}>
                                            <Link
                                                href={href}
                                                className="flex items-start gap-3 py-2 hover:bg-muted/50"
                                            >
                                                <span
                                                    className={`mt-0.5 shrink-0 rounded px-2 py-0.5 text-xs font-medium text-white ${tone}`}
                                                >
                                                    {dueLabel(t, r.days)}
                                                </span>
                                                <span className="min-w-0 text-sm">
                                                    <b className="block truncate">
                                                        {title}
                                                        {r.tooling
                                                            ?.is_critical && (
                                                            <Badge
                                                                variant="destructive"
                                                                className="ml-2"
                                                            >
                                                                {t(
                                                                    'projects.dashboard.reminders.critical',
                                                                )}
                                                            </Badge>
                                                        )}
                                                    </b>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {r.tooling
                                                            ? `${r.tooling.npd_no || 'FR.PRNI.002.00'} · ${t('projects.dashboard.reminders.tooling_due', { date: r.date, status: r.tooling.status })}`
                                                            : `${r.project!.code} · ${r.date}${r.project!.pic_name ? ` · ${r.project!.pic_name}` : ''}`}
                                                    </span>
                                                </span>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('projects.dashboard.status_title')}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t('projects.dashboard.status_hint')}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <Donut
                                centerLabel={t('projects.dashboard.center')}
                                data={statusDonut.map((d) => ({
                                    ...d,
                                    color: STATUS_COLOR[d.label] ?? '#94a3b8',
                                    text: projectStatusLabel(t, d.label),
                                }))}
                                hrefFor={listFor}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('projects.dashboard.category_title')}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    ({categoryTotal})
                                </span>
                            </h3>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {categoryDonut.map((c) => (
                                    <li key={c.label}>
                                        <Link
                                            href={
                                                portfolioIndex({
                                                    query: {
                                                        category: c.label,
                                                    },
                                                }).url
                                            }
                                            className="block rounded px-1 py-0.5 hover:bg-muted"
                                        >
                                            <div className="flex justify-between text-sm">
                                                <span>{c.label}</span>
                                                <b className="tabular-nums">
                                                    {c.value}
                                                </b>
                                            </div>
                                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-brand"
                                                    style={{
                                                        width: `${(c.value / maxCategory) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-300/60 dark:border-amber-500/30">
                        <CardHeader>
                            <h3 className="flex items-center gap-2 font-semibold">
                                <AlertTriangle className="size-4 text-amber-600" />
                                {t('projects.dashboard.attention.title', {
                                    count: attention.length,
                                })}
                            </h3>
                        </CardHeader>
                        <CardContent>
                            {attention.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('projects.dashboard.attention.empty')}
                                </p>
                            ) : (
                                <ul className="max-h-96 divide-y overflow-y-auto">
                                    {attention.map((a, i) => (
                                        <li
                                            key={`${a.project.id}-${a.tag}-${i}`}
                                        >
                                            <Link
                                                href={show(a.project.id).url}
                                                className="flex items-start gap-3 py-2 hover:bg-muted/50"
                                            >
                                                <span
                                                    className={`mt-0.5 shrink-0 rounded px-2 py-0.5 text-xs font-medium text-white ${TAG_COLOR[a.tag]}`}
                                                >
                                                    {t(
                                                        `projects.dashboard.attention.tags.${a.tag}`,
                                                    )}
                                                </span>
                                                <span className="min-w-0 text-sm">
                                                    <b className="block truncate">
                                                        {a.project.name}
                                                    </b>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {attentionText(a)}
                                                    </span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">
                                {t('projects.dashboard.board.title')}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t('projects.dashboard.board.description')}
                            </p>
                        </div>
                        <b className="text-sm">
                            {t('projects.dashboard.board.count', {
                                count: board.length,
                            })}
                        </b>
                    </CardHeader>
                    <CardContent>
                        {board.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('projects.dashboard.board.empty')}
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {board.map((p) => (
                                    <li key={p.id}>
                                        <Link
                                            href={show(p.id).url}
                                            className="grid gap-3 py-3 hover:bg-muted/40 md:grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)_auto] md:items-center md:px-2"
                                        >
                                            <div className="min-w-0">
                                                <b className="block truncate text-sm">
                                                    {p.name}
                                                </b>
                                                <span className="text-xs text-muted-foreground">
                                                    {p.code} ·{' '}
                                                    {p.pic_name || '-'} ·{' '}
                                                    {p.category || '-'}
                                                </span>
                                            </div>
                                            <div className="min-w-0 space-y-1.5">
                                                <div className="relative">
                                                    <ProgressBar
                                                        value={p.progress}
                                                    />
                                                    {p.planned !== null &&
                                                        p.stages_total ===
                                                            0 && (
                                                            <span
                                                                className="absolute top-0 h-2 w-0.5 bg-foreground/60"
                                                                style={{
                                                                    left: `calc(${p.planned}% * 0.85)`,
                                                                }}
                                                                title={t(
                                                                    'projects.dashboard.board.plan',
                                                                    {
                                                                        percent:
                                                                            p.planned,
                                                                    },
                                                                )}
                                                            />
                                                        )}
                                                </div>
                                                {p.stage_strip.length > 0 && (
                                                    <div
                                                        className="flex gap-0.5"
                                                        aria-hidden
                                                    >
                                                        {p.stage_strip.map(
                                                            (s, i) => (
                                                                <span
                                                                    key={i}
                                                                    className="h-1.5 flex-1 rounded-sm"
                                                                    style={{
                                                                        background:
                                                                            s.status ===
                                                                            'Not Started'
                                                                                ? 'var(--border)'
                                                                                : STATUS_COLOR[
                                                                                      s
                                                                                          .status
                                                                                  ],
                                                                    }}
                                                                    title={`${s.name} · ${projectStatusLabel(t, s.status)}`}
                                                                />
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                                <p className="text-xs text-muted-foreground">
                                                    {p.start_date ?? '-'} →{' '}
                                                    {p.end_date ?? '-'}
                                                    {p.completed_on && (
                                                        <b className="ml-1 text-emerald-600 dark:text-emerald-400">
                                                            ·{' '}
                                                            {t(
                                                                'projects.dashboard.board.completed_on',
                                                                {
                                                                    date: p.completed_on,
                                                                },
                                                            )}
                                                        </b>
                                                    )}
                                                    {p.stages_total > 0 && (
                                                        <>
                                                            {' · '}
                                                            {p.current_stage
                                                                ? t(
                                                                      'projects.dashboard.board.stage_current',
                                                                      {
                                                                          name: p.current_stage,
                                                                          done: p.stages_done,
                                                                          total: p.stages_total,
                                                                      },
                                                                  )
                                                                : t(
                                                                      'projects.dashboard.board.all_done',
                                                                  )}
                                                        </>
                                                    )}
                                                    {p.late_stages > 0 &&
                                                        p.status !==
                                                            'Completed' && (
                                                            <b className="ml-1 text-red-600 dark:text-red-400">
                                                                ·{' '}
                                                                {t(
                                                                    'projects.dashboard.board.late_stages',
                                                                    {
                                                                        count: p.late_stages,
                                                                    },
                                                                )}
                                                            </b>
                                                        )}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2 md:justify-end">
                                                <Badge
                                                    variant="outline"
                                                    className={projectStatusClassName(
                                                        p.status,
                                                    )}
                                                >
                                                    {projectStatusLabel(
                                                        t,
                                                        p.status,
                                                    )}
                                                </Badge>
                                                {p.at_risk && (
                                                    <Badge className="bg-orange-500 text-white hover:bg-orange-500">
                                                        {t(
                                                            'projects.dashboard.board.at_risk',
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
