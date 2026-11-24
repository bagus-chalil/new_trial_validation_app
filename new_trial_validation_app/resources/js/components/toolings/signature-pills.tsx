import { useTranslation } from '@/hooks/use-translation';
import { TOOLING_ROLES } from '@/lib/tooling';
import { cn } from '@/lib/utils';

/** PRNI / RNI / PIE / PRO chips, green once that box is signed. */
export function SignaturePills({
    signed,
}: {
    readonly signed: Record<string, boolean>;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap gap-1">
            {TOOLING_ROLES.map((role) => (
                <span
                    key={role}
                    title={t(`toolings.roles.${role}`)}
                    className={cn(
                        'rounded border px-1.5 py-0.5 text-[11px] font-medium',
                        signed[role]
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                            : 'border-border text-muted-foreground',
                    )}
                >
                    {t(`toolings.roles_short.${role}`)}
                    {signed[role] ? ' ✓' : ''}
                </span>
            ))}
        </div>
    );
}
