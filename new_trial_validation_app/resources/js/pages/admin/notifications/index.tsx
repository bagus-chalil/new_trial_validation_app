import { Head } from '@inertiajs/react';
import NotificationController from '@/actions/App/Http/Controllers/Admin/NotificationController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { PaginationFooter } from '@/components/pagination-footer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { index as notificationsIndex } from '@/routes/admin/notifications';
import type { Paginated } from '@/types';

type NotificationItem = {
    id: number;
    title: string;
    message: string;
    type: string;
    role_target: string | null;
    department_target: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
    trial: { id: number; trial_code: string } | null;
};

type PageProps = {
    notifications: Paginated<NotificationItem>;
};

export default function AdminNotificationsIndex({ notifications }: PageProps) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('admin.notifications.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('admin.notifications.heading')}
                    description={t('admin.notifications.description')}
                />

                <Card>
                    <CardContent className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('admin.notifications.id')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.notifications.title_column')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.notifications.target')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.notifications.trial')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.notifications.type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.notifications.created_at')}
                                    </TableHead>
                                    <TableHead>
                                        {t('admin.fields.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {notifications.data.map((notification) => (
                                    <TableRow key={notification.id}>
                                        <TableCell>{notification.id}</TableCell>
                                        <TableCell className="whitespace-normal">
                                            <div>{notification.title}</div>
                                            <div className="text-muted-foreground">
                                                {notification.message}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {notification.user?.name ??
                                                notification.role_target ??
                                                '-'}{' '}
                                            {notification.department_target ??
                                                ''}
                                        </TableCell>
                                        <TableCell>
                                            {notification.trial?.trial_code ??
                                                '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {notification.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {notification.created_at}
                                        </TableCell>
                                        <TableCell>
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                    >
                                                        {t(
                                                            'admin.actions.delete',
                                                        )}
                                                    </Button>
                                                }
                                                title={t(
                                                    'admin.notifications.delete_title',
                                                )}
                                                description={t(
                                                    'admin.notifications.delete_description',
                                                    {
                                                        title: notification.title,
                                                    },
                                                )}
                                                confirmLabel={t(
                                                    'admin.actions.delete',
                                                )}
                                                formProps={NotificationController.destroy.form(
                                                    notification.id,
                                                )}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {notifications.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="p-4 text-center text-muted-foreground"
                                        >
                                            {t('admin.notifications.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>

                        <PaginationFooter
                            url={notificationsIndex().url}
                            currentPage={notifications.current_page}
                            lastPage={notifications.last_page}
                            total={notifications.total}
                            itemLabel={t('admin.items.notifications')}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminNotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: notificationsIndex() }],
};
