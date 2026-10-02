import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeftRight,
    Bell,
    BriefcaseBusiness,
    CheckCircle2,
    CircleCheckBig,
    ClipboardCheck,
    FlaskConical,
    History,
    KeyRound,
    LayoutGrid,
    ListTree,
    Package,
    Printer,
    Route as RouteIcon,
    Search,
    Trash2,
    Users,
    XCircle,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { index as accessRightsIndex } from '@/routes/admin/access-rights';
import { index as activityLogsIndex } from '@/routes/admin/activity-logs';
import { index as laneConfigurationIndex } from '@/routes/admin/lane-configuration';
import { index as mastersIndex } from '@/routes/admin/masters';
import { index as notificationsIndex } from '@/routes/admin/notifications';
import { index as parametersIndex } from '@/routes/admin/parameters';
import { index as productsIndex } from '@/routes/admin/products';
import { index as trashIndex } from '@/routes/admin/trash';
import { index as usersIndex } from '@/routes/admin/users';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as reportsIndex } from '@/routes/reports';
import { index as reviewsIndex } from '@/routes/reviews';
import { index as trialsIndex } from '@/routes/trials';
import type { Auth, NavGroup } from '@/types';

export function AppSidebar() {
    const { auth, canReviewTrials, canApproveTrials } = usePage<{
        auth: Auth;
        canReviewTrials: boolean;
        canApproveTrials: boolean;
    }>().props;
    const { t } = useTranslation();
    const isSuperAdmin = auth.user.role === 'Super Admin';
    const isAdmin = auth.user.role === 'Admin' || isSuperAdmin;
    const canManageTemplates = isAdmin || auth.user.role === 'Staff';

    const navGroups: NavGroup[] = [
        {
            label: t('common.nav.overview'),
            items: [
                {
                    title: t('common.nav.dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
        {
            label: t('common.nav.trials'),
            items: [
                {
                    title: t('common.nav.my_work'),
                    href: '/my-work',
                    icon: BriefcaseBusiness,
                },
                {
                    title: t('common.nav.tracking'),
                    href: trialsIndex('tracking'),
                    icon: Search,
                },
                ...(canReviewTrials
                    ? [
                          {
                              title: t('common.nav.need_review'),
                              href: reviewsIndex(),
                              icon: ClipboardCheck,
                          },
                      ]
                    : []),
                ...(canApproveTrials
                    ? [
                          {
                              title: t('common.nav.need_approval'),
                              href: approvalsIndex(),
                              icon: CircleCheckBig,
                          },
                      ]
                    : []),
                {
                    title: t('common.nav.need_revision'),
                    href: trialsIndex('need-revision'),
                    trialStatuses: ['Need Revision'],
                    icon: AlertTriangle,
                },
            ],
        },
        {
            label: t('common.nav.results'),
            items: [
                {
                    title: t('common.nav.approved'),
                    href: trialsIndex('approved'),
                    trialStatuses: ['Approved'],
                    icon: CheckCircle2,
                },
                {
                    title: t('common.nav.rejected'),
                    href: trialsIndex('rejected'),
                    trialStatuses: ['Rejected'],
                    icon: XCircle,
                },
            ],
        },
        {
            label: t('common.nav.report'),
            items: [
                {
                    title: t('common.nav.reports'),
                    href: reportsIndex(),
                    icon: Printer,
                },
            ],
        },
        {
            label: t('common.nav.master_data'),
            items: canManageTemplates
                ? [
                      {
                          title: t('common.nav.products'),
                          href: productsIndex(),
                          icon: Package,
                      },
                      {
                          title: t('common.nav.parameters'),
                          href: parametersIndex(),
                          icon: FlaskConical,
                      },
                      {
                          title: t('common.nav.masters'),
                          href: mastersIndex(),
                          icon: ListTree,
                      },
                  ]
                : [],
        },
        {
            label: t('common.nav.user_management'),
            items: [
                ...(isAdmin
                    ? [
                          {
                              title: t('common.nav.users'),
                              href: usersIndex(),
                              icon: Users,
                          },
                      ]
                    : []),
                ...(isSuperAdmin
                    ? [
                          {
                              title: t('common.nav.access_rights'),
                              href: accessRightsIndex(),
                              icon: KeyRound,
                          },
                          {
                              title: t('common.nav.line_configuration'),
                              href: laneConfigurationIndex(),
                              icon: RouteIcon,
                          },
                      ]
                    : []),
            ],
        },
        {
            label: t('common.nav.system'),
            items: isAdmin
                ? [
                      {
                          title: t('common.nav.notifications'),
                          href: notificationsIndex(),
                          icon: Bell,
                      },
                      {
                          title: t('common.nav.trash'),
                          href: trashIndex(),
                          icon: Trash2,
                      },
                      {
                          title: t('common.nav.activity_logs'),
                          href: activityLogsIndex(),
                          icon: History,
                      },
                  ]
                : [],
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" className="print:hidden">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            tooltip={{ children: t('common.nav.open_old_app') }}
                        >
                            {/* plain anchor, not Inertia Link: this hits a redirect to another
                                origin (the legacy app), which an Inertia XHR visit can't follow */}
                            <a href="/sso/to-old">
                                <ArrowLeftRight />
                                <span>{t('common.nav.old_app')}</span>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
