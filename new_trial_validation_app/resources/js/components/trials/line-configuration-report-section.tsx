import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import TrialLineConfigurationReportController from '@/actions/App/Http/Controllers/TrialLineConfigurationReportController';
import { Combobox } from '@/components/combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
    return_prod: boolean;
    return_prod_by: string | null;
    return_prod_at: string | null;
} | null;

export type LineConfigurationApproverOption = { id: number; label: string };

export type LineConfigurationReportVersion = {
    id: number;
    version: number;
    locked_at: string | null;
    return_prod: boolean;
};

type ApprovalField = 'approved_pie' | 'checked_prod' | 'return_prod';

const APPROVAL_FIELDS: {
    field: ApprovalField;
    label: string;
    /** Return(PROD) being checked signals a problem, not a sign-off — badge reads red instead of green. */
    negative?: boolean;
}[] = [
    { field: 'approved_pie', label: 'Approved (PIE)' },
    { field: 'checked_prod', label: 'Checked (PROD)' },
    { field: 'return_prod', label: 'Return (PROD)', negative: true },
];

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
    versions,
    approvers,
    canApprovePie,
    canCheckProd,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    canEdit: boolean;
    versions: LineConfigurationReportVersion[];
    approvers: LineConfigurationApproverOption[];
    canApprovePie: boolean;
    canCheckProd: boolean;
}) {
    const [dialogOpen, setDialogOpen] = useState(false);

    if (!canEdit && !report) {
        return null;
    }

    return (
        <div className="print:hidden">
            <div className="mb-2 flex items-center justify-between">
                <h3 className="text-base font-semibold">
                    Line Configuration Report (Production)
                </h3>
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
            <Card>
                <CardContent className="pt-6">
                    <ReadOnlyLineConfigurationReport
                        trialId={trialId}
                        report={report}
                        canApprovePie={canApprovePie}
                        canCheckProd={canCheckProd}
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
                        <EditableLineConfigurationReport
                            trialId={trialId}
                            report={report}
                            approvers={approvers}
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
    onSaved,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    approvers: LineConfigurationApproverOption[];
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

    const approverOptions = approvers.map((a) => ({
        value: String(a.id),
        label: a.label,
    }));
    const [approvedPieUserId, setApprovedPieUserId] = useState(
        report?.approved_pie_user_id ? String(report.approved_pie_user_id) : '',
    );
    const [checkedProdUserId, setCheckedProdUserId] = useState(
        report?.checked_prod_user_id ? String(report.checked_prod_user_id) : '',
    );

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
                                <Label>Approved (PIE)</Label>
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
                                <Label>Checked (PROD)</Label>
                                <Combobox
                                    options={approverOptions}
                                    value={checkedProdUserId}
                                    onChange={setCheckedProdUserId}
                                    placeholder="Pilih user..."
                                    searchPlaceholder="Cari user..."
                                />
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
                            approve/checked sendiri di halaman ini — tanggal
                            tercatat otomatis saat itu.
                        </p>

                        <div className="grid gap-2 sm:w-72">
                            <Label>Return (PROD)</Label>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="lcr_return_prod"
                                    name="return_prod"
                                    value="1"
                                    defaultChecked={
                                        report?.return_prod ?? false
                                    }
                                />
                                <Label
                                    htmlFor="lcr_return_prod"
                                    className="font-normal"
                                >
                                    Dikembalikan untuk revisi
                                </Label>
                            </div>
                            <Input
                                name="return_prod_by"
                                placeholder="Nama"
                                defaultValue={report?.return_prod_by ?? ''}
                            />
                            <Input
                                type="date"
                                name="return_prod_at"
                                defaultValue={
                                    report?.return_prod_at?.slice(0, 10) ?? ''
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Mencentang Return (dari kondisi belum
                                tercentang) langsung mengunci versi ini sebagai
                                riwayat (lihat Riwayat Versi di bawah) dan
                                membuka versi baru untuk revisi selanjutnya.
                            </p>
                        </div>
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
                                defaultValue={report?.total_qty ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_setting_qty">Setting</Label>
                            <Input
                                id="lcr_setting_qty"
                                name="setting_qty"
                                placeholder="20 Pcs"
                                defaultValue={report?.setting_qty ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_pass_qty">PASS</Label>
                            <Input
                                id="lcr_pass_qty"
                                name="pass_qty"
                                placeholder="70 Pcs"
                                defaultValue={report?.pass_qty ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="lcr_ng_qty">NG</Label>
                            <Input
                                id="lcr_ng_qty"
                                name="ng_qty"
                                placeholder="10 Pcs (14%)"
                                defaultValue={report?.ng_qty ?? ''}
                            />
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
                                onClick={() =>
                                    setConfigRows((prev) => [
                                        ...prev,
                                        {
                                            key: configCounter.current++,
                                            row: blankConfigRow(),
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
                                    <TableHead className="w-14">No</TableHead>
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
                                        <TableCell>
                                            <Input
                                                name={`line_configuration[${index}][no]`}
                                                defaultValue={
                                                    row.no ?? String(index + 1)
                                                }
                                            />
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
                                                onClick={() =>
                                                    setConfigRows((prev) =>
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
    field: 'approved_pie' | 'checked_prod';
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
    canApprovePie,
    canCheckProd,
}: {
    trialId: number;
    report: LineConfigurationReportData;
    canApprovePie: boolean;
    canCheckProd: boolean;
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
                    canApprovePie={canApprovePie}
                    canCheckProd={canCheckProd}
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
                            <TableHead>No</TableHead>
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
                                <TableCell>{row.no ?? i + 1}</TableCell>
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
 * Sign-off box mirroring the source Excel's header table (row 59-62:
 * "Prepared(PIE) | Approved(PIE) | Checked(PROD) | Return(PROD)").
 * Prepared(PIE) just reflects the existing `pic` field.
 *
 * Approved(PIE)/Checked(PROD) show an inline Approve/Tandai Checked button
 * instead of a dash when the viewer is the specifically assigned user (or
 * Admin) and it isn't done yet (see
 * App\Policies\TrialLineConfigurationReportPolicy) — clicking it stamps
 * their own name and Carbon::now() server-side (see
 * MarkTrialLineConfigurationReportSignOff), never a value typed into a
 * form field. Return(PROD) has no assignment/action of its own — it stays a
 * plain freely-editable checkbox+name+date on the edit form, so it never
 * shows an action button here.
 */
function SignOffSummaryTable({
    trialId,
    report,
    canApprovePie,
    canCheckProd,
}: {
    trialId: number;
    report: NonNullable<LineConfigurationReportData>;
    canApprovePie: boolean;
    canCheckProd: boolean;
}) {
    return (
        <div className="inline-block overflow-x-auto rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="text-center whitespace-nowrap">
                            Prepared (PIE)
                        </TableHead>
                        {APPROVAL_FIELDS.map(({ field, label }) => (
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
                        {APPROVAL_FIELDS.map(({ field, negative }) => {
                            const by = report[`${field}_by`] ?? null;
                            const at = report[`${field}_at`] ?? null;
                            const done = report[field] ?? false;
                            const canAct =
                                field === 'approved_pie'
                                    ? canApprovePie
                                    : field === 'checked_prod'
                                      ? canCheckProd
                                      : false;

                            if (!done && canAct) {
                                const formProps =
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
                                        <Form {...formProps}>
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    {field === 'approved_pie'
                                                        ? 'Approve'
                                                        : 'Tandai Checked'}
                                                </Button>
                                            )}
                                        </Form>
                                    </TableCell>
                                );
                            }

                            return (
                                <TableCell
                                    key={field}
                                    className="text-center align-top"
                                >
                                    {done ? (
                                        <div
                                            className={cn(
                                                'text-sm font-medium',
                                                negative
                                                    ? 'text-red-600 dark:text-red-400'
                                                    : 'text-green-600 dark:text-green-400',
                                            )}
                                        >
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
