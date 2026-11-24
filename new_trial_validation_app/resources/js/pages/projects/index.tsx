import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { FilterBar, FilterSelect } from '@/components/filter-bar';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { ProgressBar } from '@/components/projects/progress-bar';
import { SectionTabs } from '@/components/projects/section-tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import {
    projectPriorityLabel,
    projectStatusClassName,
    projectStatusLabel,
} from '@/lib/project';
import { create, index as projectsIndex, show } from '@/routes/projects';
import type { Paginated } from '@/types';

type ProjectSummary = {
    id: number;
    code: string;
    name: string;
    category: string;
    status: string;
    priority: string;
    pic_name: string;
    start_date: string | null;
    end_date: string | null;
    progress: number;
    is_overdue: boolean;
};

type AttentionItem = ProjectSummary & {
    reasons: string[];
    late_stages: number;
};

type Filters = {
    q: string;
    status: string;
    category: string;
    priority: string;
    pic: string;
};

type PageProps = {
    projects: Paginated<ProjectSummary>;
    filters: Filters;
    kpi: {
        total: number;
        active: number;
        completed: number;
        delayed: number;
        avg_progress: number;
    };
    activeProjects: ProjectSummary[];
    attention: AttentionItem[];
    options: {
        statuses: string[];
        priorities: string[];
        categories: string[];
        pics: string[];
    };
    can: { create: boolean };
};

function KpiCard({
    label,
    value,
    tone,
}: {
    readonly label: string;
    readonly value: string | number;
    readonly tone?: 'danger';
}) {
    return (
        <Card>
            <CardContent className="space-y-1">
                <p className="text-sm text-muted-foreground">{label}</p>
                <p
                    className={`text-2xl font-semibold tabular-nums ${tone === 'danger' && Number(value) > 0 ? 'text-red-600 dark:text-red-400' : ''}`}
                >
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

export default function ProjectsIndex({
    projects,
    filters,
    kpi,
    activeProjects,
    attention,
    options,
    can,
}: PageProps) {
    const { t, formatDateOnly } = useTranslation();
    const [form, setForm] = useState<Filters>(filters);
    const url = projectsIndex().url;

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get(url, form, { preserveState: true, replace: true });
    }

    function reset() {
        setForm({ q: '', status: '', category: '', priority: '', pic: '' });
        router.get(url);
    }

    function setField(patch: Partial<Filters>) {
        const next = { ...form, ...patch };
        setForm(next);

        // Dropdowns apply immediately; the search box waits for Enter/submit.
        if (!('q' in patch)) {
            router.get(url, next, { preserveState: true, replace: true });
        }
    }

    const chips = (
        [
            ['status', t('projects.fields.status')],
            ['category', t('projects.fields.category')],
            ['priority', t('projects.fields.priority')],
            ['pic', t('projects.fields.pic')],
        ] as const
    )
        .filter(([key]) => filters[key] !== '')
        .map(([key, label]) => ({
            key,
            label: `${label}: ${
                key === 'status'
                    ? projectStatusLabel(t, filters[key])
                    : key === 'priority'
                      ? projectPriorityLabel(t, filters[key])
                      : filters[key]
            }`,
            onClear: () =>
                router.get(
                    url,
                    { ...filters, [key]: '' },
                    { preserveState: true, replace: true },
                ),
        }));

    return (
        <>
            <Head title={t('projects.head_title')} />

            <div className="space-y-6 p-4">
                <SectionTabs section="portfolio" active="list" />
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        className="space-y-0.5"
                        title={t('projects.title')}
                        description={t('projects.description')}
                    />
                    {can.create && (
                        <Button asChild>
                            <Link href={create().url}>
                                <Plus />
                                {t('projects.new')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <KpiCard
                        label={t('projects.kpi.total')}
                        value={kpi.total}
                    />
                    <KpiCard
                        label={t('projects.kpi.active')}
                        value={kpi.active}
                    />
                    <KpiCard
                        label={t('projects.kpi.completed')}
                        value={kpi.completed}
                    />
                    <KpiCard
                        label={t('projects.kpi.delayed')}
                        value={kpi.delayed}
                        tone="danger"
                    />
                    <KpiCard
                        label={t('projects.kpi.avg_progress')}
                        value={`${kpi.avg_progress}%`}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-amber-300/60 dark:border-amber-500/30">
                        <CardHeader>
                            <h3 className="flex items-center gap-2 font-semibold">
                                <AlertTriangle className="size-4 text-amber-600" />
                                {t('projects.attention.title')}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t('projects.attention.description')}
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {attention.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t('projects.attention.empty')}
                                </p>
                            )}
                            {attention.map((p) => (
                                <div
                                    key={p.id}
                                    className="flex items-start justify-between gap-3 border-b pb-3 last:border-0 last:pb-0"
                                >
                                    <div className="min-w-0 space-y-1">
                                        <Link
                                            href={show(p.id).url}
                                            className="font-medium underline-offset-2 hover:underline"
                                        >
                                            {p.name}
                                        </Link>
                                        <div className="flex flex-wrap gap-1">
                                            {p.reasons.map((reason) => (
                                                <Badge
                                                    key={reason}
                                                    variant="outline"
                                                    className="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
                                                >
                                                    {t(
                                                        `projects.attention.reasons.${reason}`,
                                                        {
                                                            count: p.late_stages,
                                                        },
                                                    )}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="shrink-0 text-right text-xs text-muted-foreground">
                                        <div>{p.pic_name || '-'}</div>
                                        <div>{formatDateOnly(p.end_date)}</div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <h3 className="font-semibold">
                                {t('projects.active.title')}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {t('projects.active.description')}
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {activeProjects.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t('projects.active.empty')}
                                </p>
                            )}
                            {activeProjects.map((p) => (
                                <div key={p.id} className="space-y-1">
                                    <div className="flex items-center justify-between gap-3">
                                        <Link
                                            href={show(p.id).url}
                                            className="truncate font-medium underline-offset-2 hover:underline"
                                        >
                                            {p.name}
                                        </Link>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {p.pic_name || '-'}
                                        </span>
                                    </div>
                                    <ProgressBar value={p.progress} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <FilterBar
                    searchValue={form.q}
                    onSearchChange={(value) => setField({ q: value })}
                    searchPlaceholder={t('projects.filter.search_placeholder')}
                    onSubmit={submit}
                    onReset={reset}
                    hasActiveFilters={Object.values(filters).some(Boolean)}
                    activeChips={chips}
                >
                    <FilterSelect
                        label={t('projects.fields.status')}
                        value={form.status}
                        onChange={(status) => setField({ status })}
                        options={options.statuses.map((value) => ({
                            value,
                            label: projectStatusLabel(t, value),
                        }))}
                    />
                    <FilterSelect
                        label={t('projects.fields.category')}
                        value={form.category}
                        onChange={(category) => setField({ category })}
                        options={options.categories}
                    />
                    <FilterSelect
                        label={t('projects.fields.priority')}
                        value={form.priority}
                        onChange={(priority) => setField({ priority })}
                        options={options.priorities.map((value) => ({
                            value,
                            label: projectPriorityLabel(t, value),
                        }))}
                    />
                    <FilterSelect
                        label={t('projects.fields.pic')}
                        value={form.pic}
                        onChange={(pic) => setField({ pic })}
                        options={options.pics}
                    />
                </FilterBar>

                <Card>
                    <CardContent className="space-y-4">
                        <h3 className="font-semibold">
                            {t('projects.table.title')}
                        </h3>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('projects.table.project')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.category')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.pic')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.priority')}
                                    </TableHead>
                                    <TableHead>
                                        {t('projects.fields.end_date')}
                                    </TableHead>
                                    <TableHead className="w-44">
                                        {t('projects.fields.progress')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {projects.data.map((p) => (
                                    <TableRow key={p.id}>
                                        <TableCell>
                                            <Link
                                                href={show(p.id).url}
                                                className="font-medium underline-offset-2 hover:underline"
                                            >
                                                {p.name}
                                            </Link>
                                            {p.code && (
                                                <div className="text-xs text-muted-foreground">
                                                    {p.code}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>{p.category}</TableCell>
                                        <TableCell>
                                            {p.pic_name || '-'}
                                        </TableCell>
                                        <TableCell>
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
                                        </TableCell>
                                        <TableCell>
                                            {projectPriorityLabel(
                                                t,
                                                p.priority,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateOnly(p.end_date)}
                                            {p.is_overdue && (
                                                <div className="text-xs text-red-600 dark:text-red-400">
                                                    {t(
                                                        'projects.table.overdue',
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <ProgressBar value={p.progress} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {projects.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('projects.table.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={url}
                            query={filters}
                            currentPage={projects.current_page}
                            lastPage={projects.last_page}
                            total={projects.total}
                            itemLabel={t('projects.table.item_label')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
