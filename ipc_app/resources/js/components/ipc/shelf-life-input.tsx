import { Input } from '@/components/ui/input';
import { useEffect, useRef, useState } from 'react';

type Unit = 'bulan' | 'tahun';

// The value is ALWAYS emitted as a whole number of months (string, '' when empty): that is what the
// server stores. "Tahun" is only an input convenience (2 tahun = 24 bulan, 2,5 tahun = 30 bulan).
function toDisplay(months: string, unit: Unit): string {
    const value = Number(months);
    if (!months || !Number.isFinite(value) || value <= 0) return '';
    if (unit === 'bulan') return String(Math.round(value));
    return String(Math.round((value / 12) * 100) / 100);
}

function toMonths(text: string, unit: Unit): string {
    const value = parseFloat(text.replace(',', '.'));
    if (!Number.isFinite(value) || value <= 0) return '';
    return String(Math.round(unit === 'tahun' ? value * 12 : value));
}

export function ShelfLifeInput({
    id,
    months,
    onChange,
    disabled,
    className,
}: {
    id: string;
    months: string;
    onChange: (months: string) => void;
    disabled?: boolean;
    className?: string;
}) {
    // Start in years when the stored value is a whole number of years, otherwise in months.
    const [unit, setUnit] = useState<Unit>(() => (Number(months) >= 12 && Number(months) % 12 === 0 ? 'tahun' : 'bulan'));
    const [text, setText] = useState(() => toDisplay(months, unit));
    const lastEmitted = useRef(months);

    // The value changed from outside (e.g. a different product was picked): show it in the current unit.
    useEffect(() => {
        if (months !== lastEmitted.current) {
            lastEmitted.current = months;
            setText(toDisplay(months, unit));
        }
    }, [months, unit]);

    const handleText = (next: string) => {
        setText(next);
        const emitted = toMonths(next, unit);
        lastEmitted.current = emitted;
        onChange(emitted);
    };

    const handleUnit = (next: Unit) => {
        setUnit(next);
        setText(toDisplay(months, next));
    };

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex gap-2">
                <Input
                    id={id}
                    type="number"
                    inputMode="decimal"
                    min={0}
                    step="any"
                    className={className}
                    value={text}
                    onChange={(e) => handleText(e.target.value)}
                    disabled={disabled}
                />
                <div
                    className="border-border bg-background flex shrink-0 overflow-hidden rounded-xl border-[1.5px]"
                    role="group"
                    aria-label="Satuan shelf life"
                >
                    {(['bulan', 'tahun'] as Unit[]).map((option) => (
                        <button
                            key={option}
                            type="button"
                            disabled={disabled}
                            aria-pressed={unit === option}
                            onClick={() => handleUnit(option)}
                            className={`min-h-11 px-3.5 text-[13px] font-bold capitalize disabled:cursor-not-allowed disabled:opacity-60 ${
                                unit === option ? 'bg-primary text-white' : 'text-muted-foreground'
                            }`}
                        >
                            {option}
                        </button>
                    ))}
                </div>
            </div>
            {unit === 'tahun' && months && <p className="text-muted-foreground text-[11.5px]">= {months} bulan</p>}
        </div>
    );
}
