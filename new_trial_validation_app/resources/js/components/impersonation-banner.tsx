import { Form, usePage } from '@inertiajs/react';
import ImpersonationController from '@/actions/App/Http/Controllers/Admin/ImpersonationController';
import { Button } from '@/components/ui/button';

/**
 * Shown on every page while an admin is in a "Login as User" session, so
 * it's never mistaken for the admin's own account.
 */
export function ImpersonationBanner() {
    const { auth, impersonator } = usePage().props;

    if (!impersonator) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <span>
                Anda sedang login sebagai <strong>{auth.user.name}</strong> (
                {auth.user.email}) — admin: {impersonator.name}
            </span>
            <Form {...ImpersonationController.destroy.form()}>
                {({ processing }) => (
                    <Button size="sm" variant="outline" disabled={processing}>
                        Kembali ke akun admin
                    </Button>
                )}
            </Form>
        </div>
    );
}
