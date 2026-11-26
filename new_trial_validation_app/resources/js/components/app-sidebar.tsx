import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Archive,
    ArrowLeftRight,
    Bell,
    Bot,
    BriefcaseBusiness,
    CalendarDays,
    FileText,
    Hash,
    ClipboardList,
    FolderKanban,
    Lightbulb,
    Receipt,
    CheckCircle2,
    CircleCheckBig,
    ClipboardCheck,
    FlaskConical,
    History,
    FileCheck,
    Hourglass,
    KeyRound,
    LayoutGrid,
    ListTree,
    Package,
    Printer,
    Route as RouteIcon,
    Search,
    Trash2,
    Users,
    Wrench,
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
import { index as archiveIndex } from '@/routes/admin/archive';
import { index as botQuestionsIndex } from '@/routes/admin/bot-questions';
import { index as laneConfigurationIndex } from '@/routes/admin/lane-configuration';
import { index as mastersIndex } from '@/routes/admin/masters';
import { index as notificationsIndex } from '@/routes/admin/notifications';
import { index as parametersIndex } from '@/routes/admin/parameters';
import { index as productsIndex } from '@/routes/admin/products';
import { index as trashIndex } from '@/routes/admin/trash';
import { index as usersIndex } from '@/routes/admin/users';
import { index as approvalsIndex } from '@/routes/approvals';
import { index as journeyIndex } from '@/routes/journey';
import { index as monitoringIndex } from '@/routes/monitoring';
import { index as projectsIndex } from '@/routes/projects';
import { index as improvementsIndex } from '@/routes/projects/improvements';
import { index as quotationsIndex } from '@/routes/projects/quotations';
import { index as toolingsIndex } from '@/routes/projects/toolings';
import { index as reportsIndex } from '@/routes/reports';
import { index as reviewsIndex } from '@/routes/reviews';
import { index as trialsIndex } from '@/routes/trials';
import { index as assessmentsIndex } from '@/routes/validation/assessments';
import { index as imIndex } from '@/routes/validation/im';
import { index as imMastersIndex } from '@/routes/validation/im-masters';
import { index as plansIndex } from '@/routes/validation/plans';
import { index as qcoIndex } from '@/routes/validation/qco';
import type { Auth, NavGroup } from '@/types';

export function AppSidebar() {
    const {
        auth,
        canReviewTrials,
        canApproveTrials,
        canViewToolings,
        canAccessFinance,
    } = usePage<{
        auth: Auth;
        canReviewTrials: boolean;
        canApproveTrials: boolean;
        canViewToolings: boolean;
        canAccessFinance: boolean;
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
                {
                    title: t('common.nav.monitoring'),
                    href: monitoringIndex(),
                    icon: Hourglass,
                },
                {
                    title: t('common.nav.journey'),
                    href: journeyIndex(),
                    icon: RouteIcon,
                    activePattern: /^\/journey(\/|$)/,
                },
                {
                    title: t('common.nav.assessment'),
                    href: assessmentsIndex(),
                    icon: ClipboardList,
                    activePattern: /^\/validation\/assessments(\/|$)/,
                },
                {
                    title: t('common.nav.validation_plan'),
                    href: plansIndex(),
                    icon: CalendarDays,
                    activePattern: /^\/validation\/plans(\/|$)/,
                },
                {
                    title: t('numbers.nav.qco'),
                    href: qcoIndex(),
                    icon: Hash,
                    activePattern: /^\/validation\/qco(\/|$)/,
                },
                {
                    title: t('numbers.nav.im'),
                    href: imIndex(),
                    icon: FileText,
                    activePattern: /^\/validation\/im(\/|$)/,
                },
                {
                    title: t('im_master.nav'),
                    href: imMastersIndex(),
                    icon: FileCheck,
                    activePattern:
                        /^\/validation\/im-(masters|revisions)(\/|$)/,
                },
                {
                    title: t('mixing.nav'),
                    href: '/mixings',
                    icon: FlaskConical,
                    activePattern: /^\/mixings(\/|$)/,
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
            // Everyone can read projects; create/edit buttons are gated per
            // project by ProjectPolicy on the pages themselves.
            label: t('common.nav.projects'),
            items: [
                {
                    title: t('common.nav.project_portfolio'),
                    href: projectsIndex(),
                    icon: FolderKanban,
                    activePattern: /^\/projects(\/(\d+|create|gantt)(\/|$)|$)/,
                },
                {
                    title: t('common.nav.project_improvements'),
                    href: improvementsIndex(),
                    icon: Lightbulb,
                    activePattern: /^\/projects\/improvements\//,
                },
                ...(canViewToolings
                    ? [
                          {
                              title: t('common.nav.project_toolings_menu'),
                              href: toolingsIndex(),
                              icon: Wrench,
                              activePattern: /^\/projects\/toolings(\/|$)/,
                          },
                      ]
                    : []),
                ...(canAccessFinance
                    ? [
                          {
                              title: t('common.nav.project_quotations'),
                              href: quotationsIndex(),
                              icon: Receipt,
                              activePattern:
                                  /^\/projects\/(quotations|purchase-orders)(\/|$)/,
                          },
                      ]
                    : []),
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
                          title: t('common.nav.archive'),
                          href: archiveIndex(),
                          icon: Archive,
                      },
                      {
                          title: t('common.nav.activity_logs'),
                          href: activityLogsIndex(),
                          icon: History,
                      },
                      {
                          title: t('common.nav.bot_questions'),
                          href: botQuestionsIndex(),
                          icon: Bot,
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
