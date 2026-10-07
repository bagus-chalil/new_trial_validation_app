import { CalendarRange } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

/** Inclusive Y-m-d range; both empty means "all time". */
export type DashboardPeriod = { from: string; to: string };

type Mode =
    | 'all'
    | 'this_month'
    | 'last_month'
    | 'last_3_months'
    | 'last_6_months'
    | 'this_year'
    | 'month'
    | 'custom';

const PRESETS: Exclude<Mode, 'month' | 'custom'>[] = [
    'all',
    'this_month',
    'last_month',
    'last_3_months',
    'last_6_months',
    'this_year',
];

/** Local-time Y-m-d (toISOString() would shift the day across UTC). */
function ymd(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function monthRange(year: number, monthIndex: number): DashboardPeriod {
    return {
        from: ymd(new Date(year, monthIndex, 1)),
        to: ymd(new Date(year, monthIndex + 1, 0)),
    };
}

function presetRange(mode: (typeof PRESETS)[number]): DashboardPeriod {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();

    switch (mode) {
        case 'this_month':
            return monthRange(y, m);
        case 'last_month':
            return monthRange(y, m - 1);
        case 'last_3_months':
            return {
                from: ymd(new Date(y, m - 2, 1)),
                to: ymd(new Date(y, m + 1, 0)),
            };
        case 'last_6_months':
            return {
                from: ymd(new Date(y, m - 5, 1)),
                to: ymd(new Date(y, m + 1, 0)),
            };
        case 'this_year':
            return { from: `${y}-01-01`, to: `${y}-12-31` };
        default:
            return { from: '', to: '' };
    }
}

/** Which control a given (server-echoed) period corresponds to. */
function detectMode(period: DashboardPeriod): Mode {
    if (!period.from || !period.to) {
        return 'all';
    }

    for (const preset of PRESETS) {
        const range = presetRange(preset);

        if (range.from === period.from && range.to === period.to) {
            return preset;
        }
    }

    const [y, m] = period.from.split('-').map(Number);
    const whole = monthRange(y, m - 1);

    if (whole.from === period.from && whole.to === period.to) {
        return 'month';
    }

    return 'custom';
}

export function DashboardPeriodFilter({
    period,
    onChange,
}: {
    period: DashboardPeriod;
    onChange: (period: DashboardPeriod) => void;
}) {
    const { t, intlLocale } = useTranslation();
    const formatDay = (value: string) => {
        const [y, m, d] = value.split('-').map(Number);

        return new Intl.DateTimeFormat(intlLocale, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(new Date(y, m - 1, d));
    };
    const [mode, setMode] = useState<Mode>(() => detectMode(period));
    const [month, setMonth] = useState(
        period.from ? period.from.slice(0, 7) : ymd(new Date()).slice(0, 7),
    );
    const [custom, setCustom] = useState<DashboardPeriod>(
        period.from ? period : presetRange('this_month'),
    );

    function selectMode(next: Mode) {
        setMode(next);

        if (next === 'month') {
            const [y, m] = month.split('-').map(Number);
            onChange(monthRange(y, m - 1));
        } else if (next !== 'custom') {
            onChange(presetRange(next));
        }
    }

    function selectMonth(value: string) {
        setMonth(value);

        if (/^\d{4}-\d{2}$/.test(value)) {
            const [y, m] = value.split('-').map(Number);
            onChange(monthRange(y, m - 1));
        }
    }

    const active = Boolean(period.from && period.to);

    return (
        <div className="flex flex-col items-start gap-1.5 sm:items-end">
            <div className="flex flex-wrap items-center gap-2">
                <CalendarRange className="size-4 text-muted-foreground" />
                <Select
                    value={mode}
                    onValueChange={(value) => selectMode(value as Mode)}
                >
                    <SelectTrigger className="h-9 w-48">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {[...PRESETS, 'month' as const, 'custom' as const].map(
                            (option) => (
                                <SelectItem key={option} value={option}>
                                    {t(`dashboard.period.options.${option}`)}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>

                {mode === 'month' && (
                    <Input
                        type="month"
                        className="h-9 w-44"
                        value={month}
                        onChange={(e) => selectMonth(e.target.value)}
                        aria-label={t('dashboard.period.options.month')}
                    />
                )}

                {mode === 'custom' && (
                    <form
                        className="flex flex-wrap items-center gap-2"
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (custom.from && custom.to) {
                                onChange(custom);
                            }
                        }}
                    >
                        <Input
                            type="date"
                            className="h-9 w-40"
                            value={custom.from}
                            onChange={(e) =>
                                setCustom({ ...custom, from: e.target.value })
                            }
                            aria-label={t('dashboard.period.from')}
                        />
                        <span className="text-sm text-muted-foreground">–</span>
                        <Input
                            type="date"
                            className="h-9 w-40"
                            value={custom.to}
                            onChange={(e) =>
                                setCustom({ ...custom, to: e.target.value })
                            }
                            aria-label={t('dashboard.period.to')}
                        />
                        <Button
                            type="submit"
                            size="sm"
                            disabled={!custom.from || !custom.to}
                        >
                            {t('dashboard.period.apply')}
                        </Button>
                    </form>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                {active
                    ? t('dashboard.period.showing_range', {
                          from: formatDay(period.from),
                          to: formatDay(period.to),
                      })
                    : t('dashboard.period.showing_all')}
            </p>
        </div>
    );
}
