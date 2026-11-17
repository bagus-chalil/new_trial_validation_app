import type { TranslateFn } from '@/hooks/use-translation';

/**
 * Display helpers for the Projects module. Stored values (status, priority,
 * stage type, ...) are shared with the imported legacy data and are never
 * translated at the source - only shown through these maps. An unknown value
 * falls back to its raw text.
 */
function translateOrRaw(t: TranslateFn, key: string, raw: string): string {
    const label = t(key);

    return label === key ? raw : label;
}

export const projectStatusLabel = (t: TranslateFn, status: string) =>
    translateOrRaw(t, `projects.status.${status}`, status);

export const projectPriorityLabel = (t: TranslateFn, priority: string) =>
    translateOrRaw(t, `projects.priority.${priority}`, priority);

export const stageTypeLabel = (t: TranslateFn, type: string) =>
    translateOrRaw(t, `projects.stage.types.${type}`, type);

export const milestoneStatusLabel = (t: TranslateFn, status: string) =>
    translateOrRaw(t, `projects.milestone.status.${status}`, status);

export const quotationStatusLabel = (t: TranslateFn, status: string) =>
    translateOrRaw(t, `projects.quotation.status.${status}`, status);

export const projectStatusClassName = (status: string): string => {
    switch (status) {
        case 'Completed':
            return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
        case 'On Progress':
            return 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200';
        case 'Delayed':
            return 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200';
        case 'On Hold':
            return 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
};

export const quotationStatusClassName = (status: string): string => {
    switch (status) {
        case 'Terpilih':
            return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
        case 'Diterima':
            return 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-200';
        case 'Ditolak':
            return 'border-red-300 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200';
        default:
            return 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200';
    }
};

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatMoney(
    amount: number,
    currency: string,
    intlLocale: string,
): string {
    try {
        return new Intl.NumberFormat(intlLocale, {
            style: 'currency',
            currency,
            maximumFractionDigits: 0,
        }).format(amount);
    } catch {
        return `${currency} ${amount.toLocaleString(intlLocale)}`;
    }
}
