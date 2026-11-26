import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavGroup } from '@/types';

const STORAGE_KEY = 'sidebar-closed-groups';

function readClosed(): string[] {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function NavMain({ groups = [] }: { readonly groups: NavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const { props, url } = usePage<{
        trial?: { progress_status?: string };
    }>();
    const { trial } = props;
    const pathname = url.split('?')[0];
    const [closed, setClosed] = useState<string[]>(readClosed);

    function isItemActive(item: NavGroup['items'][number]) {
        if (isCurrentUrl(item.href)) {
            return true;
        }

        if (item.activePattern && item.activePattern.test(pathname)) {
            return true;
        }

        return Boolean(
            trial?.progress_status &&
            item.trialStatuses?.includes(trial.progress_status),
        );
    }

    function toggle(label: string, open: boolean) {
        const next = open
            ? closed.filter((l) => l !== label)
            : [...closed, label];
        setClosed(next);

        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // Storage blocked: the toggle still works for this page view.
        }
    }

    return (
        <>
            {groups.map((group) => {
                if (group.items.length === 0) {
                    return null;
                }

                const hasActive = group.items.some(isItemActive);
                // A group holding the current page never stays folded, and the
                // icon-only rail always shows every icon.
                const open =
                    state === 'collapsed' ||
                    hasActive ||
                    !closed.includes(group.label);

                return (
                    <Collapsible
                        key={group.label}
                        open={open}
                        onOpenChange={(next) => toggle(group.label, next)}
                        asChild
                    >
                        <SidebarGroup className="px-2 py-1">
                            <SidebarGroupLabel asChild>
                                <CollapsibleTrigger className="group/label w-full cursor-pointer justify-between text-[11px] font-semibold tracking-wider uppercase hover:text-sidebar-foreground">
                                    {group.label}
                                    <ChevronRight
                                        className={cn(
                                            'size-3.5 transition-transform',
                                            open && 'rotate-90',
                                        )}
                                    />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarMenu className="gap-0.5">
                                    {group.items.map((item) => (
                                        <SidebarMenuItem key={toUrl(item.href)}>
                                            <SidebarMenuButton
                                                asChild
                                                isActive={isItemActive(item)}
                                                tooltip={{
                                                    children: item.title,
                                                }}
                                                className="rounded-lg data-[active=true]:bg-brand data-[active=true]:font-medium data-[active=true]:text-white data-[active=true]:shadow-sm data-[active=true]:hover:bg-brand/90"
                                            >
                                                <Link href={item.href} prefetch>
                                                    {item.icon && <item.icon />}
                                                    <span>{item.title}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                );
            })}
        </>
    );
}
