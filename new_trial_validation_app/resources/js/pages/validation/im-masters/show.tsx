import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ValidationImMasterController from '@/actions/App/Http/Controllers/ValidationImMasterController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { imStatusClassName } from '@/lib/im-master';
import { index as mastersIndex } from '@/routes/validation/im-masters';
import { show as revisionShow } from '@/routes/validation/im-revisions';

type Revision = {
    id: number;
    revision_no: number;
    im_number: string;
    status: string;
    issue_date: string | null;
    effective_from: string | null;
    revision_reason: string;
    replaces_number: string | null;
    prepared_by_name: string;
    approved_by: string | null;
    pending_with: string;
    is_onboarded: boolean;
};

type PageProps = {
    master: {
        id: number;
        fg_code: string;
        brand_code: string;
        product_part: string;
        product_name: string;
        customer: string;
        bulk_wip_refs: string[];
    };
    revisions: Revision[];
    can: { revise: boolean };
    hasOpen: boolean;
};

export default function ImMasterShow({
    master,
    revisions,
    can,
    hasOpen,
}: PageProps) {
    const { t } = useTranslation();
    const [revising, setRevising] = useState(false);

    return (
        <>
            <Head title={master.fg_code} />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={master.product_name || master.product_part}
                        description={`${master.fg_code} · ${master.customer}`}
                    />
                    {can.revise && (
                        <Button
                            className="gap-1.5"
                            onClick={() => setRevising(true)}
                        >
                            <Plus className="size-4" />
                            {t('im_master.revision.new')}
                        </Button>
                    )}
                </div>

                {hasOpen && (
                    <Alert>
                        <AlertDescription>
                            {t('im_master.revision.open_hint')}
                        </AlertDescription>
                    </Alert>
                )}

                {master.bulk_wip_refs.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('im_master.fields.bulk_wip_refs')}:{' '}
                        {master.bulk_wip_refs.join(', ')}
                    </p>
                )}

                <Card>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Rev.</TableHead>
                                    <TableHead>
                                        {t('im_master.fields.im_number')}
                                    </TableHead>
                                    <TableHead>
                                        {t('im_master.list.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t('im_master.fields.effective_from')}
                                    </TableHead>
                                    <TableHead>
                                        {t('im_master.fields.revision_reason')}
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {revisions.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell>{r.revision_no}</TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {r.im_number}
                                            {r.is_onboarded && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2"
                                                >
                                                    {t(
                                                        'im_master.revision.onboarded',
                                                    )}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                className={imStatusClassName(
                                                    r.status,
                                                )}
                                            >
                                                {t(
                                                    `im_master.status.${r.status}`,
                                                )}
                                            </Badge>
                                            {r.pending_with && (
                                                <div className="text-xs text-muted-foreground">
                                                    {r.pending_with}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {r.effective_from ?? '—'}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate">
                                            {r.revision_reason || '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link href={revisionShow(r.id)}>
                                                    {t('im_master.list.open')}
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {revising && (
                <Dialog
                    open
                    onOpenChange={(open) => !open && setRevising(false)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('im_master.revision.new')}
                            </DialogTitle>
                        </DialogHeader>
                        <Form
                            {...ValidationImMasterController.revisionStore.form(
                                master.id,
                            )}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="revision_reason">
                                            {t(
                                                'im_master.fields.revision_reason',
                                            )}
                                        </Label>
                                        <Input
                                            id="revision_reason"
                                            name="revision_reason"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {t('im_master.revision.new_hint')}
                                        </p>
                                        <InputError
                                            message={errors.revision_reason}
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('im_master.revision.create')}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

ImMasterShow.layout = {
    breadcrumbs: [{ title: 'IM Master', href: mastersIndex() }],
};
