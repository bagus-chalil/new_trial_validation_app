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
import InputError from '@/components/input-error';
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
import { useTranslation } from '@/hooks/use-translation';
import type { TranslateFn } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

const TRIAL_STATUS_OPTIONS = ['Pass', 'No Trial'] as const;

/**
 * Lists every validation message for a row table — the backend reports each
 * empty cell under its own key (e.g. `production_standard.0.line`), plus the
 * bare key when the table has no filled rows at all.
 */
function SectionErrors({
    errors,
    prefix,
}: {
    errors: Record<string, string>;
    prefix: string;
}) {
    const messages = Object.entries(errors)
        .filter(([key]) => key === prefix || key.startsWith(`${prefix}.`))
        .map(([, message]) => message);

    if (messages.length === 0) {
        return null;
    }

    return (
        <ul className="list-inside list-disc space-y-0.5 text-sm text-destructive">
            {messages.map((message, index) => (
                <li key={index}>{message}</li>
            ))}
        </ul>
    );
}

/** Display label for a stored trial_status value — the value itself is never translated. */
function trialStatusLabel(t: TranslateFn, status: string): string {
    if (status === 'Pass') {
        return t('line_config.trial_status.pass');
    }

    if (status === 'No Trial') {
        return t('line_config.trial_status.no_trial');
    }

    return status;
}

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
    const { t, formatDate } = useTranslation();
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
                                {t('line_config.title')}
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
                                    {t('line_config.badge.signed_off')}
                                </Badge>
                            ) : locked ? (
                                <Badge
                                    variant="outline"
                                    className="border-amber-500/40 bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"
                                >
                                    {t('line_config.badge.in_approval')}
                                </Badge>
                            ) : (
                                report && (
                                    <Badge
                                        variant="outline"
                                        className="border-slate-400/40 bg-slate-100 text-slate-700 dark:bg-slate-800/50 dark:text-slate-300"
                                    >
                                        {t('line_config.badge.draft')}
                                    </Badge>
                                )
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('line_config.subtitle', {
                                first: lanes.approved_pie,
                                second: lanes.checked_prod,
                            })}
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
                            ? t('line_config.actions.edit')
                            : t('line_config.actions.create')}
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-xl border bg-card">
                {returnNote && (
                    <div className="flex gap-3 border-b bg-red-50/70 px-4 py-3 dark:bg-red-950/20">
                        <RotateCcw className="mt-0.5 size-4 shrink-0 text-red-600" />
                        <div className="min-w-0 space-y-0.5">
                            <p className="text-sm font-medium text-red-700 dark:text-red-400">
                                {t('line_config.return_note.title')}
                            </p>
                            <p className="text-sm wrap-break-word whitespace-pre-line">
                                {returnNote.reason}
                            </p>
                            {(returnNote.by || returnNote.at) && (
                                <p className="text-xs text-muted-foreground">
                                    {[
                                        returnNote.by ||
                                            t(
                                                'line_config.return_note.approver',
                                            ),
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
                                {t('line_config.versions.title')}
                            </h4>
                        </div>
                        <p className="mb-3 text-xs text-muted-foreground">
                            {t('line_config.versions.hint')}
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
                                            {t(
                                                'line_config.versions.locked_at',
                                                {
                                                    date: formatDate(
                                                        v.locked_at,
                                                    ),
                                                },
                                            )}
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
                                            {t(
                                                'line_config.actions.download_pdf',
                                            )}
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
                                {t('line_config.dialog_title')}
                            </DialogTitle>
                        </DialogHeader>
                        {returnNote && (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    {t('line_config.return_note.title')}
                                </AlertTitle>
                                <AlertDescription>
                                    <span className="whitespace-pre-line">
                                        {returnNote.reason}
                                    </span>
                                    {(returnNote.by || returnNote.at) && (
                                        <div className="mt-1 text-xs opacity-80">
                                            —{' '}
                                            {returnNote.by ||
                                                t(
                                                    'line_config.return_note.approver',
                                                )}
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
                            submitted={locked}
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
    submitted,
    onSaved,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    approvers: LineConfigurationApproverOption[];
    prodApprovers: LineConfigurationApproverOption[];
    lanes: LineConfigurationLanes;
    /** Already in the approval chain (only an Admin can still edit it) — no draft option then. */
    submitted: boolean;
    onSaved: () => void;
}) {
    const { t } = useTranslation();
    /**
     * Written straight to the DOM (not via state) by each submit button's
     * onClick, so the value is already in place when Inertia's Form reads
     * the FormData during that same click's submit event.
     */
    const intentRef = useRef<HTMLInputElement>(null);
    const [standardRows, setStandardRows] = useState<
        KeyedRow<ProductionStandardRow>[]
    >(() => toKeyedRows(report?.production_standard, 2, blankStandardRow));
    const [configRows, setConfigRows] = useState<
        KeyedRow<LineConfigurationRow>[]
    >(() => toKeyedRows(report?.line_configuration, 3, blankConfigRow));

    // Seeded from the rows actually rendered (not the saved array's length):
    // a saved-but-empty table (e.g. a draft) still renders the blank default
    // rows keyed 0..n-1, so starting at 0 would hand "Tambah Baris" duplicate
    // React keys and leave stale, mis-numbered rows in the DOM.
    const standardCounter = useRef(standardRows.length);
    const configCounter = useRef(configRows.length);

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
                    <p className="text-sm text-muted-foreground">
                        {t('line_config.validation.summary')}
                    </p>
                    <div className="space-y-4 rounded-md border p-4">
                        <h4 className="text-sm font-semibold">
                            {t('line_config.sign_off.title')}
                        </h4>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>{lanes.approved_pie}</Label>
                                <Combobox
                                    options={approverOptions}
                                    value={approvedPieUserId}
                                    onChange={setApprovedPieUserId}
                                    placeholder={t(
                                        'line_config.sign_off.select_user',
                                    )}
                                    searchPlaceholder={t(
                                        'line_config.sign_off.search_user',
                                    )}
                                />
                                <input
                                    type="hidden"
                                    name="approved_pie_user_id"
                                    value={approvedPieUserId}
                                />
                                <InputError
                                    message={errors.approved_pie_user_id}
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
                                        {t(
                                            'line_config.sign_off.no_eligible_users',
                                            {
                                                lane: lanes.checked_prod,
                                            },
                                        )}
                                    </p>
                                ) : (
                                    <Combobox
                                        options={prodApproverOptions}
                                        value={checkedProdUserId}
                                        onChange={setCheckedProdUserId}
                                        placeholder={t(
                                            'line_config.sign_off.select_user',
                                        )}
                                        searchPlaceholder={t(
                                            'line_config.sign_off.search_user',
                                        )}
                                    />
                                )}
                                <input
                                    type="hidden"
                                    name="checked_prod_user_id"
                                    value={checkedProdUserId}
                                />
                                <InputError
                                    message={errors.checked_prod_user_id}
                                />
                                <SignOffStatusHint
                                    field="checked_prod"
                                    report={report}
                                />
                            </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t('line_config.sign_off.hint', {
                                first: lanes.approved_pie,
                                second: lanes.checked_prod,
                            })}
                        </p>
                        {!submitted && (
                            <p className="text-xs text-muted-foreground">
                                {t('line_config.validation.draft_hint')}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_report_date">
                                {t('line_config.fields.date')}
                            </Label>
                            <Input
                                id="lcr_report_date"
                                type="date"
                                name="report_date"
                                defaultValue={
                                    report?.report_date?.slice(0, 10) ?? ''
                                }
                            />
                            <InputError message={errors.report_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_client_name">
                                {t('line_config.fields.client')}
                            </Label>
                            <Input
                                id="lcr_client_name"
                                name="client_name"
                                defaultValue={report?.client_name ?? ''}
                            />
                            <InputError message={errors.client_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_validation_name">
                                {t('line_config.fields.validation')}
                            </Label>
                            <Input
                                id="lcr_validation_name"
                                name="validation_name"
                                defaultValue={report?.validation_name ?? ''}
                            />
                            <InputError message={errors.validation_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_pic">
                                {t('line_config.fields.pic')}
                            </Label>
                            <Input
                                id="lcr_pic"
                                name="pic"
                                defaultValue={report?.pic ?? ''}
                            />
                            <InputError message={errors.pic} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_operator">
                                {t('line_config.fields.operator')}
                            </Label>
                            <Input
                                id="lcr_operator"
                                name="operator"
                                defaultValue={report?.operator ?? ''}
                            />
                            <InputError message={errors.operator} />
                        </div>
                        {/* Label Speed is hidden from the form per user request;
                            kept as a hidden input so saving doesn't wipe an
                            already-stored value. */}
                        <input
                            type="hidden"
                            name="capacity_label"
                            defaultValue={report?.capacity_label ?? ''}
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_total_qty">
                                {t('line_config.fields.total')}
                            </Label>
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
                            <InputError message={errors.total_qty} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_setting_qty">
                                {t('line_config.fields.setting')}
                            </Label>
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
                            <InputError message={errors.setting_qty} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_pass_qty">
                                {t('line_config.fields.pass')}
                            </Label>
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
                            <InputError message={errors.pass_qty} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_ng_qty">
                                {t('line_config.fields.ng')}
                            </Label>
                            <Input
                                id="lcr_ng_qty"
                                name="ng_qty"
                                placeholder="10 Pcs (14%)"
                                value={ngQty}
                                onChange={(e) => setNgQty(e.target.value)}
                            />
                            <InputError message={errors.ng_qty} />
                            <p className="text-xs text-muted-foreground">
                                {t('line_config.fields.ng_hint')}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold">
                                {t('line_config.sections.production_standard')}
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
                                {t('line_config.actions.add_row')}
                            </Button>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('line_config.columns.line')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.workers')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.capacity')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.remark')}
                                    </TableHead>
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
                                                aria-label={t(
                                                    'line_config.actions.remove_row',
                                                )}
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
                        <SectionErrors
                            errors={errors}
                            prefix="production_standard"
                        />
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold">
                                {t('line_config.sections.line_configuration')}
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
                                {t('line_config.actions.add_row')}
                            </Button>
                        </div>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-14 text-center">
                                        {t('line_config.columns.no')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.equipment')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.process')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.worker')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.trial')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.remark')}
                                    </TableHead>
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
                                                            {trialStatusLabel(
                                                                t,
                                                                option,
                                                            )}
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
                                                aria-label={t(
                                                    'line_config.actions.remove_row',
                                                )}
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
                                            {t('line_config.total_workers')}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            {totalWorkers}
                                        </TableCell>
                                        <TableCell colSpan={3} />
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        <SectionErrors
                            errors={errors}
                            prefix="line_configuration"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="lcr_opinion">
                            {t('line_config.fields.opinion')}
                        </Label>
                        <Textarea
                            id="lcr_opinion"
                            name="opinion"
                            rows={4}
                            defaultValue={report?.opinion ?? ''}
                        />
                        <InputError message={errors.opinion} />
                    </div>

                    <InputError message={errors.intent} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <input
                            ref={intentRef}
                            type="hidden"
                            name="intent"
                            defaultValue="submit"
                        />
                        {!submitted && (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                                onClick={() => {
                                    if (intentRef.current) {
                                        intentRef.current.value = 'draft';
                                    }
                                }}
                            >
                                {t('line_config.actions.save_draft')}
                            </Button>
                        )}
                        <Button
                            type="submit"
                            disabled={processing}
                            onClick={() => {
                                if (intentRef.current) {
                                    intentRef.current.value = 'submit';
                                }
                            }}
                        >
                            {submitted
                                ? t('line_config.actions.save')
                                : t('line_config.actions.submit')}
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
    const { t, formatDate } = useTranslation();
    const done = report?.[field] ?? false;

    if (!done) {
        return (
            <p className="text-xs text-muted-foreground">
                {t('line_config.sign_off.not_confirmed')}
            </p>
        );
    }

    const by = report?.[`${field}_by`] ?? null;
    const at = report?.[`${field}_at`] ?? null;

    return (
        <p className="text-xs text-green-600 dark:text-green-400">
            {at
                ? t('line_config.sign_off.confirmed_by_at', {
                      name: by || '-',
                      date: formatDate(at),
                  })
                : t('line_config.sign_off.confirmed_by', { name: by || '-' })}
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
    const { t, formatDate } = useTranslation();

    if (!report) {
        return (
            <p className="p-6 text-center text-sm text-muted-foreground">
                {t('line_config.empty')}
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
                        [
                            t('line_config.fields.date'),
                            formatDate(report.report_date),
                        ],
                        [t('line_config.fields.client'), report.client_name],
                        [
                            t('line_config.fields.validation'),
                            report.validation_name,
                        ],
                        [t('line_config.fields.pic'), report.pic],
                        [t('line_config.fields.operator'), report.operator],
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
                        {
                            label: t('line_config.fields.total'),
                            value: report.total_qty,
                            tone: '',
                        },
                        {
                            label: t('line_config.fields.setting'),
                            value: report.setting_qty,
                            tone: '',
                        },
                        {
                            label: t('line_config.fields.pass'),
                            value: report.pass_qty,
                            tone: 'text-green-700 dark:text-green-400',
                        },
                        {
                            label: t('line_config.fields.ng'),
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
                        {t('line_config.sections.production_standard')}
                    </h4>
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader className="bg-muted/50">
                                <TableRow>
                                    <TableHead>
                                        {t('line_config.columns.line')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.workers')}
                                    </TableHead>
                                    <TableHead>
                                        {report.capacity_label ||
                                            t('line_config.columns.capacity')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.remark')}
                                    </TableHead>
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
                                            {t('line_config.no_data')}
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
                            {t('line_config.sections.line_configuration')}
                        </h4>
                        {lineRows.length > 0 && (
                            <span className="text-xs text-muted-foreground">
                                {t('line_config.total_workers_inline')}{' '}
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
                                        {t('line_config.columns.no')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.equipment')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.process')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.worker')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.trial')}
                                    </TableHead>
                                    <TableHead>
                                        {t('line_config.columns.remark')}
                                    </TableHead>
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
                                                {trialStatusLabel(
                                                    t,
                                                    row.trial_status ||
                                                        'No Trial',
                                                )}
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
                                            {t('line_config.no_data')}
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
                    <h4 className="mb-1 text-sm font-semibold">
                        {t('line_config.fields.opinion')}
                    </h4>
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
    const { t, formatDate } = useTranslation();
    const stages: StageView[] = [
        {
            key: 'prepared',
            label: t('line_config.stage.prepared'),
            state: report.pic ? 'done' : 'idle',
            detail: report.pic || t('line_config.stage.not_filled'),
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
            let detail = t('line_config.stage.not_assigned');

            if (done) {
                state = 'done';
                detail =
                    report[`${field}_by`] || t('line_config.stage.confirmed');
            } else if (waitingOnPrevious) {
                detail = t('line_config.stage.waiting_for', {
                    name: lanes.approved_pie,
                });
            } else if (canConfirm || canReturn) {
                state = 'action';
                detail = t('line_config.stage.awaiting_you');
            } else if (assignedUser) {
                state = 'waiting';
                detail = t('line_config.stage.waiting_for', {
                    name: assignedUser.name,
                });
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
                                {t('line_config.stage.assigned_to', {
                                    name: stage.assignee,
                                })}
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
    const { t } = useTranslation();
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
        field === 'approved_pie'
            ? t('line_config.actions.approve')
            : t('line_config.actions.mark_checked');
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
                        {t('line_config.action_panel.title', { lane: label })}
                    </h4>
                    <p className="text-xs text-muted-foreground">
                        {t('line_config.action_panel.description', {
                            lane: label,
                        })}
                    </p>
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="lcr_sign_off_comment" className="sr-only">
                    {t('line_config.action_panel.comment')}
                </Label>
                <Textarea
                    id="lcr_sign_off_comment"
                    rows={3}
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder={t(
                        'line_config.action_panel.comment_placeholder',
                    )}
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
                    {t('line_config.action_panel.min_words', {
                        count: MIN_RETURN_REASON_WORDS,
                    })}{' '}
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
                                        {t('line_config.actions.return')}
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
