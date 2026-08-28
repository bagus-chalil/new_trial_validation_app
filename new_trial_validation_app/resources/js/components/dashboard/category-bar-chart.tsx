import {
    Bar,
    BarChart,
    CartesianGrid,
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

export type CategoryBarDatum = {
    label: string;
    count: number;
};

type CategoryBarChartProps = {
    data: CategoryBarDatum[];
    emptyMessage: string;
};

const chartConfig = {
    count: {
        label: 'Jumlah Trial',
        color: 'var(--brand)',
    },
} satisfies ChartConfig;

// Single-hue horizontal bars: the axis labels already carry each category's
// identity, so a rainbow of categorical colors here would be decorative, not
// informative — see the dataviz skill's "magnitude comparison" guidance.
export function CategoryBarChart({
    data,
    emptyMessage,
}: CategoryBarChartProps) {
    const nonZero = data.filter((row) => row.count > 0);

    if (nonZero.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </p>
        );
    }

    return (
        <ChartContainer config={chartConfig} className="h-64 w-full">
            <BarChart
                data={nonZero}
                layout="vertical"
                margin={{ left: 8, right: 24 }}
            >
                <CartesianGrid horizontal={false} />
                <XAxis type="number" hide />
                <YAxis
                    type="category"
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    width={110}
                />
                <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent hideLabel />}
                />
                <Bar
                    dataKey="count"
                    fill="var(--color-count)"
                    radius={4}
                    barSize={18}
                >
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
