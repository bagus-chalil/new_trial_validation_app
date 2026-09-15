import { Cell, Pie, PieChart as RechartsPieChart } from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';

export type ProductTypePieDatum = {
    label: string;
    count: number;
};

type ProductTypePieChartProps = {
    data: ProductTypePieDatum[];
    emptyMessage: string;
};

// Fixed order, capped at 3 real categories + an "Lainnya" catch-all — the
// backend (Trial::productTypeBreakdown($user, 3)) already folds anything
// past the top 3 into that bucket. A pie's wedges can end up adjacent in any
// combination depending on the data (unlike a bar chart's fixed axis order),
// so this only uses the reference palette's first 3 categorical slots — the
// only prefix that clears validate_palette.js's --pairs all CVD/contrast
// checks (see resources/css/app.css). "Lainnya" reuses --muted-foreground,
// the same neutral treatment the status chart gives its own "off" state.
const SLOT_COLOR_VARS = [
    'var(--chart-categorical-1)',
    'var(--chart-categorical-2)',
    'var(--chart-categorical-3)',
];
const OTHER_COLOR_VAR = 'var(--muted-foreground)';

function colorFor(label: string, index: number): string {
    if (label === 'Lainnya') {
        return OTHER_COLOR_VAR;
    }

    return SLOT_COLOR_VARS[index] ?? OTHER_COLOR_VAR;
}

export function ProductTypePieChart({
    data,
    emptyMessage,
}: ProductTypePieChartProps) {
    const nonZero = data.filter((row) => row.count > 0);
    const total = nonZero.reduce((sum, row) => sum + row.count, 0);

    // ChartLegendContent looks up each entry's label in `config` by its
    // category name (see getPayloadConfigFromPayload in ui/chart.tsx) — for
    // a fixed-series chart that config is written by hand, but here the
    // categories are whatever product types are in the data, so the config
    // has to be built to match at render time instead.
    const chartConfig: ChartConfig = {};
    nonZero.forEach((row, index) => {
        chartConfig[row.label] = {
            label: row.label,
            color: colorFor(row.label, index),
        };
    });

    if (nonZero.length === 0 || total === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </p>
        );
    }

    return (
        <ChartContainer config={chartConfig} className="h-64 w-full">
            <RechartsPieChart>
                <ChartTooltip
                    content={<ChartTooltipContent hideLabel nameKey="label" />}
                />
                <Pie
                    data={nonZero}
                    dataKey="count"
                    nameKey="label"
                    innerRadius={55}
                    outerRadius={90}
                    paddingAngle={2}
                    stroke="var(--card)"
                    strokeWidth={2}
                    label={({ percent }) =>
                        percent >= 0.06 ? `${Math.round(percent * 100)}%` : ''
                    }
                    labelLine={false}
                >
                    {nonZero.map((row, index) => (
                        <Cell
                            key={row.label}
                            fill={colorFor(row.label, index)}
                        />
                    ))}
                </Pie>
                <ChartLegend content={<ChartLegendContent nameKey="label" />} />
            </RechartsPieChart>
        </ChartContainer>
    );
}
