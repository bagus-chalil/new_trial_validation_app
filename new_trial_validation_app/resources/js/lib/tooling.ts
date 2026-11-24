import type { TranslateFn } from '@/hooks/use-translation';

/**
 * Display helpers for Check Tooling. Stored values (status, sample status,
 * tool status, risk, product type) are shared with imported legacy data and
 * only translated here; an unknown value falls back to its raw text.
 */
function translateOrRaw(t: TranslateFn, key: string, raw: string): string {
    const label = t(key);

    return label === key ? raw : label;
}

export const TOOLING_ROLES = ['prni', 'rni', 'pi', 'production'] as const;

export type ToolingRole = (typeof TOOLING_ROLES)[number];

export const toolingStatusLabel = (t: TranslateFn, status: string) =>
    translateOrRaw(t, `toolings.status.${status}`, status);

export const toolingSampleStatusLabel = (t: TranslateFn, status: string) =>
    translateOrRaw(t, `toolings.sample_status.${status}`, status);

export const toolingProductTypeLabel = (t: TranslateFn, type: string) =>
    translateOrRaw(t, `toolings.product_type.${type}`, type);

export const toolingToolStatusLabel = (t: TranslateFn, status: string) =>
    translateOrRaw(t, `toolings.tool_status.${status}`, status);

export const toolingRiskLabel = (t: TranslateFn, risk: string) =>
    translateOrRaw(t, `toolings.risk.${risk}`, risk);

export const toolingActionLabel = (t: TranslateFn, action: string) =>
    translateOrRaw(t, `toolings.actions.${action}`, action);

const GREEN =
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
const RED =
    'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200';
const AMBER =
    'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200';
const SKY =
    'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200';
const VIOLET =
    'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950 dark:text-violet-200';
const ROSE =
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200';

export const toolingStatusClassName = (status: string): string => {
    switch (status) {
        case 'Approved':
            return GREEN;
        case 'Ditolak':
            return RED;
        case 'Revisi / Hold':
        case 'Menunggu Sampel':
            return AMBER;
        case 'Cek Tooling PI':
            return SKY;
        case 'Review Production':
            return VIOLET;
        default:
            return ROSE;
    }
};

export const toolingToolStatusClassName = (status: string): string => {
    switch (status) {
        case 'OK':
            return GREEN;
        case 'NOK':
            return RED;
        case 'Need New':
            return AMBER;
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
};

export const toolingRiskClassName = (risk: string): string => {
    switch (risk) {
        case 'High risk':
            return RED;
        case 'Medium risk':
            return AMBER;
        case 'Low risk':
            return GREEN;
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
};
