import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { MyWorkSection } from '@/components/my-work-section';
import type { MyWork } from '@/components/my-work-section';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { create as createTrial } from '@/routes/trials';

type PageProps = {
    canCreateTrial: boolean;
    myWork: MyWork;
};

export default function MyWorkPage({ canCreateTrial, myWork }: PageProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('common.nav.my_work')} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={t('common.nav.my_work')}
                        description={t('dashboard.my_work.description')}
                    />
                    {canCreateTrial && (
                        <Button asChild>
                            <Link href={createTrial().url}>
                                <Plus />
                                {t('common.breadcrumb.new_trial')}
                            </Link>
                        </Button>
                    )}
                </div>

                <MyWorkSection myWork={myWork} />
            </div>
        </>
    );
}
