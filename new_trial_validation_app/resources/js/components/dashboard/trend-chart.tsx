import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { useTranslation } from '@/hooks/use-translation';

export type TrendDatum = {
    period: string; // "YYYY-MM"
    count: number;
};

type TrendChartProps = {
    data: TrendDatum[];
};

function monthLabel(period: string, intlLocale: string): string {
    const [year, month] = period.split('-').map(Number);

    return new Intl.DateTimeFormat(intlLocale, {
        month: 'short',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));
}

export function TrendChart({ data }: TrendChartProps) {
    const { t, intlLocale } = useTranslation();
    const chartConfig = {
        count: {
            label: t('dashboard.charts.trend_series'),
            color: 'var(--brand)',
        },
    } satisfies ChartConfig;
    const formatted = data.map((row) => ({
        ...row,
        label: monthLabel(row.period, intlLocale),
    }));

    return (
        <ChartContainer config={chartConfig} className="h-64 w-full">
            <AreaChart data={formatted} margin={{ left: -16, right: 12 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                />
                <YAxis
                    allowDecimals={false}
                    tickLine={false}
                    axisLine={false}
                    width={28}
                />
                <ChartTooltip
                    cursor={false}
                    content={<ChartTooltipContent indicator="line" />}
                />
                <Area
                    dataKey="count"
                    type="monotone"
                    fill="var(--color-count)"
                    fillOpacity={0.15}
                    stroke="var(--color-count)"
                    strokeWidth={2}
                />
            </AreaChart>
        </ChartContainer>
    );
}
