import { FlaskConical, PackageCheck } from 'lucide-react';
import { useState } from 'react';
import { CategoryBarChart } from '@/components/dashboard/category-bar-chart';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type StatusBuckets = {
    total: number;
    in_review: number;
    awaiting_approval: number;
    done: number;
    revision_required: number;
    rejected: number;
};

export type ValidationOverviewData = {
    risk: { high: number; medium: number; low: number } | null;
    npd: StatusBuckets | null;
    improvement: StatusBuckets | null;
    npdProductTypes: { label: string; count: number }[];
    lineConfig:
        | (Omit<StatusBuckets, 'total' | 'rejected'> & {
              qty: number;
              draft: number;
              bottleneck: { department: string; count: number } | null;
          })
        | null;
    mixing: StatusBuckets & { machines: { label: string; count: number }[] };
};

type Tab = 'filling' | 'mixing';

const BUCKETS = [
    { key: 'in_review', tone: 'bg-blue-500' },
    { key: 'awaiting_approval', tone: 'bg-orange-500' },
    { key: 'done', tone: 'bg-emerald-500' },
    { key: 'revision_required', tone: 'bg-amber-500' },
    { key: 'rejected', tone: 'bg-red-500' },
] as const;

function StatusCard({
    title,
    caption,
    data,
    skip = [],
}: {
    title: string;
    caption?: string;
    data: Partial<StatusBuckets>;
    skip?: string[];
}) {
    const { t } = useTranslation();
    const buckets = BUCKETS.filter((b) => !skip.includes(b.key));

    return (
        <Card className="h-full">
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{title}</CardTitle>
                {caption && (
                    <p className="text-xs text-muted-foreground">{caption}</p>
                )}
            </CardHeader>
            <CardContent className="space-y-3">
                {data.total !== undefined && (
                    <p className="text-3xl font-semibold tabular-nums">
                        {data.total}
                    </p>
                )}
                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {buckets.map((b) => (
                        <li
                            key={b.key}
                            className="rounded-lg border bg-muted/30 px-3 py-2"
                        >
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        b.tone,
                                    )}
                                />
                                {t(`dashboard.overview.${b.key}`)}
                            </div>
                            <div className="text-lg font-semibold tabular-nums">
                                {data[b.key] ?? 0}
                            </div>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function RiskCard({
    risk,
}: {
    risk: NonNullable<ValidationOverviewData['risk']>;
}) {
    const { t } = useTranslation();
    const total = risk.high + risk.medium + risk.low;
    const rows = [
        { key: 'high', value: risk.high, tone: 'bg-red-500' },
        { key: 'medium', value: risk.medium, tone: 'bg-amber-500' },
        { key: 'low', value: risk.low, tone: 'bg-emerald-500' },
    ] as const;

    return (
        <Card className="h-full">
            <CardHeader className="pb-2">
                <CardTitle className="text-base">
                    {t('dashboard.overview.risk_title')}
                </CardTitle>
                <p className="text-xs text-muted-foreground">
                    {t('dashboard.overview.risk_caption')}
                </p>
            </CardHeader>
            <CardContent className="space-y-3">
                {rows.map((r) => (
                    <div key={r.key} className="space-y-1">
                        <div className="flex justify-between text-sm">
                            <span>{t(`dashboard.overview.risk_${r.key}`)}</span>
                            <span className="font-semibold tabular-nums">
                                {r.value}
                            </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn('h-full rounded-full', r.tone)}
                                style={{
                                    width: `${total ? (r.value / total) * 100 : 0}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

export function ValidationOverview({ data }: { data: ValidationOverviewData }) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>('filling');

    const tabs: { key: Tab; icon: typeof PackageCheck }[] = [
        { key: 'filling', icon: PackageCheck },
        { key: 'mixing', icon: FlaskConical },
    ];

    return (
        <section className="space-y-4">
            <div
                role="tablist"
                className="inline-flex rounded-xl border bg-muted/40 p-1"
            >
                {tabs.map(({ key, icon: Icon }) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={cn(
                            'flex items-center gap-2 rounded-lg px-4 py-1.5 text-sm font-medium transition-colors',
                            tab === key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" />
                        {t(`dashboard.overview.tab_${key}`)}
                    </button>
                ))}
            </div>

            {tab === 'filling' ? (
                <div className="grid gap-4 lg:grid-cols-3">
                    {data.risk && <RiskCard risk={data.risk} />}
                    {data.npd && (
                        <StatusCard
                            title={t('dashboard.overview.npd_title')}
                            caption={t('dashboard.overview.trials_caption')}
                            data={data.npd}
                        />
                    )}
                    {data.improvement && (
                        <StatusCard
                            title={t('dashboard.overview.imp_title')}
                            caption={t('dashboard.overview.trials_caption')}
                            data={data.improvement}
                        />
                    )}

                    <Card className="h-full lg:col-span-2">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">
                                {t('dashboard.overview.npd_types_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CategoryBarChart
                                data={data.npdProductTypes}
                                emptyMessage={t('dashboard.overview.empty')}
                            />
                        </CardContent>
                    </Card>

                    <Card className="h-full">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">
                                {t('dashboard.overview.sales_title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                {t('dashboard.overview.sales_pending')}
                            </p>
                        </CardContent>
                    </Card>

                    {data.lineConfig && (
                        <div className="lg:col-span-3">
                            <StatusCard
                                title={t('dashboard.overview.line_title')}
                                caption={`${t('dashboard.overview.line_caption')} · ${t('dashboard.overview.line_qty')}: ${data.lineConfig.qty} · ${t('dashboard.overview.line_draft')}: ${data.lineConfig.draft} · ${t('dashboard.overview.line_bottleneck')}: ${
                                    data.lineConfig.bottleneck
                                        ? `${data.lineConfig.bottleneck.department} (${data.lineConfig.bottleneck.count})`
                                        : t('dashboard.overview.line_none')
                                }`}
                                data={data.lineConfig}
                                skip={['rejected']}
                            />
                        </div>
                    )}
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    <StatusCard
                        title={t('dashboard.overview.mixing_title')}
                        caption={t('dashboard.overview.mixing_note')}
                        data={data.mixing}
                    />
                    <Card className="h-full">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">
                                {t('dashboard.overview.mixing_machines')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CategoryBarChart
                                data={data.mixing.machines}
                                emptyMessage={t('dashboard.overview.empty')}
                            />
                        </CardContent>
                    </Card>
                </div>
            )}
        </section>
    );
}
