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
import type { TranslateFn } from '@/hooks/use-translation';
import { trialStatusLabel } from '@/lib/trial-status';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type TrialContext = {
    id: number;
    trial_code?: string;
    progress_status?: string;
};

const trialGroups: Record<string, string> = {
    Draft: 'draft',
    'In Review': 'tracking',
    'Ready for Approval': 'tracking',
    Approved: 'approved',
    'Need Revision': 'need-revision',
    Rejected: 'rejected',
};

// Sidebar group of each /admin/* page, keyed by its path segment.
const adminGroups: Record<string, string> = {
    users: 'common.nav.user_management',
    'access-rights': 'common.nav.user_management',
    'lane-configuration': 'common.nav.user_management',
    notifications: 'common.nav.system',
    trash: 'common.nav.system',
    archive: 'common.nav.system',
    'activity-logs': 'common.nav.system',
};

// Translated page title of each /admin/* page, keyed by its path segment.
const adminTitles: Record<string, string> = {
    users: 'common.nav.users',
    'access-rights': 'common.nav.access_rights',
    'lane-configuration': 'common.nav.line_configuration',
    notifications: 'common.nav.notifications',
    trash: 'common.nav.trash',
    archive: 'common.nav.archive',
    'activity-logs': 'common.nav.activity_logs',
    products: 'common.nav.products',
    parameters: 'common.nav.parameters',
    masters: 'common.nav.masters',
};

// Top-level pages whose breadcrumb is just their own (translated) nav label.
const singlePages: Record<string, string> = {
    '/dashboard': 'common.nav.dashboard',
    '/my-work': 'common.nav.my_work',
};

function item(title: string, href: string): BreadcrumbItemType {
    return { title, href };
}

function trialDetailLabel(segments: string[], t: TranslateFn): string {
    switch (segments[2]) {
        case 'edit':
            return t('common.breadcrumb.edit_trial');
        case 'validation':
            return t('common.breadcrumb.validation');
        case 'weighing':
            return t(
                segments[3] === 'Filling'
                    ? 'common.wizard.weighing_filling'
                    : 'common.wizard.weighing_packaging',
            );
        case 'attachments':
            return t('common.breadcrumb.attachments');
        case 'review':
            return t('common.breadcrumb.review');
        case 'report':
            return t('common.breadcrumb.trial_report');
        default:
            return t('common.breadcrumb.trial_detail');
    }
}

function statusTrials(status: string, t: TranslateFn): string {
    return t('common.breadcrumb.status_trials', {
        status: trialStatusLabel(t, status),
    });
}

function trialBreadcrumbs(
    segments: string[],
    trial: TrialContext,
    t: TranslateFn,
): BreadcrumbItemType[] {
    const status = trial.progress_status ?? 'Draft';
    const group = trialGroups[status] ?? 'draft';

    return [
        item(t('common.breadcrumb.trials'), `/trials/${group}`),
        item(statusTrials(status, t), `/trials/${group}`),
        item(trialDetailLabel(segments, t), '#'),
    ];
}

function groupedBreadcrumbs(
    pathname: string,
    fallback: BreadcrumbItemType[],
    t: TranslateFn,
): BreadcrumbItemType[] | null {
    if (pathname.startsWith('/admin/')) {
        const segment = pathname.split('/')[2];
        const title = adminTitles[segment]
            ? t(adminTitles[segment])
            : (fallback.at(-1)?.title ?? 'Admin');
        const group = adminGroups[segment] ?? 'common.nav.master_data';

        return [item(t(group), '#'), item(title, '#')];
    }

    if (pathname.startsWith('/settings/')) {
        // Settings pages give their title as a translation key.
        return [
            item(t('common.breadcrumb.settings'), '#'),
            ...fallback.map((entry) => ({ ...entry, title: t(entry.title) })),
        ];
    }

    if (pathname === '/reports' || pathname.startsWith('/reports/')) {
        const reports = t('common.nav.reports');

        return [
            item(reports, '/reports'),
            ...fallback.filter(
                (entry) =>
                    entry.title !== 'Dashboard' &&
                    entry.title !== 'Report' &&
                    entry.title !== reports,
            ),
        ];
    }

    return null;
}

export function contextualBreadcrumbs(
    url: string,
    fallback: BreadcrumbItemType[],
    trial: TrialContext | undefined,
    t: TranslateFn,
): BreadcrumbItemType[] {
    const pathname = new URL(url, 'http://localhost').pathname;
    const segments = pathname.split('/').filter(Boolean);
    const trials = t('common.breadcrumb.trials');

    if (segments[0] === 'trials') {
        if (segments[1] === 'create') {
            return [
                item(trials, '/trials/draft'),
                item(t('common.breadcrumb.new_trial'), '#'),
            ];
        }

        if (segments[1] === 'tracking') {
            return [
                item(trials, '/trials/draft'),
                item(t('common.nav.tracking'), '#'),
            ];
        }

        const status = Object.entries(trialGroups).find(
            ([, group]) => group === segments[1],
        )?.[0];

        if (segments.length === 2 && status) {
            return [
                item(trials, '/trials/draft'),
                item(statusTrials(status, t), '#'),
            ];
        }

        if (trial && segments[1] && /^\d+$/.test(segments[1])) {
            return trialBreadcrumbs(segments, trial, t);
        }
    }

    if (singlePages[pathname]) {
        return [item(t(singlePages[pathname]), pathname)];
    }

    if (pathname === '/reviews' || pathname === '/approvals') {
        return [
            item(trials, '/trials/tracking'),
            item(t('common.nav.tracking'), '/trials/tracking'),
            item(
                t(
                    pathname === '/reviews'
                        ? 'common.nav.need_review'
                        : 'common.nav.need_approval',
                ),
                '#',
            ),
        ];
    }

    return groupedBreadcrumbs(pathname, fallback, t) ?? fallback;
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
                                <Fragment key={`${index}-${item.title}`}>
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
