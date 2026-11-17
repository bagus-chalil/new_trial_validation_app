import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    activePath?: string;
    /** Extra URL paths (regex) that also highlight this item, e.g. its detail pages. */
    activePattern?: RegExp;
    /** Trial progress_status values whose detail pages highlight this item. */
    trialStatuses?: string[];
};

export type NavGroup = {
    label: string;
    items: NavItem[];
};
