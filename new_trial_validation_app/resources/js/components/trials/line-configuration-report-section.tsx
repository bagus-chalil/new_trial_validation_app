import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import TrialLineConfigurationReportController from '@/actions/App/Http/Controllers/TrialLineConfigurationReportController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
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
import { Textarea } from '@/components/ui/textarea';
import { cn, formatDate } from '@/lib/utils';

export type ProductionStandardRow = {
    line?: string | null;
    workers?: string | null;
    capacity?: string | null;
    remark?: string | null;
};

export type LineConfigurationRow = {
    no?: string | null;
    equipment?: string | null;
    process?: string | null;
    worker?: string | null;
    trial_status?: string | null;
    remark?: string | null;
};

export type LineConfigurationReportData = {
    id: number;
    version: number;
    report_date: string | null;
    client_name: string | null;
    pic: string | null;
    operator: string | null;
    validation_name: string | null;
    total_qty: string | null;
    setting_qty: string | null;
    pass_qty: string | null;
    ng_qty: string | null;
    capacity_label: string | null;
    production_standard: ProductionStandardRow[] | null;
    line_configuration: LineConfigurationRow[] | null;
    opinion: string | null;
    approved_pie: boolean;
    approved_pie_by: string | null;
    approved_pie_at: string | null;
    approved_pie_user_id: number | null;
    checked_prod: boolean;
    checked_prod_by: string | null;
    checked_prod_at: string | null;
    checked_prod_user_id: number | null;
} | null;

export type LineConfigurationApproverOption = { id: number; label: string };

export type LineConfigurationReportVersion = {
    id: number;
    version: number;
    locked_at: string | null;
    return_prod: boolean;
    return_reason: string | null;
};

export type LineConfigurationReturnNote = {
    reason: string | null;
    by: string | null;
    at: string | null;
} | null;

type StageField = 'approved_pie' | 'checked_prod';

/**
 * Display labels for the two sign-off stages — admin-editable via the Lane
 * Configuration screen (Phase 3 of the RBAC/Team-master redesign) instead of
 * hardcoded JSX strings, so a renamed lane ("Approved (PIE)" →
 * "Approved (Line Head)") shows up here immediately. Always reflects the
 * *live* config, since this section only ever renders the current/editable
 * report — a locked historical version is a download-only PDF that reads
 * its own frozen label snapshot instead (see
 * resources/views/pdf/line-configuration-report-version.blade.php).
 */
export type LineConfigurationLanes = {
    approved_pie: string;
    checked_prod: string;
};

const DEFAULT_LANES: LineConfigurationLanes = {
    approved_pie: 'Approved (PIE)',
    checked_prod: 'Checked (PROD)',
};

function stageFields(
    lanes: LineConfigurationLanes,
): { field: StageField; label: string }[] {
    return [
        { field: 'approved_pie', label: lanes.approved_pie },
        { field: 'checked_prod', label: lanes.checked_prod },
    ];
}

const MIN_RETURN_REASON_WORDS = 5;

function countWords(value: string): number {
    return value.trim() === ''
        ? 0
        : value.trim().split(/\s+/).filter(Boolean).length;
}

/**
 * Total/Setting/PASS/NG are free text in the source Excel (e.g. "General
 * Pcs"), not real numbers — this pulls out the leading numeric portion so NG
 * can still be auto-computed whenever all three actually contain one.
 */
function parseLeadingNumber(value: string): number | null {
    const match = value.match(/-?\d+(\.\d+)?/);

    return match ? parseFloat(match[0]) : null;
}

/** Ports the source Excel's NG formula: `=(Total-Setting-PASS)&" Pcs ("&TEXT((Total-Setting-PASS)/Total,"0%")&")"`. */
function computeNgQty(
    total: string,
    setting: string,
    pass: string,
): string | null {
    const t = parseLeadingNumber(total);
    const s = parseLeadingNumber(setting);
    const p = parseLeadingNumber(pass);

    if (t === null || s === null || p === null) {
        return null;
    }

    const ng = t - s - p;
    const pct = t !== 0 ? Math.round((ng / t) * 100) : 0;

    return `${ng} Pcs (${pct}%)`;
}

type KeyedRow<T> = { key: number; row: T };

function toKeyedRows<T>(
    rows: T[] | null | undefined,
    minRows: number,
    blank: () => T,
): KeyedRow<T>[] {
    const source =
        rows && rows.length > 0 ? rows : Array.from({ length: minRows }, blank);

    return source.map((row, i) => ({ key: i, row }));
}

function blankStandardRow(): ProductionStandardRow {
    return { line: '', workers: '', capacity: '', remark: '' };
}

function blankConfigRow(): LineConfigurationRow {
    return {
        no: '',
        equipment: '',
        process: '',
        worker: '',
        trial_status: '',
        remark: '',
    };
}

export function LineConfigurationReportSection({
    trialId,
    report,
    canEdit,
    locked,
    returnNote,
    versions,
    approvers,
    prodApprovers,
    lanes = DEFAULT_LANES,
    canApprovePie,
    canCheckProd,
    canReturn,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    canEdit: boolean;
    locked: boolean;
    returnNote: LineConfigurationReturnNote;
    versions: LineConfigurationReportVersion[];
    /** Eligible for the 'approved_pie' stage per its current lane config. */
    approvers: LineConfigurationApproverOption[];
    /** Eligible for the 'checked_prod' stage per its current lane config. */
    prodApprovers: LineConfigurationApproverOption[];
    /** Current, admin-editable display labels for the two stages. */
    lanes?: LineConfigurationLanes;
    canApprovePie: boolean;
    canCheckProd: boolean;
    canReturn: boolean;
}) {
    const [dialogOpen, setDialogOpen] = useState(false);

    if (!canEdit && !report) {
        return null;
    }

    return (
        <div className="print:hidden">
            <div className="mb-2 flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold">
                        Line Configuration Report (Production)
                    </h3>
                    {locked && (
                        <Badge variant="outline" className="text-amber-600">
                            Dalam Proses Approval
                        </Badge>
                    )}
                </div>
                {canEdit && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setDialogOpen(true)}
                    >
                        {report ? 'Edit' : 'Buat Line Configuration Report'}
                    </Button>
                )}
            </div>

            {returnNote && (
                <Alert variant="destructive" className="mb-3">
                    <AlertTitle>Dikembalikan untuk Revisi</AlertTitle>
                    <AlertDescription>
                        <span className="whitespace-pre-line">
                            {returnNote.reason}
                        </span>
                        {(returnNote.by || returnNote.at) && (
                            <div className="mt-1 text-xs opacity-80">
                                — {returnNote.by || 'Approver'}
                                {returnNote.at &&
                                    `, ${formatDate(returnNote.at)}`}
                            </div>
                        )}
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardContent className="pt-6">
                    <ReadOnlyLineConfigurationReport
                        trialId={trialId}
                        report={report}
                        lanes={lanes}
                        canApprovePie={canApprovePie}
                        canCheckProd={canCheckProd}
                        canReturn={canReturn}
                    />
                </CardContent>
            </Card>

            {versions.length > 0 && (
                <Card className="mt-3">
                    <CardContent className="pt-6">
                        <h4 className="mb-1 text-sm font-semibold">
                            Riwayat Versi
                        </h4>
                        <p className="mb-3 text-xs text-muted-foreground">
                            Versi yang sudah di-Return dan direvisi lagi
                            terkunci di sini — hanya bisa diunduh, tidak bisa
                            diedit lagi.
                        </p>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Versi</TableHead>
                                    <TableHead>Dikunci Pada</TableHead>
                                    <TableHead>Alasan Return</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {versions.map((v) => (
                                    <TableRow key={v.id}>
                                        <TableCell>v{v.version}</TableCell>
                                        <TableCell>
                                            {formatDate(v.locked_at)}
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            {v.return_reason ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <a
                                                    href={
                                                        TrialLineConfigurationReportController.downloadVersion(
                                                            {
                                                                trial: trialId,
                                                                version:
                                                                    v.version,
                                                            },
                                                        ).url
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    Unduh PDF
                                                </a>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            {canEdit && (
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-5xl lg:max-w-6xl">
                        <DialogHeader>
                            <DialogTitle>
                                Line Configuration Report (Production)
                            </DialogTitle>
                        </DialogHeader>
                        {returnNote && (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Dikembalikan untuk Revisi
                                </AlertTitle>
                                <AlertDescription>
                                    <span className="whitespace-pre-line">
                                        {returnNote.reason}
                                    </span>
                                    {(returnNote.by || returnNote.at) && (
                                        <div className="mt-1 text-xs opacity-80">
                                            — {returnNote.by || 'Approver'}
                                            {returnNote.at &&
                                                `, ${formatDate(returnNote.at)}`}
                                        </div>
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}
                        <EditableLineConfigurationReport
                            trialId={trialId}
                            report={report}
                            approvers={approvers}
                            prodApprovers={prodApprovers}
                            lanes={lanes}
                            onSaved={() => setDialogOpen(false)}
                        />
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}

function EditableLineConfigurationReport({
    trialId,
    report,
    approvers,
    prodApprovers,
    lanes,
    onSaved,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    approvers: LineConfigurationApproverOption[];
    prodApprovers: LineConfigurationApproverOption[];
    lanes: LineConfigurationLanes;
    onSaved: () => void;
}) {
    const standardCounter = useRef(report?.production_standard?.length ?? 2);
    const configCounter = useRef(report?.line_configuration?.length ?? 3);

    const [standardRows, setStandardRows] = useState<
        KeyedRow<ProductionStandardRow>[]
    >(() => toKeyedRows(report?.production_standard, 2, blankStandardRow));
    const [configRows, setConfigRows] = useState<
        KeyedRow<LineConfigurationRow>[]
    >(() => toKeyedRows(report?.line_configuration, 3, blankConfigRow));

    /**
     * Tracked alongside (not instead of) each row's uncontrolled `worker`
     * Input, purely to compute the live "Total Workers" footer — the input
     * itself stays uncontrolled (defaultValue), matching every other
     * array-of-rows field in this form.
     */
    const [workerValues, setWorkerValues] = useState<Record<number, string>>(
        () =>
            Object.fromEntries(
                configRows.map(({ key, row }) => [key, row.worker ?? '']),
            ),
    );
    const totalWorkers = Object.values(workerValues).reduce((sum, value) => {
        const n = parseLeadingNumber(value);

        return n !== null ? sum + n : sum;
    }, 0);

    const approverOptions = approvers.map((a) => ({
        value: String(a.id),
        label: a.label,
    }));
    const prodApproverOptions = prodApprovers.map((a) => ({
        value: String(a.id),
        label: a.label,
    }));
    const [approvedPieUserId, setApprovedPieUserId] = useState(
        report?.approved_pie_user_id ? String(report.approved_pie_user_id) : '',
    );
    const [checkedProdUserId, setCheckedProdUserId] = useState(
        report?.checked_prod_user_id ? String(report.checked_prod_user_id) : '',
    );

    const [totalQty, setTotalQty] = useState(report?.total_qty ?? '');
    const [settingQty, setSettingQty] = useState(report?.setting_qty ?? '');
    const [passQty, setPassQty] = useState(report?.pass_qty ?? '');
    const [ngQty, setNgQty] = useState(report?.ng_qty ?? '');

    function recalcNgQty(total: string, setting: string, pass: string) {
        const computed = computeNgQty(total, setting, pass);

        if (computed !== null) {
            setNgQty(computed);
        }
    }

    return (
        <Form
            {...TrialLineConfigurationReportController.update.form(trialId)}
            onSuccess={onSaved}
        >
            {({ processing, errors }) => (
                <div className="space-y-6">
                    <div className="space-y-4 rounded-md border p-4">
                        <h4 className="text-sm font-semibold">Sign-off</h4>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>{lanes.approved_pie}</Label>
                                <Combobox
                                    options={approverOptions}
                                    value={approvedPieUserId}
                                    onChange={setApprovedPieUserId}
                                    placeholder="Pilih user..."
                                    searchPlaceholder="Cari user..."
                                />
                                <input
                                    type="hidden"
                                    name="approved_pie_user_id"
                                    value={approvedPieUserId}
                                />
                                <SignOffStatusHint
                                    field="approved_pie"
                                    report={report}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>{lanes.checked_prod}</Label>
                                {prodApproverOptions.length === 0 ? (
                                    <p className="text-xs text-destructive">
                                        Belum ada user yang memenuhi tim untuk{' '}
                                        {lanes.checked_prod}. Atur di Access
                                        Rights / Lane Configuration.
                                    </p>
                                ) : (
                                    <Combobox
                                        options={prodApproverOptions}
                                        value={checkedProdUserId}
                                        onChange={setCheckedProdUserId}
                                        placeholder="Pilih user..."
                                        searchPlaceholder="Cari user..."
                                    />
                                )}
                                <input
                                    type="hidden"
                                    name="checked_prod_user_id"
                                    value={checkedProdUserId}
                                />
                                <SignOffStatusHint
                                    field="checked_prod"
                                    report={report}
                                />
                            </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            User yang dipilih akan menerima email, lalu
                            approve/checked sendiri di halaman ini (berjenjang —{' '}
                            {lanes.checked_prod} baru bisa setelah{' '}
                            {lanes.approved_pie} selesai) — tanggal tercatat
                            otomatis saat itu. Begitu salah satu di-assign, form
                            ini terkunci (tidak bisa diedit lagi) sampai
                            di-Return.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_report_date">Date</Label>
                            <Input
                                id="lcr_report_date"
                                type="date"
                                name="report_date"
                                defaultValue={
                                    report?.report_date?.slice(0, 10) ?? ''
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_client_name">Client</Label>
                            <Input
                                id="lcr_client_name"
                                name="client_name"
                                defaultValue={report?.client_name ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_validation_name">
                                Validation
                            </Label>
                            <Input
                                id="lcr_validation_name"
                                name="validation_name"
                                defaultValue={report?.validation_name ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_pic">PIC</Label>
                            <Input
                                id="lcr_pic"
                                name="pic"
                                defaultValue={report?.pic ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_operator">Operator</Label>
                            <Input
                                id="lcr_operator"
                                name="operator"
                                defaultValue={report?.operator ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_capacity_label">
                                Label Kapasitas/Speed
                            </Label>
                            <Input
                                id="lcr_capacity_label"
                                name="capacity_label"
                                placeholder="Capa / PRD Speed (pcs/min)"
                                defaultValue={report?.capacity_label ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_total_qty">Total</Label>
                            <Input
                                id="lcr_total_qty"
                                name="total_qty"
                                placeholder="100 Pcs"
                                value={totalQty}
                                onChange={(e) => {
                                    setTotalQty(e.target.value);
                                    recalcNgQty(
                                        e.target.value,
                                        settingQty,
                                        passQty,
                                    );
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_setting_qty">Setting</Label>
                            <Input
                                id="lcr_setting_qty"
                                name="setting_qty"
                                placeholder="20 Pcs"
                                value={settingQty}
                                onChange={(e) => {
                                    setSettingQty(e.target.value);
                                    recalcNgQty(
                                        totalQty,
                                        e.target.value,
                                        passQty,
                                    );
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_pass_qty">PASS</Label>
                            <Input
                                id="lcr_pass_qty"
                                name="pass_qty"
                                placeholder="70 Pcs"
                                value={passQty}
                                onChange={(e) => {
                                    setPassQty(e.target.value);
                                    recalcNgQty(
                                        totalQty,
                                        settingQty,
                                        e.target.value,
                                    );
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_ng_qty">NG</Label>
                            <Input
                                id="lcr_ng_qty"
                                name="ng_qty"
                                placeholder="10 Pcs (14%)"
                                value={ngQty}
                                onChange={(e) => setNgQty(e.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Otomatis dihitung dari Total − Setting − PASS
                                (bisa diedit manual bila perlu).
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold">
                                Production Standard
                            </h4>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setStandardRows((prev) => [
                                        ...prev,
                                        {
                                            key: standardCounter.current++,
                                            row: blankStandardRow(),
                                        },
                                    ])
                                }
                            >
                                Tambah Baris
                            </Button>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Line</TableHead>
                                    <TableHead>Workers</TableHead>
                                    <TableHead>Kapasitas/Speed</TableHead>
                                    <TableHead>Remark</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {standardRows.map(({ key, row }, index) => (
                                    <TableRow key={key}>
                                        <TableCell>
                                            <Input
                                                name={`production_standard[${index}][line]`}
                                                defaultValue={row.line ?? ''}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`production_standard[${index}][workers]`}
                                                defaultValue={row.workers ?? ''}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`production_standard[${index}][capacity]`}
                                                defaultValue={
                                                    row.capacity ?? ''
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`production_standard[${index}][remark]`}
                                                defaultValue={row.remark ?? ''}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setStandardRows((prev) =>
                                                        prev.filter(
                                                            (r) =>
                                                                r.key !== key,
                                                        ),
                                                    )
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {errors.production_standard && (
                            <p className="text-sm text-destructive">
                                {errors.production_standard}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold">
                                Line Configuration
                            </h4>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const key = configCounter.current++;
                                    setConfigRows((prev) => [
                                        ...prev,
                                        { key, row: blankConfigRow() },
                                    ]);
                                    setWorkerValues((prev) => ({
                                        ...prev,
                                        [key]: '',
                                    }));
                                }}
                            >
                                Tambah Baris
                            </Button>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-14 text-center">
                                        No
                                    </TableHead>
                                    <TableHead>Equipment</TableHead>
                                    <TableHead>Process</TableHead>
                                    <TableHead>Worker</TableHead>
                                    <TableHead>Trial</TableHead>
                                    <TableHead>Remark</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {configRows.map(({ key, row }, index) => (
                                    <TableRow key={key}>
                                        <TableCell className="text-center text-muted-foreground">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`line_configuration[${index}][equipment]`}
                                                defaultValue={
                                                    row.equipment ?? ''
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`line_configuration[${index}][process]`}
                                                defaultValue={row.process ?? ''}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`line_configuration[${index}][worker]`}
                                                defaultValue={row.worker ?? ''}
                                                onChange={(e) =>
                                                    setWorkerValues((prev) => ({
                                                        ...prev,
                                                        [key]: e.target.value,
                                                    }))
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`line_configuration[${index}][trial_status]`}
                                                placeholder="Pass / No Trial"
                                                defaultValue={
                                                    row.trial_status ?? ''
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                name={`line_configuration[${index}][remark]`}
                                                defaultValue={row.remark ?? ''}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    setConfigRows((prev) =>
                                                        prev.filter(
                                                            (r) =>
                                                                r.key !== key,
                                                        ),
                                                    );
                                                    setWorkerValues((prev) => {
                                                        const next = {
                                                            ...prev,
                                                        };
                                                        delete next[key];

                                                        return next;
                                                    });
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {configRows.length > 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="text-right font-semibold"
                                        >
                                            Total Workers
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {totalWorkers}
                                        </TableCell>
                                        <TableCell colSpan={3} />
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        {errors.line_configuration && (
                            <p className="text-sm text-destructive">
                                {errors.line_configuration}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="lcr_opinion">Opinion</Label>
                        <Textarea
                            id="lcr_opinion"
                            name="opinion"
                            rows={4}
                            defaultValue={report?.opinion ?? ''}
                        />
                    </div>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            Simpan Line Configuration Report
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}

function SignOffStatusHint({
    field,
    report,
}: {
    field: StageField;
    report: LineConfigurationReportData;
}) {
    const done = report?.[field] ?? false;

    if (!done) {
        return (
            <p className="text-xs text-muted-foreground">Belum dikonfirmasi.</p>
        );
    }

    const by = report?.[`${field}_by`] ?? null;
    const at = report?.[`${field}_at`] ?? null;

    return (
        <p className="text-xs text-green-600 dark:text-green-400">
            Dikonfirmasi oleh {by || '-'}
            {at && ` pada ${formatDate(at)}`}
        </p>
    );
}

function ReadOnlyLineConfigurationReport({
    trialId,
    report,
    lanes,
    canApprovePie,
    canCheckProd,
    canReturn,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    lanes: LineConfigurationLanes;
    canApprovePie: boolean;
    canCheckProd: boolean;
    canReturn: boolean;
}) {
    if (!report) {
        return (
            <p className="text-muted-foreground">
                Belum ada Line Configuration Report.
            </p>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex flex-col items-end gap-2">
                <span className="text-xs text-muted-foreground">
                    Versi {report.version}
                </span>
                <SignOffSummaryTable
                    trialId={trialId}
                    report={report}
                    lanes={lanes}
                    canApprovePie={canApprovePie}
                    canCheckProd={canCheckProd}
                    canReturn={canReturn}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                {[
                    ['Date', formatDate(report.report_date)],
                    ['Client', report.client_name],
                    ['Validation', report.validation_name],
                    ['PIC', report.pic],
                    ['Operator', report.operator],
                    ['Total', report.total_qty],
                    ['Setting', report.setting_qty],
                    ['PASS', report.pass_qty],
                    ['NG', report.ng_qty],
                ].map(([label, value]) => (
                    <div
                        key={label as string}
                        className="rounded-md border p-3"
                    >
                        <div className="text-xs tracking-wide text-muted-foreground uppercase">
                            {label}
                        </div>
                        <div className="font-medium">{value || '-'}</div>
                    </div>
                ))}
            </div>

            <div>
                <h4 className="mb-2 text-sm font-semibold">
                    Production Standard
                </h4>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Line</TableHead>
                            <TableHead>Workers</TableHead>
                            <TableHead>
                                {report.capacity_label || 'Kapasitas/Speed'}
                            </TableHead>
                            <TableHead>Remark</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(report.production_standard ?? []).map((row, i) => (
                            <TableRow key={i}>
                                <TableCell>{row.line ?? '-'}</TableCell>
                                <TableCell>{row.workers ?? '-'}</TableCell>
                                <TableCell>{row.capacity ?? '-'}</TableCell>
                                <TableCell>{row.remark ?? '-'}</TableCell>
                            </TableRow>
                        ))}
                        {(report.production_standard ?? []).length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="text-center text-muted-foreground"
                                >
                                    Tidak ada data.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div>
                <h4 className="mb-2 text-sm font-semibold">
                    Line Configuration
                </h4>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="text-center">No</TableHead>
                            <TableHead>Equipment</TableHead>
                            <TableHead>Process</TableHead>
                            <TableHead>Worker</TableHead>
                            <TableHead>Trial</TableHead>
                            <TableHead>Remark</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(report.line_configuration ?? []).map((row, i) => (
                            <TableRow key={i}>
                                <TableCell className="text-center text-muted-foreground">
                                    {i + 1}
                                </TableCell>
                                <TableCell>{row.equipment ?? '-'}</TableCell>
                                <TableCell>{row.process ?? '-'}</TableCell>
                                <TableCell>{row.worker ?? '-'}</TableCell>
                                <TableCell>{row.trial_status ?? '-'}</TableCell>
                                <TableCell>{row.remark ?? '-'}</TableCell>
                            </TableRow>
                        ))}
                        {(report.line_configuration ?? []).length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={6}
                                    className="text-center text-muted-foreground"
                                >
                                    Tidak ada data.
                                </TableCell>
                            </TableRow>
                        )}
                        {(report.line_configuration ?? []).length > 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={3}
                                    className="text-right font-semibold"
                                >
                                    Total Workers
                                </TableCell>
                                <TableCell className="font-semibold">
                                    {(report.line_configuration ?? []).reduce(
                                        (sum, row) => {
                                            const n = parseLeadingNumber(
                                                row.worker ?? '',
                                            );

                                            return n !== null ? sum + n : sum;
                                        },
                                        0,
                                    )}
                                </TableCell>
                                <TableCell colSpan={2} />
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {report.opinion && (
                <div>
                    <h4 className="mb-2 text-sm font-semibold">Opinion</h4>
                    <p className="whitespace-pre-line">{report.opinion}</p>
                </div>
            )}
        </div>
    );
}

/**
 * Sign-off box mirroring the source Excel's header table, minus the
 * Return(PROD) column (row 59-62 originally had it as a 4th column) — Return
 * is now its own action (see ReturnDialog below), available next to whichever
 * stage's confirm button is currently active, not a persistent field on
 * every version. Prepared(PIE) just reflects the existing `pic` field.
 *
 * The two real stages are sequential (Approved(PIE) before Checked(PROD) —
 * see App\Policies\TrialLineConfigurationReportPolicy): Checked(PROD) shows
 * a muted "Menunggu Approved (PIE)" placeholder instead of a dash while
 * that's still pending, rather than looking actionable when it isn't yet.
 * A cell shows Approve/Tandai Checked + Return buttons only when the viewer
 * is specifically authorized for that action right now (canApprovePie/
 * canCheckProd/canReturn, all pre-computed server-side) — clicking Approve/
 * Checked stamps the acting user's own name and Carbon::now() server-side
 * (see MarkTrialLineConfigurationReportSignOff), never a value typed into a
 * form field.
 */
function SignOffSummaryTable({
    trialId,
    report,
    lanes,
    canApprovePie,
    canCheckProd,
    canReturn,
}: {
    trialId: number;
    report: NonNullable<LineConfigurationReportData>;
    lanes: LineConfigurationLanes;
    canApprovePie: boolean;
    canCheckProd: boolean;
    canReturn: boolean;
}) {
    const fields = stageFields(lanes);

    return (
        <div className="inline-block overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="text-center whitespace-nowrap">
                            Prepared (PIE)
                        </TableHead>
                        {fields.map(({ field, label }) => (
                            <TableHead
                                key={field}
                                className="text-center whitespace-nowrap"
                            >
                                {label}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell className="text-center align-top">
                            {report.pic || '-'}
                        </TableCell>
                        {fields.map(({ field }) => {
                            const by = report[`${field}_by`] ?? null;
                            const at = report[`${field}_at`] ?? null;
                            const done = report[field] ?? false;
                            const canConfirm =
                                field === 'approved_pie'
                                    ? canApprovePie
                                    : canCheckProd;
                            const waitingOnPreviousStage =
                                field === 'checked_prod' &&
                                !report.approved_pie &&
                                !done;

                            if (waitingOnPreviousStage) {
                                return (
                                    <TableCell
                                        key={field}
                                        className="text-center align-top"
                                    >
                                        <span className="text-xs text-muted-foreground">
                                            Menunggu {lanes.approved_pie}
                                        </span>
                                    </TableCell>
                                );
                            }

                            if (!done && (canConfirm || canReturn)) {
                                const confirmFormProps =
                                    field === 'approved_pie'
                                        ? TrialLineConfigurationReportController.approvePie.form(
                                              trialId,
                                          )
                                        : TrialLineConfigurationReportController.checkProd.form(
                                              trialId,
                                          );

                                return (
                                    <TableCell
                                        key={field}
                                        className="text-center align-top"
                                    >
                                        <div className="flex flex-col items-center gap-1.5">
                                            {canConfirm && (
                                                <Form {...confirmFormProps}>
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {field ===
                                                            'approved_pie'
                                                                ? 'Approve'
                                                                : 'Tandai Checked'}
                                                        </Button>
                                                    )}
                                                </Form>
                                            )}
                                            {canReturn && (
                                                <ReturnDialog
                                                    trialId={trialId}
                                                />
                                            )}
                                        </div>
                                    </TableCell>
                                );
                            }

                            return (
                                <TableCell
                                    key={field}
                                    className="text-center align-top"
                                >
                                    {done ? (
                                        <div className="text-sm font-medium text-green-600 dark:text-green-400">
                                            {by || 'Ya'}
                                            {at && (
                                                <div className="text-[10px] font-normal text-muted-foreground">
                                                    {formatDate(at)}
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            -
                                        </span>
                                    )}
                                </TableCell>
                            );
                        })}
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    );
}

/**
 * Send-back-for-revision action, available to whoever's turn it currently
 * is (canReturn, pre-resolved server-side via
 * TrialLineConfigurationReportPolicy::returnReport() — never gated
 * per-field on the frontend, since only one stage is ever active at once).
 * Requires a reason of at least MIN_RETURN_REASON_WORDS words, enforced both
 * client-side (live counter, disables Submit) and server-side (the
 * authoritative check).
 */
function ReturnDialog({ trialId }: { trialId: number }) {
    const [reason, setReason] = useState('');
    const wordCount = countWords(reason);
    const reasonOk = wordCount >= MIN_RETURN_REASON_WORDS;

    return (
        <ConfirmDialog
            trigger={
                <Button type="button" size="sm" variant="destructive">
                    Return
                </Button>
            }
            title="Return Line Configuration Report"
            description="Kembalikan Line Configuration Report ini untuk direvisi. Versi saat ini akan terkunci sebagai riwayat, dan versi baru akan dibuka untuk revisi."
            confirmLabel="Return"
            confirmVariant="destructive"
            formProps={TrialLineConfigurationReportController.returnReport.form(
                trialId,
            )}
        >
            {({ errors, processing }) => (
                <div className="space-y-2">
                    <Label htmlFor="lcr_return_reason">
                        Alasan Return (minimal {MIN_RETURN_REASON_WORDS} kata)
                    </Label>
                    <Textarea
                        id="lcr_return_reason"
                        name="reason"
                        rows={4}
                        required
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Jelaskan apa yang perlu direvisi..."
                    />
                    <p
                        className={cn(
                            'text-xs',
                            reasonOk
                                ? 'text-muted-foreground'
                                : 'text-destructive',
                        )}
                    >
                        {wordCount} / {MIN_RETURN_REASON_WORDS} kata
                    </p>
                    {errors.reason && (
                        <p className="text-sm text-destructive">
                            {errors.reason}
                        </p>
                    )}
                    {/* Extra guard beyond the live counter above — a real
                        submit is still blocked server-side either way. */}
                    <input
                        type="hidden"
                        name="_client_reason_ok"
                        value={reasonOk ? '1' : ''}
                        disabled={processing}
                    />
                </div>
            )}
        </ConfirmDialog>
    );
}
