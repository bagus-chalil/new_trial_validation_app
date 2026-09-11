import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { MyWorkSection } from '@/components/my-work-section';
import type { MyWork } from '@/components/my-work-section';
import { Button } from '@/components/ui/button';
import { create as createTrial } from '@/routes/trials';

type PageProps = {
    canCreateTrial: boolean;
    myWork: MyWork;
};

export default function MyWorkPage({ canCreateTrial, myWork }: PageProps) {
    return (
        <>
            <Head title="My Work" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="My Work"
                        description="Ringkasan aksi Anda: draft yang perlu dilanjutkan, trial yang perlu direvisi, yang sedang berjalan, serta review/approval yang menunggu Anda."
                    />
                    {canCreateTrial && (
                        <Button asChild>
                            <Link href={createTrial().url}>
                                <Plus />
                                New Trial
                            </Link>
                        </Button>
                    )}
                </div>

                <MyWorkSection myWork={myWork} />
            </div>
        </>
    );
}
