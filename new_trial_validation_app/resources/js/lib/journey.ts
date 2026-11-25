import type { TranslateFn } from '@/hooks/use-translation';

// Trial Result values are derived server-side (App\Services\Validation\TrialOutcome)
// and shown through a label map, never translated at the source.
const RESULT_KEYS: Record<string, string> = {
    Pass: 'pass',
    'Pass with Approval Client': 'pass_client',
    'Re-trial': 'retrial',
    Fail: 'fail',
    Pending: 'pending',
};

export function journeyResultLabel(t: TranslateFn, result: string): string {
    const key = RESULT_KEYS[result];

    return key ? t(`journey.result.${key}`) : result;
}

export function journeyResultClassName(result: string): string {
    switch (result) {
        case 'Pass':
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
        case 'Pass with Approval Client':
            return 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200';
        case 'Re-trial':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
        case 'Fail':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

export function journeyTypeLabel(t: TranslateFn, type: string): string {
    return ['NPD', 'IMP', 'MIX'].includes(type)
        ? t(`journey.type.${type}`)
        : type;
}
