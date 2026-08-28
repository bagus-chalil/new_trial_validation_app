import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type TrialContext = {
    id: number;
    trial_code?: string;
    progress_status?: string;
};

const trialGroups: Record<string, string> = {
    Draft: 'draft',
    'In Review': 'in-review',
    'Ready for Approval': 'waiting-approval',
    Approved: 'approved',
    'Need Revision': 'need-revision',
    Rejected: 'rejected',
};

function item(title: string, href: string): BreadcrumbItemType {
    return { title, href };
}

function trialStatusLabel(status: string | undefined): string {
    return status ?? 'Draft';
}

function trialDetailLabel(segments: string[]): string {
    switch (segments[2]) {
        case 'edit':
            return 'Edit Form Trial';
        case 'validation':
            return 'Validation';
        case 'weighing':
            return `${segments[3] ?? 'Packaging'} Weighing`;
        case 'attachments':
            return 'Attachments';
        case 'review':
            return 'Review';
        case 'report':
            return 'Trial Report';
        default:
            return 'Trial Detail';
    }
}

function trialBreadcrumbs(
    segments: string[],
    trial: TrialContext,
): BreadcrumbItemType[] {
    const group = trialGroups[trialStatusLabel(trial.progress_status)] ?? 'draft';
    const statusTitle = trialStatusLabel(trial.progress_status);

    return [
        item('Trials', `/trials/${group}`),
        item(`${statusTitle} Trials`, `/trials/${group}`),
        item(trialDetailLabel(segments), '#'),
    ];
}

function groupedBreadcrumbs(
    pathname: string,
    fallback: BreadcrumbItemType[],
): BreadcrumbItemType[] | null {
    if (pathname.startsWith('/admin/')) {
        const title = fallback.at(-1)?.title ?? 'Admin';
        let group = 'Master Data';

        if (['Users', 'Access Rights'].includes(title)) {
            group = 'User Management';
        } else if (['Notifications', 'Trash', 'Activity Logs'].includes(title)) {
            group = 'System';
        }

        return [item(group, '#'), item(title, '#')];
    }

    if (pathname.startsWith('/settings/')) {
        return [item('Settings', '#'), ...fallback];
    }

    if (pathname === '/reports' || pathname.startsWith('/reports/')) {
        return [
            item('Report', '/reports'),
            ...fallback.filter(
                (entry) =>
                    entry.title !== 'Dashboard' && entry.title !== 'Report',
            ),
        ];
    }

    return null;
}

export function contextualBreadcrumbs(
    url: string,
    fallback: BreadcrumbItemType[],
    trial?: TrialContext,
): BreadcrumbItemType[] {
    const pathname = new URL(url, 'http://localhost').pathname;
    const segments = pathname.split('/').filter(Boolean);

    if (segments[0] === 'trials') {
        if (segments[1] === 'create') {
            return [item('Trials', '/trials/draft'), item('New Trial', '#')];
        }

        if (segments.length === 2 && trialGroups[segments[1]]) {
            const title = Object.entries(trialGroups).find(
                ([, group]) => group === segments[1],
            )?.[0] ?? 'Trials';

            return [item('Trials', '/trials/draft'), item(`${title} Trials`, '#')];
        }

        if (trial && segments[1] && /^\d+$/.test(segments[1])) {
            return trialBreadcrumbs(segments, trial);
        }
    }

    if (pathname === '/reviews') {
        return [
            item('Trials', '/trials/in-review'),
            item('In Review', '/trials/in-review'),
            item('Review Queue Saya', '#'),
        ];
    }

    if (pathname === '/approvals') {
        return [
            item('Trials', '/trials/waiting-approval'),
            item('Ready for Approval', '/trials/waiting-approval'),
            item('Approval Queue Saya', '#'),
        ];
    }

    return groupedBreadcrumbs(pathname, fallback) ?? fallback;
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    readonly breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;

                            return (
                                <Fragment key={item.title}>
                                    <BreadcrumbItem>
                                        {isLast ? (
                                            <BreadcrumbPage>
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
