import { Form } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    ClipboardList,
    Clock,
    Download,
    History,
    MessageSquare,
    Pencil,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import TrialLineConfigurationReportController from '@/actions/App/Http/Controllers/TrialLineConfigurationReportController';
import { Combobox } from '@/components/combobox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn, formatDate } from '@/lib/utils';

const TRIAL_STATUS_OPTIONS = ['Pass', 'No Trial'] as const;

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
    approved_pie_comment: string | null;
    checked_prod: boolean;
    checked_prod_by: string | null;
    checked_prod_at: string | null;
    checked_prod_user_id: number | null;
    checked_prod_comment: string | null;
    approved_pie_user: { id: number; name: string } | null;
    checked_prod_user: { id: number; name: string } | null;
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

    const fullySignedOff = Boolean(
        report?.approved_pie && report?.checked_prod,
    );

    return (
        <section className="space-y-3 print:hidden">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                        <ClipboardList className="size-4" />
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="text-base font-semibold">
                                Line Configuration Report
                            </h3>
                            {report && (
                                <Badge
                                    variant="outline"
                                    className="tabular-nums"
                                >
                                    v{report.version}
                                </Badge>
                            )}
                            {fullySignedOff ? (
                                <Badge
                                    variant="outline"
                                    className="border-green-600/30 bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400"
                                >
                                    Selesai Sign-off
                                </Badge>
                            ) : (
                                locked && (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-500/40 bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"
                                    >
                                        Dalam Proses Approval
                                    </Badge>
                                )
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Production — konfigurasi line, standar produksi, dan
                            sign-off {lanes.approved_pie} &rarr;{' '}
                            {lanes.checked_prod}.
                        </p>
                    </div>
                </div>
                {canEdit && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setDialogOpen(true)}
                    >
                        <Pencil className="size-3.5" />
                        {report
                            ? 'Edit Report'
                            : 'Buat Line Configuration Report'}
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border bg-card">
                {returnNote && (
                    <div className="flex gap-3 border-b bg-red-50/70 px-4 py-3 dark:bg-red-950/20">
                        <RotateCcw className="mt-0.5 size-4 shrink-0 text-red-600" />
                        <div className="min-w-0 space-y-0.5">
                            <p className="text-sm font-medium text-red-700 dark:text-red-400">
                                Dikembalikan untuk Revisi
                            </p>
                            <p className="text-sm wrap-break-word whitespace-pre-line">
                                {returnNote.reason}
                            </p>
                            {(returnNote.by || returnNote.at) && (
                                <p className="text-xs text-muted-foreground">
                                    {[
                                        returnNote.by || 'Approver',
                                        returnNote.at &&
                                            formatDate(returnNote.at),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <ReadOnlyLineConfigurationReport
                    trialId={trialId}
                    report={report}
                    lanes={lanes}
                    canApprovePie={canApprovePie}
                    canCheckProd={canCheckProd}
                    canReturn={canReturn}
                />

                {versions.length > 0 && (
                    <div className="border-t p-4">
                        <div className="mb-1 flex items-center gap-2">
                            <History className="size-4 text-muted-foreground" />
                            <h4 className="text-sm font-semibold">
                                Riwayat Versi
                            </h4>
                        </div>
                        <p className="mb-3 text-xs text-muted-foreground">
                            Versi yang sudah di-Return terkunci di sini — hanya
                            bisa diunduh, tidak bisa diedit lagi.
                        </p>
                        <ul className="divide-y rounded-lg border">
                            {versions.map((v) => (
                                <li
                                    key={v.id}
                                    className="flex flex-wrap items-center gap-3 px-3 py-2"
                                >
                                    <Badge
                                        variant="secondary"
                                        className="tabular-nums"
                                    >
                                        v{v.version}
                                    </Badge>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm">
                                            {v.return_reason ?? '-'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Dikunci {formatDate(v.locked_at)}
                                        </p>
                                    </div>
                                    <Button asChild variant="ghost" size="sm">
                                        <a
                                            href={
                                                TrialLineConfigurationReportController.downloadVersion(
                                                    {
                                                        trial: trialId,
                                                        version: v.version,
                                                    },
                                                ).url
                                            }
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <Download className="size-3.5" />
                                            PDF
                                        </a>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
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
        </section>
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

    /**
     * Controlled per-row state for the Trial column's Pass/No Trial toggle
     * (replacing free text — legacy placeholder was already "Pass / No
     * Trial", just never enforced). Always one of the two real option
     * strings, never '' — a Radix single-select ToggleGroup deselects
     * (falls back to '') when you click its *already-active* item, so if ''
     * were only ever visually papered over as "No Trial" via a `|| 'No
     * Trial'` display fallback, clicking the already-highlighted "No Trial"
     * button would toggle it off and immediately re-collapse back to the
     * same-looking fallback — reading as completely unresponsive. Keeping
     * the real value always resolved avoids that dead click entirely. The
     * controller's dropBlankRows() is told to ignore this key (alongside
     * `no`) so an otherwise fully-empty "Tambah Baris" row still gets
     * dropped despite always carrying a non-blank default here.
     */
    const [trialStatusValues, setTrialStatusValues] = useState<
        Record<number, string>
    >(() =>
        Object.fromEntries(
            configRows.map(({ key, row }) => [
                key,
                row.trial_status || 'No Trial',
            ]),
        ),
    );

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
                                Label Speed
                            </Label>
                            <Input
                                id="lcr_capacity_label"
                                name="capacity_label"
                                placeholder="Capa / PRD (pcs/min)"
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
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                step={1}
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
                                    setTrialStatusValues((prev) => ({
                                        ...prev,
                                        [key]: 'No Trial',
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
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                step={1}
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
                                            <ToggleGroup
                                                type="single"
                                                variant="outline"
                                                size="sm"
                                                value={trialStatusValues[key]}
                                                onValueChange={(value) => {
                                                    // Radix reports '' when
                                                    // the *already-active*
                                                    // item is clicked again
                                                    // (its deselect
                                                    // behavior) — ignored so
                                                    // exactly one of the two
                                                    // options always stays
                                                    // selected, matching
                                                    // radio-button semantics
                                                    // rather than leaving
                                                    // the row's Trial status
                                                    // unset.
                                                    if (!value) {
                                                        return;
                                                    }

                                                    setTrialStatusValues(
                                                        (prev) => ({
                                                            ...prev,
                                                            [key]: value,
                                                        }),
                                                    );
                                                }}
                                            >
                                                {TRIAL_STATUS_OPTIONS.map(
                                                    (option) => (
                                                        <ToggleGroupItem
                                                            key={option}
                                                            value={option}
                                                            className={cn(
                                                                'text-xs font-medium',
                                                                option ===
                                                                    'Pass' &&
                                                                    'data-[state=on]:border-emerald-600 data-[state=on]:bg-emerald-600 data-[state=on]:text-white dark:data-[state=on]:border-emerald-500 dark:data-[state=on]:bg-emerald-500',
                                                                option ===
                                                                    'No Trial' &&
                                                                    'data-[state=on]:border-slate-600 data-[state=on]:bg-slate-600 data-[state=on]:text-white dark:data-[state=on]:border-slate-500 dark:data-[state=on]:bg-slate-500',
                                                            )}
                                                        >
                                                            {option}
                                                        </ToggleGroupItem>
                                                    ),
                                                )}
                                            </ToggleGroup>
                                            <input
                                                type="hidden"
                                                name={`line_configuration[${index}][trial_status]`}
                                                value={trialStatusValues[key]}
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
                                                    setTrialStatusValues(
                                                        (prev) => {
                                                            const next = {
                                                                ...prev,
                                                            };
                                                            delete next[key];

                                                            return next;
                                                        },
                                                    );
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
            <p className="p-6 text-center text-sm text-muted-foreground">
                Belum ada Line Configuration Report.
            </p>
        );
    }

    const lineRows = report.line_configuration ?? [];
    const standardRows = report.production_standard ?? [];
    const totalWorkers = lineRows.reduce((sum, row) => {
        const n = parseLeadingNumber(row.worker ?? '');

        return n !== null ? sum + n : sum;
    }, 0);

    return (
        <>
            <div className="border-b p-4">
                <SignOffFlow
                    report={report}
                    lanes={lanes}
                    canApprovePie={canApprovePie}
                    canCheckProd={canCheckProd}
                    canReturn={canReturn}
                />
            </div>

            <SignOffActionPanel
                trialId={trialId}
                report={report}
                lanes={lanes}
                canApprovePie={canApprovePie}
                canCheckProd={canCheckProd}
                canReturn={canReturn}
            />

            <div className="space-y-4 border-b p-4">
                <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-5">
                    {[
                        ['Date', formatDate(report.report_date)],
                        ['Client', report.client_name],
                        ['Validation', report.validation_name],
                        ['PIC', report.pic],
                        ['Operator', report.operator],
                    ].map(([label, value]) => (
                        <div key={label} className="min-w-0">
                            <dt className="text-xs text-muted-foreground">
                                {label}
                            </dt>
                            <dd
                                className="truncate text-sm font-medium"
                                title={value || undefined}
                            >
                                {value || '-'}
                            </dd>
                        </div>
                    ))}
                </dl>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {[
                        { label: 'Total', value: report.total_qty, tone: '' },
                        {
                            label: 'Setting',
                            value: report.setting_qty,
                            tone: '',
                        },
                        {
                            label: 'PASS',
                            value: report.pass_qty,
                            tone: 'text-green-700 dark:text-green-400',
                        },
                        {
                            label: 'NG',
                            value: report.ng_qty,
                            tone: 'text-red-600 dark:text-red-400',
                        },
                    ].map(({ label, value, tone }) => (
                        <div
                            key={label}
                            className="rounded-lg bg-muted/50 px-3 py-2"
                        >
                            <div className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                {label}
                            </div>
                            <div
                                className={cn(
                                    'text-lg font-semibold tabular-nums',
                                    tone,
                                )}
                            >
                                {value || '-'}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="space-y-4 p-4">
                <div className="min-w-0">
                    <h4 className="mb-2 text-sm font-semibold">
                        Production Standard
                    </h4>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader className="bg-muted/50">
                                <TableRow>
                                    <TableHead>Line</TableHead>
                                    <TableHead>Workers</TableHead>
                                    <TableHead>
                                        {report.capacity_label ||
                                            'Kapasitas/Speed'}
                                    </TableHead>
                                    <TableHead>Remark</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {standardRows.map((row, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{row.line ?? '-'}</TableCell>
                                        <TableCell>
                                            {row.workers ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.capacity ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.remark ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {standardRows.length === 0 && (
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
                </div>

                <div className="min-w-0">
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h4 className="text-sm font-semibold">
                            Line Configuration
                        </h4>
                        {lineRows.length > 0 && (
                            <span className="text-xs text-muted-foreground">
                                Total workers:{' '}
                                <span className="font-semibold text-foreground tabular-nums">
                                    {totalWorkers}
                                </span>
                            </span>
                        )}
                    </div>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader className="bg-muted/50">
                                <TableRow>
                                    <TableHead className="w-10 text-center">
                                        No
                                    </TableHead>
                                    <TableHead>Equipment</TableHead>
                                    <TableHead>Process</TableHead>
                                    <TableHead>Worker</TableHead>
                                    <TableHead>Trial</TableHead>
                                    <TableHead>Remark</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lineRows.map((row, i) => (
                                    <TableRow key={i}>
                                        <TableCell className="text-center text-muted-foreground">
                                            {i + 1}
                                        </TableCell>
                                        <TableCell>
                                            {row.equipment ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.process ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {row.worker ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={
                                                    row.trial_status === 'Pass'
                                                        ? 'border-green-600/30 bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400'
                                                        : 'text-muted-foreground'
                                                }
                                            >
                                                {row.trial_status || 'No Trial'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {row.remark ?? '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {lineRows.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-center text-muted-foreground"
                                        >
                                            Tidak ada data.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            {report.opinion && (
                <div className="border-t p-4">
                    <h4 className="mb-1 text-sm font-semibold">Opinion</h4>
                    <p className="text-sm wrap-break-word whitespace-pre-line text-foreground/90">
                        {report.opinion}
                    </p>
                </div>
            )}
        </>
    );
}

type StageState = 'done' | 'action' | 'waiting' | 'idle';

type StageView = {
    key: string;
    label: string;
    state: StageState;
    detail: string;
    assignee: string | null;
    at: string | null;
    comment: string | null;
};

/**
 * The three sign-off stages as a left-to-right flow — Prepared (just the
 * report's `pic`), then the two sequential real stages (Approved(PIE) before
 * Checked(PROD), see App\Policies\TrialLineConfigurationReportPolicy). Each
 * tile carries its own status, signer, timestamp and optional sign-off
 * comment inline, replacing the old right-floated compact table plus its
 * separate "Catatan Sign-off" box (which left a large empty area beside the
 * table). The Approve/Tandai Checked/Return controls stay in
 * SignOffActionPanel below; a tile only reads "Menunggu tindakan Anda" when
 * the viewer is authorized for that stage right now (canApprovePie/
 * canCheckProd/canReturn, all pre-computed server-side). Signer name/time are
 * stamped server-side (see MarkTrialLineConfigurationReportSignOff), never
 * typed into a form.
 */
function SignOffFlow({
    report,
    lanes,
    canApprovePie,
    canCheckProd,
    canReturn,
}: {
    report: NonNullable<LineConfigurationReportData>;
    lanes: LineConfigurationLanes;
    canApprovePie: boolean;
    canCheckProd: boolean;
    canReturn: boolean;
}) {
    const stages: StageView[] = [
        {
            key: 'prepared',
            label: 'Prepared (PIE)',
            state: report.pic ? 'done' : 'idle',
            detail: report.pic || 'Belum diisi',
            assignee: null,
            at: null,
            comment: null,
        },
        ...stageFields(lanes).map(({ field, label }): StageView => {
            const done = report[field] ?? false;
            const assignedUser = report[`${field}_user`] ?? null;
            const canConfirm =
                field === 'approved_pie' ? canApprovePie : canCheckProd;
            const waitingOnPrevious =
                field === 'checked_prod' && !report.approved_pie && !done;

            let state: StageState = 'idle';
            let detail = 'Belum ditentukan';

            if (done) {
                state = 'done';
                detail = report[`${field}_by`] || 'Dikonfirmasi';
            } else if (waitingOnPrevious) {
                detail = `Menunggu ${lanes.approved_pie}`;
            } else if (canConfirm || canReturn) {
                state = 'action';
                detail = 'Menunggu tindakan Anda';
            } else if (assignedUser) {
                state = 'waiting';
                detail = `Menunggu ${assignedUser.name}`;
            }

            return {
                key: field,
                label,
                state,
                detail,
                assignee: !done && assignedUser ? assignedUser.name : null,
                at: done ? (report[`${field}_at`] ?? null) : null,
                comment: done ? (report[`${field}_comment`] ?? null) : null,
            };
        }),
    ];

    return (
        <ol className="grid gap-3 sm:grid-cols-3">
            {stages.map((stage, index) => (
                <li
                    key={stage.key}
                    className={cn(
                        'flex gap-3 rounded-lg border p-3',
                        stage.state === 'done' &&
                            'border-green-600/25 bg-green-50/50 dark:bg-green-950/15',
                        stage.state === 'action' &&
                            'border-amber-500/50 bg-amber-50/60 dark:bg-amber-950/20',
                    )}
                >
                    <StageIcon state={stage.state} step={index + 1} />
                    <div className="min-w-0 flex-1">
                        <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            {stage.label}
                        </p>
                        <p
                            className={cn(
                                'truncate text-sm font-medium',
                                stage.state === 'action' &&
                                    'text-amber-700 dark:text-amber-400',
                                (stage.state === 'idle' ||
                                    stage.state === 'waiting') &&
                                    'font-normal text-muted-foreground',
                            )}
                            title={stage.detail}
                        >
                            {stage.detail}
                        </p>
                        {stage.at && (
                            <p className="text-xs text-muted-foreground">
                                {formatDate(stage.at)}
                            </p>
                        )}
                        {stage.state === 'action' && stage.assignee && (
                            <p className="text-xs text-muted-foreground">
                                Ditugaskan ke {stage.assignee}
                            </p>
                        )}
                        {stage.comment && (
                            <p className="mt-2 flex gap-1.5 rounded-md bg-background/80 px-2 py-1.5 text-xs wrap-break-word whitespace-pre-line text-foreground/80">
                                <MessageSquare className="mt-0.5 size-3 shrink-0 text-muted-foreground" />
                                <span className="min-w-0">{stage.comment}</span>
                            </p>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}

function StageIcon({ state, step }: { state: StageState; step: number }) {
    if (state === 'done') {
        return (
            <CircleCheck className="size-5 shrink-0 text-green-600 dark:text-green-400" />
        );
    }

    if (state === 'action') {
        return (
            <CircleAlert className="size-5 shrink-0 text-amber-600 dark:text-amber-400" />
        );
    }

    if (state === 'waiting') {
        return <Clock className="size-5 shrink-0 text-muted-foreground" />;
    }

    return (
        <span className="flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-semibold text-muted-foreground">
            {step}
        </span>
    );
}

/**
 * The actionable counterpart to SignOffFlow's "Menunggu tindakan Anda" tile —
 * a full-width amber band with a real-sized comment box and the actual
 * Approve/Tandai Checked + Return controls. Resolves to at most one stage at
 * a time (the two are sequential — see
 * TrialLineConfigurationReport::currentApprovalStage() — and canApprovePie/
 * canCheckProd/canReturn are all pre-scoped server-side to whichever stage is
 * currently active for *this* viewer). Renders nothing once neither stage is
 * actionable for the current viewer.
 *
 * The comment box is a *single* shared field: its text becomes the optional
 * Approve/Tandai Checked `comment`, or the Return `reason` if Return is
 * submitted instead (each button belongs to its own real `<Form>` posting to
 * its own route; the Textarea sits outside both and is mirrored into each via
 * a hidden input). Return has no confirmation popup — per the user's explicit
 * ask — and is gated by the MIN_RETURN_REASON_WORDS-word minimum (client-side
 * the button stays disabled below it; ReturnLineConfigurationReportRequest is
 * still the authoritative server-side check).
 */
function SignOffActionPanel({
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
    const [note, setNote] = useState('');
    const wordCount = countWords(note);
    const reasonOk = wordCount >= MIN_RETURN_REASON_WORDS;

    const field: StageField | null =
        canApprovePie && !report.approved_pie
            ? 'approved_pie'
            : canCheckProd && !report.checked_prod
              ? 'checked_prod'
              : null;

    if (!field) {
        return null;
    }

    const label = lanes[field];
    const confirmLabel =
        field === 'approved_pie' ? 'Approve' : 'Tandai Checked';
    const confirmFormProps =
        field === 'approved_pie'
            ? TrialLineConfigurationReportController.approvePie.form(trialId)
            : TrialLineConfigurationReportController.checkProd.form(trialId);
    const returnFormProps =
        TrialLineConfigurationReportController.returnReport.form(trialId);

    return (
        <div className="space-y-3 border-b border-l-4 border-l-amber-500 bg-amber-50/50 p-4 dark:bg-amber-950/15">
            <div className="flex items-start gap-2">
                <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                <div>
                    <h4 className="text-sm font-semibold">
                        Tindakan Diperlukan: {label}
                    </h4>
                    <p className="text-xs text-muted-foreground">
                        Konfirmasi {label} untuk report ini, atau kembalikan
                        untuk revisi.
                    </p>
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="lcr_sign_off_comment" className="sr-only">
                    Komentar
                </Label>
                <Textarea
                    id="lcr_sign_off_comment"
                    rows={3}
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="Komentar (opsional untuk konfirmasi, wajib untuk Return)..."
                    className="bg-background"
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <p
                    className={cn(
                        'text-xs',
                        reasonOk
                            ? 'text-muted-foreground'
                            : 'text-amber-700 dark:text-amber-400',
                    )}
                >
                    Return butuh minimal {MIN_RETURN_REASON_WORDS} kata —{' '}
                    <span className="tabular-nums">
                        {wordCount}/{MIN_RETURN_REASON_WORDS}
                    </span>
                </p>
                <div className="flex flex-wrap items-start gap-2">
                    {canReturn && (
                        <Form {...returnFormProps}>
                            {({ processing, errors }) => (
                                <div className="flex flex-col items-end gap-1">
                                    <input
                                        type="hidden"
                                        name="reason"
                                        value={note}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="border-destructive/40 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        disabled={processing || !reasonOk}
                                    >
                                        <RotateCcw className="size-4" />
                                        Return
                                    </Button>
                                    {errors.reason && (
                                        <p className="text-xs text-destructive">
                                            {errors.reason}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Form>
                    )}
                    <Form {...confirmFormProps}>
                        {({ processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="comment"
                                    value={note}
                                />
                                <Button type="submit" disabled={processing}>
                                    <CircleCheck className="size-4" />
                                    {confirmLabel}
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </div>
    );
}
