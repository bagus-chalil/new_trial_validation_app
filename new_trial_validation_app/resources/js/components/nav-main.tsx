import { Link, usePage } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavGroup } from '@/types';

export function NavMain({ groups = [] }: { readonly groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { trial } = usePage<{ trial?: { progress_status?: string } }>().props;

    const statusNavTitles: Record<string, string> = {
        Draft: 'Draft',
        'In Review': 'In Review',
        'Ready for Approval': 'Ready for Approval',
        Approved: 'Approved',
        'Need Revision': 'Need Revision',
        Rejected: 'Rejected',
    };

    function isItemActive(item: NavGroup['items'][number]) {
        if (isCurrentUrl(item.href)) {
            return true;
        }

        return Boolean(
            trial?.progress_status &&
                statusNavTitles[trial.progress_status] === item.title,
        );
    }

    return (
        <>
            {groups.map(
                (group) =>
                    group.items.length > 0 && (
                        <SidebarGroup key={group.label} className="px-2 py-0">
                            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isItemActive(item)}
                                            tooltip={{ children: item.title }}
                                            className="data-[active=true]:bg-brand data-[active=true]:text-white data-[active=true]:hover:bg-brand/90"
                                        >
                                            <Link href={item.href} prefetch>
                                                {item.icon && <item.icon />}
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    ),
            )}
        </>
    );
}
