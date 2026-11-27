import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type BaseProps = {
    name: string;
    label: string;
    error?: string;
    hint?: string;
    className?: string;
};

export function TextField({
    name,
    label,
    error,
    hint,
    className,
    defaultValue,
    type = 'text',
    required,
    disabled,
}: BaseProps & {
    defaultValue?: string | number | null;
    type?: string;
    required?: boolean;
    disabled?: boolean;
}) {
    return (
        <div className={cn('grid min-w-0 gap-2', className)}>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type={type}
                step={type === 'number' ? 'any' : undefined}
                required={required}
                disabled={disabled}
                defaultValue={defaultValue ?? ''}
            />
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export function AreaField({
    name,
    label,
    error,
    className,
    defaultValue,
}: BaseProps & { defaultValue?: string | null }) {
    return (
        <div className={cn('grid min-w-0 gap-2', className)}>
            <Label htmlFor={name}>{label}</Label>
            <Textarea
                id={name}
                name={name}
                rows={3}
                defaultValue={defaultValue ?? ''}
            />
            <InputError message={error} />
        </div>
    );
}

export type Opt = string | { value: string | number; label: string };

export function SelectField({
    name,
    label,
    error,
    className,
    defaultValue,
    options,
    emptyLabel,
    required,
}: BaseProps & {
    defaultValue?: string | number | null;
    options: Opt[];
    emptyLabel?: string;
    required?: boolean;
}) {
    return (
        <div className={cn('grid min-w-0 gap-2', className)}>
            <Label htmlFor={name}>{label}</Label>
            <select
                id={name}
                name={name}
                required={required}
                defaultValue={defaultValue ?? ''}
                className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                {emptyLabel !== undefined && (
                    <option value="">{emptyLabel}</option>
                )}
                {options.map((o) => {
                    const v = typeof o === 'string' ? o : o.value;
                    const l = typeof o === 'string' ? o : o.label;

                    return (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    );
                })}
            </select>
            <InputError message={error} />
        </div>
    );
}

export function FormGrid({ children }: { children: ReactNode }) {
    return (
        <div className="grid max-h-[65vh] gap-4 overflow-y-auto p-1 sm:grid-cols-2">
            {children}
        </div>
    );
}
