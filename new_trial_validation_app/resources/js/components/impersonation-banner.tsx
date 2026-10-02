import { Form, usePage } from '@inertiajs/react';
import ImpersonationController from '@/actions/App/Http/Controllers/Admin/ImpersonationController';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Shown on every page while an admin is in a "Login as User" session, so
 * it's never mistaken for the admin's own account.
 */
export function ImpersonationBanner() {
    const { auth, impersonator } = usePage().props;
    const { t } = useTranslation();

    if (!impersonator) {
        return null;
    }

    // `:name` is left unfilled so the impersonated user's name can be
    // rendered bold wherever the translation places it.
    const [before, after = ''] = t('admin.impersonation.banner', {
        email: auth.user.email,
        admin: impersonator.name,
    }).split(':name');

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <span>
                {before}
                <strong>{auth.user.name}</strong>
                {after}
            </span>
            <Form {...ImpersonationController.destroy.form()}>
                {({ processing }) => (
                    <Button size="sm" variant="outline" disabled={processing}>
                        {t('admin.impersonation.back')}
                    </Button>
                )}
            </Form>
        </div>
    );
}
