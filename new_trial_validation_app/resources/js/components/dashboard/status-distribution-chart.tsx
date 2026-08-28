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

const chartConfig = {
    count: { label: 'Jumlah Trial' },
} satisfies ChartConfig;

export function StatusDistributionChart({
    data,
}: StatusDistributionChartProps) {
    return (
        <ChartContainer config={chartConfig} className="h-64 w-full">
            <BarChart
                data={data}
                layout="vertical"
                margin={{ left: 8, right: 24 }}
            >
                <CartesianGrid horizontal={false} />
                <XAxis type="number" hide allowDecimals={false} />
                <YAxis
                    type="category"
                    dataKey="status"
                    tickLine={false}
                    axisLine={false}
                    width={120}
                />
                <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent hideLabel />}
                />
                <Bar dataKey="count" radius={4} barSize={18}>
                    {data.map((row) => (
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
