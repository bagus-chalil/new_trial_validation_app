import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    XAxis,
    YAxis,
} from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useTranslation } from '@/hooks/use-translation';
import { trialStatusLabel } from '@/lib/trial-status';

export type StatusDatum = {
    status: string;
    count: number;
};

type StatusDistributionChartProps = {
    data: StatusDatum[];
};

// Fixed order + fixed color per status (validated categorical set, see
// resources/css/app.css) — never re-derived from the data, so a status with
// zero trials still keeps its place and color rather than reshuffling.
const STATUS_COLOR_VAR: Record<string, string> = {
    Draft: 'var(--chart-status-draft)',
    'In Review': 'var(--chart-status-in-review)',
    'Ready for Approval': 'var(--chart-status-ready)',
    'Need Revision': 'var(--chart-status-need-revision)',
    Approved: 'var(--chart-status-approved)',
    Rejected: 'var(--chart-status-rejected)',
};

export function StatusDistributionChart({
    data,
}: StatusDistributionChartProps) {
    const { t } = useTranslation();
    const chartConfig = {
        count: { label: t('dashboard.charts.count_label') },
    } satisfies ChartConfig;
    // `status` stays the stored value (color lookup); `label` is display-only.
    const rows = data.map((row) => ({
        ...row,
        label: trialStatusLabel(t, row.status),
    }));

    return (
        <ChartContainer
            config={chartConfig}
            className="w-full"
            style={{ height: Math.max(224, data.length * 40) }}
        >
            <BarChart
                data={rows}
                layout="vertical"
                margin={{ left: 8, right: 24 }}
                barCategoryGap="22%"
            >
                <CartesianGrid horizontal={false} />
                <XAxis type="number" hide allowDecimals={false} />
                <YAxis
                    type="category"
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    width={120}
                />
                <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent hideLabel />}
                />
                <Bar dataKey="count" radius={4} barSize={26}>
                    {rows.map((row) => (
                        <Cell
                            key={row.status}
                            fill={
                                STATUS_COLOR_VAR[row.status] ??
                                'var(--muted-foreground)'
                            }
                        />
                    ))}
                    <LabelList
                        dataKey="count"
                        position="right"
                        className="fill-foreground"
                        fontSize={12}
                    />
                </Bar>
            </BarChart>
        </ChartContainer>
    );
}
