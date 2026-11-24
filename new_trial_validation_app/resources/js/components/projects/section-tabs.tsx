import { Link } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { gantt, index as portfolioIndex } from '@/routes/projects';
import { index as purchaseOrdersIndex } from '@/routes/projects/purchase-orders';
import { index as quotationsIndex } from '@/routes/projects/quotations';
import {
    index as toolingQueue,
    report as toolingReport,
} from '@/routes/projects/toolings';

export type ProjectSection = 'portfolio' | 'tooling' | 'finance';

type Tab = { key: string; labelKey: string; href: string };

const SECTIONS: Record<ProjectSection, Tab[]> = {
    portfolio: [
        {
            key: 'list',
            labelKey: 'common.nav.project_tab_list',
            href: portfolioIndex().url,
        },
        {
            key: 'gantt',
            labelKey: 'common.nav.project_gantt',
            href: gantt().url,
        },
    ],
    tooling: [
        {
            key: 'queue',
            labelKey: 'common.nav.project_tooling_queue',
            href: toolingQueue().url,
        },
        {
            key: 'report',
            labelKey: 'common.nav.project_tooling_report',
            href: toolingReport().url,
        },
    ],
    finance: [
        {
            key: 'quotations',
            labelKey: 'common.nav.project_tab_quotations',
            href: quotationsIndex().url,
        },
        {
            key: 'purchase-orders',
            labelKey: 'common.nav.project_purchase_orders',
            href: purchaseOrdersIndex().url,
        },
    ],
};

/**
 * Segmented link bar that makes sibling pages read as tabs of one section.
 * Each tab is a real page/route, so deep links and bookmarks keep working.
 */
export function SectionTabs({
    section,
    active,
}: {
    section: ProjectSection;
    active: string;
}) {
    const { t } = useTranslation();

    return (
        <nav
            aria-label={t('common.nav.projects')}
            className="inline-flex max-w-full overflow-x-auto rounded-lg bg-muted p-1"
        >
            {SECTIONS[section].map((tab) => {
                const isActive = tab.key === active;

                return (
                    <Link
                        key={tab.key}
                        href={tab.href}
                        aria-current={isActive ? 'page' : undefined}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            isActive
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {t(tab.labelKey)}
                    </Link>
                );
            })}
        </nav>
    );
}
