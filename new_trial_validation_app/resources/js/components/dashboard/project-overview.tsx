import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { ATTENTION_TAG_COLOR, attentionText } from '@/lib/project-dashboard';
import type { AttentionItem, ProjectKpi } from '@/lib/project-dashboard';
import { dashboard as projectDashboard, index, show } from '@/routes/projects';
import { index as improvementsIndex } from '@/routes/projects/improvements';
import { index as toolingsIndex } from '@/routes/projects/toolings';

export type ProjectOverviewData = {
    kpi: ProjectKpi;
    cards: {
        improvement: { count: number; new: number; avg_progress: number };
        tooling: { count: number; active: number; avg_progress: number };
    };
    attention: AttentionItem[];
};

function OverviewCard({
    label,
    value,
    sub,
    href,
    progress,
    tone,
}: {
    readonly label: string;
    readonly value: number;
    readonly sub: string;
    readonly href: string;
    readonly progress: number;
    readonly tone?: 'danger';
}) {
    return (
        <Link
            href={href}
            className="block rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <Card className="h-full transition-colors hover:bg-muted/40">
                <CardContent className="space-y-1">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p
                        className={`text-2xl font-semibold tabular-nums ${tone === 'danger' ? 'text-red-600 dark:text-red-400' : ''}`}
                    >
                        {value}
                    </p>
                    <p className="text-xs text-muted-foreground">{sub}</p>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-brand"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

/**
 * Overview of the PIE module (Project, Improvement, Check Tooling) for the
 * main Dashboard, like the home screen of the old Project Tracking app.
 */
export function ProjectOverview({
    data,
}: {
    readonly data: ProjectOverviewData;
}) {
    const { t } = useTranslation();
    const { kpi, cards, attention } = data;

    return (
        <section className="space-y-3" aria-labelledby="project-overview">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="project-overview" className="text-lg font-semibold">
                        {t('projects.dashboard.overview.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('projects.dashboard.overview.description')}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={projectDashboard().url}>
                        {t('projects.dashboard.overview.open')}
                        <ArrowRight />
                    </Link>
                </Button>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <OverviewCard
                    label={t('projects.dashboard.overview.projects')}
                    value={kpi.total}
                    sub={t('projects.dashboard.overview.projects_sub', {
                        active: kpi.on_progress,
                        delayed: kpi.delayed,
                        done: kpi.completed,
                    })}
                    progress={kpi.avg_progress}
                    href={index().url}
                    tone={kpi.delayed > 0 ? 'danger' : undefined}
                />
                <OverviewCard
                    label={t('projects.dashboard.cards.improvement')}
                    value={cards.improvement.count}
                    sub={t('projects.dashboard.cards.improvement_sub', {
                        new: cards.improvement.new,
                        avg: cards.improvement.avg_progress,
                    })}
                    progress={cards.improvement.avg_progress}
                    href={improvementsIndex().url}
                />
                <OverviewCard
                    label={t('projects.dashboard.cards.tooling')}
                    value={cards.tooling.count}
                    sub={t('projects.dashboard.cards.tooling_sub', {
                        active: cards.tooling.active,
                        avg: cards.tooling.avg_progress,
                    })}
                    progress={cards.tooling.avg_progress}
                    href={toolingsIndex().url}
                />
                <Card className="border-amber-300/60 dark:border-amber-500/30">
                    <CardHeader className="pb-2">
                        <h3 className="flex items-center gap-2 text-sm font-semibold">
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
                            <ul className="space-y-2">
                                {attention.map((a, i) => (
                                    <li key={`${a.project.id}-${a.tag}-${i}`}>
                                        <Link
                                            href={show(a.project.id).url}
                                            className="flex items-start gap-2 text-sm hover:underline"
                                        >
                                            <span
                                                className={`mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium text-white ${ATTENTION_TAG_COLOR[a.tag]}`}
                                            >
                                                {t(
                                                    `projects.dashboard.attention.tags.${a.tag}`,
                                                )}
                                            </span>
                                            <span className="min-w-0">
                                                <b className="block truncate">
                                                    {a.project.name}
                                                </b>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {attentionText(t, a)}
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
        </section>
    );
}
