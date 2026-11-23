import { Plus, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { toolingRiskLabel, toolingToolStatusLabel } from '@/lib/tooling';

type Errors = Record<string, string>;

export function StepField({
    label,
    error,
    children,
}: {
    readonly label: string;
    readonly error?: string;
    readonly children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

/** The e-signature: the user's own account password (same as Trial Approval). */
export function SignaturePasswordField({
    errors,
}: {
    readonly errors: Errors;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-2 rounded-md border border-dashed p-3">
            <Label htmlFor="signature_password">
                {t('toolings.fields.signature_password')} *
            </Label>
            <Input
                id="signature_password"
                type="password"
                name="signature_password"
                autoComplete="current-password"
                required
            />
            <p className="text-xs text-muted-foreground">
                {t('toolings.fields.signature_password_hint')}{' '}
                {t('toolings.show.sign_hint')}
            </p>
            <InputError message={errors.signature_password} />
        </div>
    );
}

export function NoteField({ errors }: { readonly errors: Errors }) {
    const { t } = useTranslation();

    return (
        <StepField label={t('toolings.fields.note')} error={errors.note}>
            <Textarea name="note" rows={2} />
        </StepField>
    );
}

type ToolRow = { tool: string; actual: string; status: string };

const NO_STATUS = '__none__';

/** PIE check: tool rows + machine / investment / risk, then the PIE signature. */
export function PieCheckFields({
    errors,
    initialTools,
    defaultTools,
    toolStatuses,
    investValues,
    riskLevels,
    initial,
}: {
    readonly errors: Errors;
    readonly initialTools: ToolRow[];
    readonly defaultTools: string[];
    readonly toolStatuses: string[];
    readonly investValues: string[];
    readonly riskLevels: string[];
    readonly initial: {
        recommended_machine: string;
        need_invest: string;
        need_invest_note: string | null;
        production_note: string | null;
        risk_level: string;
    };
}) {
    const { t } = useTranslation();
    const [rows, setRows] = useState<ToolRow[]>(
        initialTools.length > 0
            ? initialTools
            : defaultTools.map((tool) => ({ tool, actual: '', status: '' })),
    );

    const update = (i: number, patch: Partial<ToolRow>) =>
        setRows((prev) =>
            prev.map((row, j) => (j === i ? { ...row, ...patch } : row)),
        );

    return (
        <div className="mt-4 grid gap-4">
            <div className="grid gap-2">
                <Label>{t('toolings.fields.tools')} *</Label>
                {rows.map((row, i) => (
                    <div
                        key={i}
                        className="grid gap-2 sm:grid-cols-[1fr_1fr_10rem_auto]"
                    >
                        <Input
                            name={`tools[${i}][tool]`}
                            placeholder={t('toolings.fields.tool')}
                            value={row.tool}
                            onChange={(e) =>
                                update(i, { tool: e.target.value })
                            }
                        />
                        <Input
                            name={`tools[${i}][actual]`}
                            placeholder={t('toolings.fields.actual')}
                            value={row.actual}
                            onChange={(e) =>
                                update(i, { actual: e.target.value })
                            }
                        />
                        <input
                            type="hidden"
                            name={`tools[${i}][status]`}
                            value={row.status}
                        />
                        <Select
                            value={row.status === '' ? NO_STATUS : row.status}
                            onValueChange={(v) =>
                                update(i, { status: v === NO_STATUS ? '' : v })
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_STATUS}>-</SelectItem>
                                {toolStatuses.map((s) => (
                                    <SelectItem key={s} value={s}>
                                        {toolingToolStatusLabel(t, s)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('toolings.show.remove_tool')}
                            disabled={rows.length <= 1}
                            onClick={() =>
                                setRows((prev) =>
                                    prev.filter((_, j) => j !== i),
                                )
                            }
                        >
                            <X />
                        </Button>
                    </div>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setRows((prev) => [
                                ...prev,
                                { tool: '', actual: '', status: '' },
                            ])
                        }
                    >
                        <Plus />
                        {t('toolings.show.add_tool')}
                    </Button>
                </div>
                <InputError message={errors.tools} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <StepField
                    label={`${t('toolings.fields.recommended_machine')} *`}
                    error={errors.recommended_machine}
                >
                    <Input
                        name="recommended_machine"
                        required
                        defaultValue={initial.recommended_machine}
                    />
                </StepField>
                <StepField
                    label={`${t('toolings.fields.need_invest')} *`}
                    error={errors.need_invest}
                >
                    <Select
                        name="need_invest"
                        defaultValue={initial.need_invest || undefined}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="-" />
                        </SelectTrigger>
                        <SelectContent>
                            {investValues.map((v) => (
                                <SelectItem key={v} value={v}>
                                    {v}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </StepField>
                <StepField
                    label={`${t('toolings.fields.risk_level')} *`}
                    error={errors.risk_level}
                >
                    <Select
                        name="risk_level"
                        defaultValue={initial.risk_level || undefined}
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="-" />
                        </SelectTrigger>
                        <SelectContent>
                            {riskLevels.map((v) => (
                                <SelectItem key={v} value={v}>
                                    {toolingRiskLabel(t, v)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </StepField>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <StepField
                    label={t('toolings.fields.need_invest_note')}
                    error={errors.need_invest_note}
                >
                    <Textarea
                        name="need_invest_note"
                        rows={3}
                        defaultValue={initial.need_invest_note ?? ''}
                    />
                </StepField>
                <StepField
                    label={t('toolings.fields.production_note')}
                    error={errors.production_note}
                >
                    <Textarea
                        name="production_note"
                        rows={3}
                        defaultValue={initial.production_note ?? ''}
                    />
                </StepField>
            </div>

            <SignaturePasswordField errors={errors} />
        </div>
    );
}
