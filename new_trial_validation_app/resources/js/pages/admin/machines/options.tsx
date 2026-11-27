import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import MachineOptionMapController from '@/actions/App/Http/Controllers/Admin/MachineOptionMapController';
import { Combobox } from '@/components/combobox';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { index as machinesIndex } from '@/routes/admin/machines';

type Row = {
    map_id: number | null;
    option_name: string;
    trials: number;
    status: 'unmapped' | 'auto' | 'suggested' | 'manual' | 'not_applicable';
    score: number | null;
    reason: string | null;
    line_id: number | null;
    line_label: string | null;
    equipment_id: number | null;
    equipment_label: string | null;
};

type LineOption = { id: number; label: string; category: string };
type EquipmentOption = { id: number; line_id: number; label: string };

type PageProps = {
    rows: Row[];
    lines: LineOption[];
    equipment: EquipmentOption[];
};

const FILTERS = [
    'all',
    'unmapped',
    'suggested',
    'auto',
    'manual',
    'not_applicable',
] as const;

function statusClass(status: Row['status']): string {
    switch (status) {
        case 'auto':
        case 'manual':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
        case 'suggested':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
        case 'not_applicable':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

function MapDialog({
    row,
    lines,
    equipment,
    onClose,
}: {
    row: Row | null;
    lines: LineOption[];
    equipment: EquipmentOption[];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const [lineId, setLineId] = useState(String(row?.line_id ?? ''));
    const [equipmentId, setEquipmentId] = useState(
        String(row?.equipment_id ?? ''),
    );
    const [notApplicable, setNotApplicable] = useState(
        row?.status === 'not_applicable',
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const equipmentOptions = [
        { value: '', label: t('machines.mapping.none') },
        ...equipment
            .filter((e) => String(e.line_id) === lineId)
            .map((e) => ({ value: String(e.id), label: e.label })),
    ];

    function save() {
        if (!row) {
            return;
        }

        router.post(
            MachineOptionMapController.store().url,
            {
                option_name: row.option_name,
                not_applicable: notApplicable,
                line_id: notApplicable ? null : lineId || null,
                equipment_id: notApplicable ? null : equipmentId || null,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (e) => setErrors(e),
                onSuccess: onClose,
            },
        );
    }

    return (
        <Dialog open={row !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{row?.option_name}</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={notApplicable}
                            onCheckedChange={(v) =>
                                setNotApplicable(v === true)
                            }
                        />
                        {t('machines.mapping.na_check')}
                    </label>
                    {!notApplicable && (
                        <>
                            <div className="grid gap-2">
                                <span className="text-sm font-medium">
                                    {t('machines.mapping.line')}
                                </span>
                                <Combobox
                                    options={lines.map((l) => ({
                                        value: String(l.id),
                                        label: l.label,
                                    }))}
                                    value={lineId}
                                    onChange={(v) => {
                                        setLineId(v);
                                        setEquipmentId('');
                                    }}
                                    placeholder={t('machines.mapping.pick')}
                                />
                                {errors.line_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.line_id}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-medium">
                                    {t('machines.mapping.equipment')}
                                </span>
                                <Combobox
                                    options={equipmentOptions}
                                    value={equipmentId}
                                    onChange={setEquipmentId}
                                    placeholder={t('machines.mapping.none')}
                                    disabled={!lineId}
                                />
                                {errors.equipment_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.equipment_id}
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                    {errors.option_name && (
                        <p className="text-sm text-destructive">
                            {errors.option_name}
                        </p>
                    )}
                </div>
                <DialogFooter>
                    <Button type="button" onClick={save} disabled={processing}>
                        {t('machines.mapping.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function MachineOptionMapping({
    rows,
    lines,
    equipment,
}: PageProps) {
    const { t } = useTranslation();
    const [filter, setFilter] = useState<(typeof FILTERS)[number]>('all');
    const [q, setQ] = useState('');
    const [editing, setEditing] = useState<Row | null>(null);

    const counts = useMemo(() => {
        const c: Record<string, number> = { all: rows.length };
        rows.forEach((r) => {
            c[r.status] = (c[r.status] ?? 0) + 1;
        });

        return c;
    }, [rows]);

    const visible = rows.filter(
        (r) =>
            (filter === 'all' || r.status === filter) &&
            r.option_name.toLowerCase().includes(q.trim().toLowerCase()),
    );

    function accept(row: Row) {
        router.post(
            MachineOptionMapController.store().url,
            {
                option_name: row.option_name,
                line_id: row.line_id,
                equipment_id: row.equipment_id,
            },
            { preserveScroll: true },
        );
    }

    function reset(row: Row) {
        if (row.map_id === null) {
            return;
        }

        router.delete(
            MachineOptionMapController.destroy.url({ map: row.map_id }),
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title={t('machines.mapping.title')} />
            <div className="space-y-6 p-4">
                <Heading
                    title={t('machines.mapping.title')}
                    description={t('machines.mapping.description')}
                />
                <Card>
                    <CardHeader className="flex-row flex-wrap items-center gap-2">
                        {FILTERS.map((f) => (
                            <Button
                                key={f}
                                type="button"
                                size="sm"
                                variant={filter === f ? 'default' : 'outline'}
                                onClick={() => setFilter(f)}
                            >
                                {t(`machines.mapping.${f}`)} ({counts[f] ?? 0})
                            </Button>
                        ))}
                        <Input
                            className="ml-auto w-56"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder={t('machines.mapping.search')}
                        />
                        <Button asChild variant="outline" size="sm">
                            <Link href={machinesIndex()}>
                                {t('machines.mapping.back')}
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('machines.mapping.option')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.mapping.trials')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.mapping.target')}
                                    </TableHead>
                                    <TableHead>
                                        {t('machines.mapping.status')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('machines.mapping.action')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visible.map((r) => (
                                    <TableRow key={r.option_name}>
                                        <TableCell className="font-medium">
                                            {r.option_name}
                                        </TableCell>
                                        <TableCell>{r.trials}</TableCell>
                                        <TableCell className="text-sm">
                                            {r.line_label ?? '-'}
                                            {r.equipment_label && (
                                                <div className="text-muted-foreground">
                                                    {r.equipment_label}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="secondary"
                                                className={statusClass(
                                                    r.status,
                                                )}
                                                title={r.reason ?? undefined}
                                            >
                                                {t(
                                                    `machines.mapping.${r.status}`,
                                                )}
                                                {r.score !== null &&
                                                    r.status !== 'manual' &&
                                                    ` ${r.score.toFixed(2)}`}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="space-x-2 text-right">
                                            {(r.status === 'suggested' ||
                                                r.status === 'auto') && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => accept(r)}
                                                >
                                                    {t(
                                                        'machines.mapping.accept',
                                                    )}
                                                </Button>
                                            )}
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setEditing(r)}
                                            >
                                                {t('machines.mapping.set')}
                                            </Button>
                                            {r.status !== 'unmapped' && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => reset(r)}
                                                >
                                                    {t(
                                                        'machines.mapping.reset',
                                                    )}
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {visible.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-center text-muted-foreground"
                                        >
                                            {t('machines.mapping.empty')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
            {editing && (
                <MapDialog
                    key={editing.option_name}
                    row={editing}
                    lines={lines}
                    equipment={equipment}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

MachineOptionMapping.layout = {
    breadcrumbs: [{ title: 'Machines', href: machinesIndex() }],
};
