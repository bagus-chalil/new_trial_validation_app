import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('account.appearance.head')} />

            <h1 className="sr-only">{t('account.appearance.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('account.appearance.title')}
                    description={t('account.appearance.description')}
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'account.appearance.head',
            href: editAppearance(),
        },
    ],
};
