import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import {
    formatDate as formatDateIn,
    formatDateOnly as formatDateOnlyIn,
} from '@/lib/utils';

export type Locale = 'id' | 'en' | 'ko';

export type Replacements = Record<string, string | number>;

export type TranslateFn = (key: string, replace?: Replacements) => string;

export const LOCALES: { code: Locale; label: string; name: string }[] = [
    { code: 'id', label: 'ID', name: 'Bahasa Indonesia' },
    { code: 'en', label: 'EN', name: 'English' },
    { code: 'ko', label: '한국어', name: '한국어' },
];

// BCP 47 tags for Intl date/number formatting per UI language.
const INTL_LOCALES: Record<Locale, string> = {
    id: 'id-ID',
    en: 'en-GB',
    ko: 'ko-KR',
};

/**
 * Looks up `key` (e.g. `dashboard.title`) in the strings shared by
 * HandleInertiaRequests, filling `:name` placeholders the way Laravel's
 * `__()` does. An unknown key renders as itself so a gap is visible, not
 * blank.
 */
export function translate(
    translations: Record<string, string>,
    key: string,
    replace?: Replacements,
): string {
    let line = translations[key] ?? key;

    if (replace) {
        // Longest name first so `:count` never eats part of `:countAll`.
        Object.keys(replace)
            .sort((a, b) => b.length - a.length)
            .forEach((name) => {
                line = line.replaceAll(`:${name}`, String(replace[name]));
            });
    }

    return line;
}

export function useTranslation() {
    const { locale, translations } = usePage<{
        locale: Locale;
        translations: Record<string, string>;
    }>().props;

    const t: TranslateFn = useCallback(
        (key, replace) => translate(translations, key, replace),
        [translations],
    );

    const intlLocale = INTL_LOCALES[locale] ?? 'id-ID';

    const formatDate = useCallback(
        (value: string | null | undefined, withSeconds: boolean = false) =>
            formatDateIn(value, intlLocale, withSeconds),
        [intlLocale],
    );

    const formatDateOnly = useCallback(
        (value: string | null | undefined) =>
            formatDateOnlyIn(value, intlLocale),
        [intlLocale],
    );

    return { t, locale, intlLocale, formatDate, formatDateOnly };
}
