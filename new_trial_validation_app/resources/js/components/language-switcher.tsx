import { router } from '@inertiajs/react';
import { LOCALES, useTranslation } from '@/hooks/use-translation';
import type { Locale } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

// Read server-side by the SetLocale middleware; excluded from cookie
// encryption in bootstrap/app.php, same as `appearance`.
function storeLocale(locale: Locale): void {
    const maxAge = 365 * 24 * 60 * 60;
    document.cookie = `locale=${locale};path=/;max-age=${maxAge};SameSite=Lax`;
}

export function LanguageSwitcher({
    className,
}: {
    readonly className?: string;
}) {
    const { t, locale } = useTranslation();

    function select(code: Locale) {
        if (code === locale) {
            return;
        }

        storeLocale(code);
        // Full reload of the current page's props, so server-rendered text
        // (flash messages, labels built in controllers) switches too.
        router.reload();
    }

    return (
        <div
            role="group"
            aria-label={t('common.language')}
            className={cn(
                'inline-flex items-center gap-0.5 rounded-full border bg-background p-0.5',
                className,
            )}
        >
            {LOCALES.map((option) => (
                <button
                    key={option.code}
                    type="button"
                    lang={option.code}
                    title={option.name}
                    aria-pressed={option.code === locale}
                    onClick={() => select(option.code)}
                    className={cn(
                        'rounded-full px-2.5 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:text-foreground',
                        option.code === locale &&
                            'bg-brand text-white hover:text-white',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
