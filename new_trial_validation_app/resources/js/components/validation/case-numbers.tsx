import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import ValidationCaseNumberController from '@/actions/App/Http/Controllers/ValidationCaseNumberController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { index as imIndex } from '@/routes/validation/im';
import { index as qcoIndex } from '@/routes/validation/qco';

export type CaseNumbersData = {
    qco: { id: number; number: string; status: string; client: string }[];
    im: { id: number; number: string; status: string; version_no: number }[];
};

export type AvailableNumbers = {
    qco: { id: number; label: string }[];
    im: { id: number; label: string }[];
};

export type IpcData = {
    available: boolean;
    batches: {
        id: number;
        no_batch: string;
        stage: string;
        flow_type: string | null;
        created_at: string | null;
        mixing_date: string | null;
        exp_date: string | null;
        fg_code: string;
        product_name: string;
        im_document_number: string | null;
        matched_by: 'im' | 'fg';
    }[];
};

function LinkDialog({
    caseId,
    available,
    canLink,
    open,
    onOpenChange,
}: {
    caseId: number;
    available: AvailableNumbers;
    canLink: { qco: boolean; im: boolean };
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const kinds = (['qco', 'im'] as const).filter((k) => canLink[k]);
    const [kind, setKind] = useState<'qco' | 'im'>(kinds[0] ?? 'qco');
    const [numberId, setNumberId] = useState('');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('numbers.journey.link_title')}</DialogTitle>
                </DialogHeader>
                <Form
                    {...ValidationCaseNumberController.store.form(caseId)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="kind" value={kind} />
                            <input
                                type="hidden"
                                name="number_id"
                                value={numberId}
                            />
                            <div className="flex gap-2">
                                {kinds.map((k) => (
                                    <Button
                                        key={k}
                                        type="button"
                                        size="sm"
                                        variant={
                                            kind === k ? 'default' : 'outline'
                                        }
                                        onClick={() => {
                                            setKind(k);
                                            setNumberId('');
                                        }}
                                    >
                                        {k.toUpperCase()}
                                    </Button>
                                ))}
                            </div>
                            <div className="grid min-w-0 gap-2">
                                <Combobox
                                    options={available[kind].map((n) => ({
                                        value: String(n.id),
                                        label: n.label,
                                    }))}
                                    value={numberId}
                                    onChange={setNumberId}
                                />
                                {available[kind].length === 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('numbers.journey.no_available')}
                                    </p>
                                )}
                                <InputError
                                    message={errors.number ?? errors.number_id}
                                />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={processing || numberId === ''}
                                >
                                    {t('numbers.journey.link')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function CaseNumbers({
    caseId,
    numbers,
    available,
    canLink,
}: {
    caseId: number;
    numbers: CaseNumbersData;
    available: AvailableNumbers;
    canLink: { qco: boolean; im: boolean };
}) {
    const { t } = useTranslation();
    const [linking, setLinking] = useState(false);
    const rows = [
        ...numbers.qco.map((n) => ({ ...n, kind: 'qco' as const })),
        ...numbers.im.map((n) => ({ ...n, kind: 'im' as const })),
    ];

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1">
                    <CardTitle>{t('numbers.journey.title')}</CardTitle>
                    <p className="text-sm text-muted-foreground">
                        {t('numbers.journey.description')}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild size="sm" variant="ghost">
                        <Link href={qcoIndex().url}>QCO</Link>
                    </Button>
                    <Button asChild size="sm" variant="ghost">
                        <Link href={imIndex().url}>IM</Link>
                    </Button>
                    {(canLink.qco || canLink.im) && (
                        <Button size="sm" onClick={() => setLinking(true)}>
                            {t('numbers.journey.link')}
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-2">
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('numbers.journey.none')}
                    </p>
                ) : (
                    <ul className="divide-y text-sm">
                        {rows.map((n) => (
                            <li
                                key={`${n.kind}-${n.id}`}
                                className="flex items-center justify-between gap-3 py-2"
                            >
                                <span className="flex items-center gap-2">
                                    <Badge variant="outline">
                                        {n.kind.toUpperCase()}
                                    </Badge>
                                    <span className="font-medium">
                                        {n.number}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {t(`numbers.status.${n.status}`)}
                                    </span>
                                </span>
                                {canLink[n.kind] && (
                                    <ConfirmDialog
                                        trigger={
                                            <Button size="sm" variant="ghost">
                                                {t('numbers.journey.unlink')}
                                            </Button>
                                        }
                                        title={t('numbers.journey.unlink')}
                                        description={n.number}
                                        confirmLabel={t(
                                            'numbers.journey.unlink',
                                        )}
                                        formProps={ValidationCaseNumberController.destroy.form(
                                            {
                                                case: caseId,
                                                kind: n.kind,
                                                number: n.id,
                                            },
                                        )}
                                    />
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <p className="text-xs text-muted-foreground">
                    {t('numbers.journey.manual_note')}
                </p>
            </CardContent>

            {linking && (
                <LinkDialog
                    caseId={caseId}
                    available={available}
                    canLink={canLink}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setLinking(false);
                        }
                    }}
                />
            )}
        </Card>
    );
}

export function CaseIpcBatches({ ipc }: { ipc: IpcData }) {
    const { t, formatDateOnly } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('numbers.ipc.title')}</CardTitle>
                <p className="text-sm text-muted-foreground">
                    {t('numbers.ipc.description')}
                </p>
            </CardHeader>
            <CardContent>
                {!ipc.available ? (
                    <p className="text-sm text-muted-foreground">
                        {t('numbers.ipc.unavailable')}
                    </p>
                ) : ipc.batches.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('numbers.ipc.empty')}
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('numbers.ipc.no_batch')}
                                </TableHead>
                                <TableHead>
                                    {t('numbers.qco.product_name')}
                                </TableHead>
                                <TableHead>{t('numbers.ipc.stage')}</TableHead>
                                <TableHead>
                                    {t('numbers.ipc.mixing_date')}
                                </TableHead>
                                <TableHead>
                                    {t('numbers.ipc.exp_date')}
                                </TableHead>
                                <TableHead>{t('numbers.ipc.im')}</TableHead>
                                <TableHead>
                                    {t('numbers.ipc.matched_by')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ipc.batches.map((b) => (
                                <TableRow key={b.id}>
                                    <TableCell className="font-medium">
                                        {b.no_batch}
                                    </TableCell>
                                    <TableCell>
                                        {b.product_name} · {b.fg_code}
                                    </TableCell>
                                    <TableCell>{b.stage}</TableCell>
                                    <TableCell>
                                        {b.mixing_date
                                            ? formatDateOnly(b.mixing_date)
                                            : '-'}
                                    </TableCell>
                                    <TableCell>
                                        {b.exp_date
                                            ? formatDateOnly(b.exp_date)
                                            : '-'}
                                    </TableCell>
                                    <TableCell>
                                        {b.im_document_number ?? '-'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {t(
                                                `numbers.ipc.matched_${b.matched_by}`,
                                            )}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
