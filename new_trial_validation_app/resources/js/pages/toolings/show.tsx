import { Form, Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Circle,
    Download,
    FileDown,
    FileText,
    FolderPlus,
    Pause,
    Pencil,
    Play,
    Trash2,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import ToolingController from '@/actions/App/Http/Controllers/ToolingController';
import ToolingFileController from '@/actions/App/Http/Controllers/ToolingFileController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    AssignSignersFields,
    type SignerCandidates,
} from '@/components/toolings/assign-signers-fields';
import {
    NoteField,
    PieCheckFields,
    SignaturePasswordField,
    StepField,
} from '@/components/toolings/step-fields';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import { formatBytes } from '@/lib/project';
import {
    TOOLING_ROLES,
    toolingActionLabel,
    toolingProductTypeLabel,
    toolingRiskClassName,
    toolingRiskLabel,
    toolingSampleStatusLabel,
    toolingStatusClassName,
    toolingStatusLabel,
    toolingToolStatusClassName,
    toolingToolStatusLabel,
} from '@/lib/tooling';
import { cn } from '@/lib/utils';
import { show as projectShow } from '@/routes/projects';
import {
    edit,
    index as toolingsIndex,
    pdf,
    word,
} from '@/routes/projects/toolings';

type Approval = {
    role: string;
    assignee_id: number | null;
    assignee_name: string | null;
    approved: boolean;
    signer_name: string;
    sign_date: string | null;
    signed_at: string | null;
    note: string | null;
    method: string;
    signed_by_account: string;
    signature_image: string | null;
};

type HistoryEntry = {
    at: string;
    by: string;
    status: string;
    action: string;
    note: string;
    code: boolean;
};

type Tooling = {
    id: number;
    npd_no: string;
    doc_code: string;
    product_name: string;
    client_code: string;
    packaging_type: string;
    status: string;
    sample_status: string;
    is_critical: boolean;
    is_late: boolean;
    due_date: string | null;
    submitted_by_name: string;
    submitted_at: string | null;
    request_date: string | null;
    requested_by_name: string;
    marketing: string;
    packdev: string;
    product_type: string;
    quantity_po: string;
    launch_date: string;
    dimension_packaging: string | null;
    material_packaging: string | null;
    bom_primary: string | null;
    pm_ref_detail: string | null;
    links: { title: string; url: string }[];
    sample_send_date: string | null;
    sample_send_note: string | null;
    sample_received_date: string | null;
    sample_received_note: string | null;
    sample_received_by_name: string;
    formula_info: string | null;
    volume_suggest: string;
    recommended_machine: string;
    need_invest: string;
    need_invest_note: string | null;
    production_note: string | null;
    risk_level: string;
    pi_owner_name: string;
    status_reason: string | null;
    bottleneck: string;
    my_action: string | null;
    items: { tool: string; actual: string; status: string }[];
    approvals: Approval[];
    history: HistoryEntry[];
};

type FileItem = {
    id: number;
    file_name: string;
    size_bytes: number;
    description: string | null;
    uploaded_by_name: string;
    uploaded_at: string | null;
    is_missing: boolean;
    url: string;
};

type Can = Record<
    | 'update'
    | 'signPrni'
    | 'sendSample'
    | 'signRni'
    | 'receiveSample'
    | 'pieCheck'
    | 'signProduction'
    | 'hold'
    | 'reject'
    | 'resume'
    | 'assign'
    | 'attach'
    | 'deleteFile'
    | 'promote'
    | 'delete',
    boolean
>;

type PageProps = {
    tooling: Tooling;
    project: { id: number; name: string; code: string } | null;
    files: FileItem[];
    can: Can;
    candidates: SignerCandidates | null;
    defaultTools: string[];
    options: {
        tool_statuses: string[];
        invest_values: string[];
        risk_levels: string[];
    };
    uploadLimitMb: number;
    today: string;
};

function Detail({
    label,
    value,
    className,
}: {
    readonly label: string;
    readonly value: ReactNode;
    readonly className?: string;
}) {
    const empty = value === null || value === undefined || value === '';

    return (
        <div className={cn('space-y-1', className)}>
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm whitespace-pre-line">
                {empty ? (
                    <span className="text-muted-foreground">-</span>
                ) : (
                    value
                )}
            </dd>
        </div>
    );
}

export default function ToolingShow({
    tooling,
    project,
    files,
    can,
    candidates,
    defaultTools,
    options,
    uploadLimitMb,
    today,
}: PageProps) {
    const { t } = useTranslation();
    const stepForm = (step: string) =>
        ToolingController.step.form({ tooling: tooling.id, step });

    const title = tooling.npd_no || `#${tooling.id}`;

    // One dialog per workflow step; rendered where the step belongs and,
    // for the user's own next step, again in the "your turn" banner.
    const stepButton = (action: string, primary: boolean): ReactNode => {
        const trigger = (
            <Button variant={primary ? 'default' : 'outline'} size="sm">
                {toolingActionLabel(t, action)}
            </Button>
        );

        switch (action) {
            case 'sign_prni':
                return can.signPrni ? (
                    <ConfirmDialog
                        trigger={trigger}
                        title={t('toolings.actions.sign_prni')}
                        description={t('toolings.roles.prni')}
                        confirmLabel={t('toolings.actions.sign_prni')}
                        confirmVariant="default"
                        formProps={stepForm('sign-prni')}
                    >
                        {({ errors }) => (
                            <div className="mt-4 grid gap-4">
                                <NoteField errors={errors} />
                                <SignaturePasswordField errors={errors} />
                            </div>
                        )}
                    </ConfirmDialog>
                ) : null;
            case 'send_sample':
                return can.sendSample ? (
                    <ConfirmDialog
                        trigger={trigger}
                        title={t('toolings.actions.send_sample')}
                        description={t('toolings.bottleneck.sample')}
                        confirmLabel={t('toolings.actions.send_sample')}
                        confirmVariant="default"
                        formProps={stepForm('send-sample')}
                    >
                        {({ errors }) => (
                            <div className="mt-4 grid gap-4">
                                <StepField
                                    label={`${t('toolings.fields.sample_send_date')} *`}
                                    error={errors.date}
                                >
                                    <Input
                                        type="date"
                                        name="date"
                                        required
                                        defaultValue={
                                            tooling.sample_send_date ?? today
                                        }
                                    />
                                </StepField>
                                <StepField
                                    label={t(
                                        'toolings.fields.sample_send_note',
                                    )}
                                    error={errors.note}
                                >
                                    <Textarea
                                        name="note"
                                        rows={2}
                                        defaultValue={
                                            tooling.sample_send_note ?? ''
                                        }
                                    />
                                </StepField>
                            </div>
                        )}
                    </ConfirmDialog>
                ) : null;
            case 'sign_rni':
                return can.signRni ? (
                    <ConfirmDialog
                        trigger={trigger}
                        title={t('toolings.actions.sign_rni')}
                        description={t('toolings.roles.rni')}
                        confirmLabel={t('toolings.actions.sign_rni')}
                        confirmVariant="default"
                        contentClassName="sm:max-w-xl"
                        formProps={stepForm('sign-rni')}
                    >
                        {({ errors }) => (
                            <div className="mt-4 grid gap-4">
                                <StepField
                                    label={`${t('toolings.fields.formula_info')} *`}
                                    error={errors.formula_info}
                                >
                                    <Textarea
                                        name="formula_info"
                                        rows={3}
                                        required
                                        defaultValue={
                                            tooling.formula_info ?? ''
                                        }
                                    />
                                </StepField>
                                <StepField
                                    label={`${t('toolings.fields.volume_suggest')} *`}
                                    error={errors.volume_suggest}
                                >
                                    <Input
                                        name="volume_suggest"
                                        required
                                        defaultValue={tooling.volume_suggest}
                                    />
                                </StepField>
                                <NoteField errors={errors} />
                                <SignaturePasswordField errors={errors} />
                            </div>
                        )}
                    </ConfirmDialog>
                ) : null;
            case 'receive_sample':
                return can.receiveSample ? (
                    <ConfirmDialog
                        trigger={trigger}
                        title={t('toolings.actions.receive_sample')}
                        description={t('toolings.show.sla_hint')}
                        confirmLabel={t('toolings.actions.receive_sample')}
                        confirmVariant="default"
                        formProps={stepForm('receive-sample')}
                    >
                        {({ errors }) => (
                            <div className="mt-4 grid gap-4">
                                <StepField
                                    label={`${t('toolings.fields.sample_received_date')} *`}
                                    error={errors.date}
                                >
                                    <Input
                                        type="date"
                                        name="date"
                                        required
                                        defaultValue={today}
                                    />
                                </StepField>
                                <StepField
                                    label={t(
                                        'toolings.fields.sample_received_note',
                                    )}
                                    error={errors.note}
                                >
                                    <Textarea name="note" rows={2} />
                                </StepField>
                            </div>
                        )}
                    </ConfirmDialog>
                ) : null;
            case 'pie_check':
                return can.pieCheck ? (
                    <ConfirmDialog
                        trigger={trigger}
                        title={t('toolings.actions.pie_check')}
                        description={t('toolings.roles.pi')}
                        confirmLabel={t('toolings.actions.pie_check')}
                        confirmVariant="default"
                        contentClassName="max-h-[90vh] overflow-y-auto sm:max-w-3xl"
                        formProps={stepForm('pie-check')}
                    >
                        {({ errors }) => (
                            <PieCheckFields
                                errors={errors}
                                initialTools={tooling.items}
                                defaultTools={defaultTools}
                                toolStatuses={options.tool_statuses}
                                investValues={options.invest_values}
                                riskLevels={options.risk_levels}
                                initial={{
                                    recommended_machine:
                                        tooling.recommended_machine,
                                    need_invest: tooling.need_invest,
                                    need_invest_note: tooling.need_invest_note,
                                    production_note: tooling.production_note,
                                    risk_level: tooling.risk_level,
                                }}
                            />
                        )}
                    </ConfirmDialog>
                ) : null;
            case 'sign_production':
                return can.signProduction ? (
                    <ConfirmDialog
                        trigger={trigger}
                        title={t('toolings.actions.sign_production')}
                        description={t('toolings.roles.production')}
                        confirmLabel={t('toolings.actions.sign_production')}
                        confirmVariant="default"
                        formProps={stepForm('sign-production')}
                    >
                        {({ errors }) => (
                            <div className="mt-4 grid gap-4">
                                <NoteField errors={errors} />
                                <SignaturePasswordField errors={errors} />
                            </div>
                        )}
                    </ConfirmDialog>
                ) : null;
            default:
                return null;
        }
    };

    const reasonDialog = (
        step: 'hold' | 'reject',
        icon: ReactNode,
        variant: 'outline' | 'destructive',
    ) => (
        <ConfirmDialog
            trigger={
                <Button variant={variant}>
                    {icon}
                    {t(`toolings.show.${step}`)}
                </Button>
            }
            title={t(`toolings.show.${step}`)}
            description={t(`toolings.show.${step}_hint`)}
            confirmLabel={t(`toolings.show.${step}`)}
            confirmVariant={step === 'reject' ? 'destructive' : 'default'}
            formProps={stepForm(step)}
        >
            {({ errors }) => (
                <div className="mt-4">
                    <StepField
                        label={`${t('toolings.fields.reason')} *`}
                        error={errors.reason}
                    >
                        <Textarea name="reason" rows={3} required />
                    </StepField>
                </div>
            )}
        </ConfirmDialog>
    );

    const approvalFor = (role: string) =>
        tooling.approvals.find((a) => a.role === role);

    const roleAction: Record<string, string> = {
        prni: 'sign_prni',
        rni: 'sign_rni',
        pi: 'pie_check',
        production: 'sign_production',
    };

    return (
        <>
            <Head title={title} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title={`${title} · ${tooling.product_name}`}
                            description={`${tooling.doc_code} · ${tooling.submitted_by_name || '-'}${tooling.submitted_at ? ` · ${tooling.submitted_at}` : ''}`}
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className={toolingStatusClassName(
                                    tooling.status,
                                )}
                            >
                                {toolingStatusLabel(t, tooling.status)}
                            </Badge>
                            <Badge variant="outline">
                                {toolingSampleStatusLabel(
                                    t,
                                    tooling.sample_status,
                                )}
                            </Badge>
                            {tooling.is_critical && (
                                <Badge
                                    variant="outline"
                                    className="border-red-300 text-red-700 dark:text-red-300"
                                >
                                    {t('toolings.critical')}
                                </Badge>
                            )}
                            {tooling.is_late && (
                                <Badge
                                    variant="outline"
                                    className="border-red-300 text-red-700 dark:text-red-300"
                                >
                                    <AlertTriangle className="size-3" />
                                    {t('toolings.late')}
                                </Badge>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={toolingsIndex().url}>
                                {t('toolings.back')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a
                                href={pdf(tooling.id).url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileDown />
                                {t('toolings.report.pdf')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={word(tooling.id).url}>
                                <FileText />
                                {t('toolings.report.word')}
                            </a>
                        </Button>
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(tooling.id).url}>
                                    <Pencil />
                                    {t('toolings.edit')}
                                </Link>
                            </Button>
                        )}
                        {can.resume && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline">
                                        <Play />
                                        {t('toolings.show.resume')}
                                    </Button>
                                }
                                title={t('toolings.show.resume')}
                                description={t('toolings.show.resume_hint')}
                                confirmLabel={t('toolings.show.resume')}
                                confirmVariant="default"
                                formProps={stepForm('resume')}
                            >
                                {({ errors }) => (
                                    <div className="mt-4">
                                        <NoteField errors={errors} />
                                    </div>
                                )}
                            </ConfirmDialog>
                        )}
                        {can.hold && reasonDialog('hold', <Pause />, 'outline')}
                        {can.reject &&
                            reasonDialog('reject', <XCircle />, 'destructive')}
                        {can.promote && (
                            <ConfirmDialog
                                trigger={
                                    <Button>
                                        <FolderPlus />
                                        {t('toolings.show.promote')}
                                    </Button>
                                }
                                title={t('toolings.show.promote')}
                                description={t('toolings.show.promote_hint')}
                                confirmLabel={t('toolings.show.promote')}
                                confirmVariant="default"
                                formProps={ToolingController.promote.form(
                                    tooling.id,
                                )}
                            >
                                {({ errors }) => (
                                    <InputError message={errors.promote} />
                                )}
                            </ConfirmDialog>
                        )}
                        {can.delete && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        <Trash2 />
                                        {t('toolings.delete')}
                                    </Button>
                                }
                                title={t('toolings.show.delete_title')}
                                description={t(
                                    'toolings.show.delete_description',
                                    { npd: title },
                                )}
                                confirmLabel={t('toolings.delete')}
                                formProps={ToolingController.destroy.form(
                                    tooling.id,
                                )}
                            />
                        )}
                    </div>
                </div>

                {tooling.my_action ? (
                    <Alert className="border-brand/40">
                        <AlertTitle>
                            {t('toolings.show.your_action')}
                        </AlertTitle>
                        <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                            <span>
                                {t(`toolings.bottleneck.${tooling.bottleneck}`)}
                            </span>
                            {stepButton(tooling.my_action, true)}
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert>
                        <AlertTitle>{t('toolings.show.waiting_on')}</AlertTitle>
                        <AlertDescription>
                            {t(`toolings.bottleneck.${tooling.bottleneck}`)}
                            {tooling.status_reason && (
                                <span className="mt-1 block whitespace-pre-line">
                                    {t('toolings.show.status_reason')}:{' '}
                                    {tooling.status_reason}
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {project && (
                    <Alert>
                        <AlertDescription>
                            {t('toolings.show.project_link')}:{' '}
                            <Link
                                href={projectShow(project.id).url}
                                className="font-medium underline underline-offset-2"
                            >
                                {project.code
                                    ? `${project.code} · ${project.name}`
                                    : project.name}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle className="text-base">
                            {t('toolings.show.section_approvals')}
                        </CardTitle>
                        {can.assign && candidates && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline" size="sm">
                                        {t('toolings.assign.button')}
                                    </Button>
                                }
                                title={t('toolings.assign.title')}
                                description={t('toolings.assign.button')}
                                confirmLabel={t('toolings.assign.save')}
                                confirmVariant="default"
                                formProps={stepForm('assign')}
                            >
                                {({ errors }) => (
                                    <AssignSignersFields
                                        roles={TOOLING_ROLES}
                                        candidates={candidates}
                                        assigned={Object.fromEntries(
                                            tooling.approvals.map((x) => [
                                                x.role,
                                                x.assignee_id,
                                            ]),
                                        )}
                                        signed={Object.fromEntries(
                                            tooling.approvals.map((x) => [
                                                x.role,
                                                x.approved,
                                            ]),
                                        )}
                                        errors={errors}
                                    />
                                )}
                            </ConfirmDialog>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {TOOLING_ROLES.map((role) => {
                            const a = approvalFor(role);

                            return (
                                <div
                                    key={role}
                                    className={cn(
                                        'flex flex-col gap-2 rounded-lg border p-3',
                                        a?.approved &&
                                            'border-emerald-300 dark:border-emerald-800',
                                    )}
                                >
                                    <div className="flex items-center gap-2 text-sm font-medium">
                                        {a?.approved ? (
                                            <CheckCircle2 className="size-4 text-emerald-600" />
                                        ) : (
                                            <Circle className="size-4 text-muted-foreground" />
                                        )}
                                        {t(`toolings.roles.${role}`)}
                                    </div>
                                    {a?.approved ? (
                                        <div className="space-y-1 text-xs text-muted-foreground">
                                            <div className="text-sm text-foreground">
                                                {t('toolings.show.signed_by', {
                                                    name: a.signer_name || '-',
                                                })}
                                            </div>
                                            <div>
                                                {a.signed_at ??
                                                    a.sign_date ??
                                                    ''}
                                            </div>
                                            <div>
                                                {a.method === 'legacy'
                                                    ? t(
                                                          'toolings.show.signed_legacy',
                                                      )
                                                    : t(
                                                          'toolings.show.signed_with_password',
                                                      )}
                                                {a.method === 'legacy' &&
                                                    a.signed_by_account &&
                                                    ` · ${t('toolings.show.legacy_account', { account: a.signed_by_account })}`}
                                            </div>
                                            {a.signature_image && (
                                                <img
                                                    src={a.signature_image}
                                                    alt={a.signer_name}
                                                    className="h-14 max-w-full rounded border bg-white object-contain p-1"
                                                />
                                            )}
                                            {a.note && (
                                                <div className="whitespace-pre-line text-foreground">
                                                    {a.note}
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            <p className="text-xs text-muted-foreground">
                                                {t('toolings.show.not_signed')}
                                            </p>
                                            <p className="text-xs">
                                                {a?.assignee_name
                                                    ? t(
                                                          'toolings.assign.assigned',
                                                          {
                                                              name: a.assignee_name,
                                                          },
                                                      )
                                                    : t(
                                                          'toolings.assign.anyone_short',
                                                      )}
                                            </p>
                                            {stepButton(
                                                roleAction[role],
                                                false,
                                            )}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('toolings.show.section_request')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Detail
                                label={t('toolings.fields.request_date')}
                                value={tooling.request_date}
                            />
                            <Detail
                                label={t('toolings.fields.requested_by_name')}
                                value={tooling.requested_by_name}
                            />
                            <Detail
                                label={t('toolings.fields.client_code')}
                                value={tooling.client_code}
                            />
                            <Detail
                                label={t('toolings.fields.product_type')}
                                value={toolingProductTypeLabel(
                                    t,
                                    tooling.product_type,
                                )}
                            />
                            <Detail
                                label={t('toolings.fields.marketing')}
                                value={tooling.marketing}
                            />
                            <Detail
                                label={t('toolings.fields.packdev')}
                                value={tooling.packdev}
                            />
                            <Detail
                                label={t('toolings.fields.quantity_po')}
                                value={tooling.quantity_po}
                            />
                            <Detail
                                label={t('toolings.fields.launch_date')}
                                value={tooling.launch_date}
                            />
                            <Detail
                                label={t('toolings.fields.packaging_type')}
                                value={tooling.packaging_type}
                            />
                            <Detail
                                label={t('toolings.fields.bom_primary')}
                                value={tooling.bom_primary}
                            />
                            <Detail
                                label={t('toolings.fields.dimension_packaging')}
                                value={tooling.dimension_packaging}
                            />
                            <Detail
                                label={t('toolings.fields.material_packaging')}
                                value={tooling.material_packaging}
                            />
                            <Detail
                                label={t('toolings.fields.pm_ref_detail')}
                                value={tooling.pm_ref_detail}
                                className="sm:col-span-2"
                            />
                            {tooling.links.length > 0 && (
                                <Detail
                                    label={t('toolings.fields.links')}
                                    className="sm:col-span-2"
                                    value={
                                        <ul className="space-y-1">
                                            {tooling.links.map((link) => (
                                                <li key={link.url}>
                                                    <a
                                                        href={link.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="break-all underline underline-offset-2"
                                                    >
                                                        {link.title || link.url}
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    }
                                />
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                            <CardTitle className="text-base">
                                {t('toolings.show.section_sample')}
                            </CardTitle>
                            <div className="flex flex-wrap gap-2">
                                {stepButton('send_sample', false)}
                                {stepButton('receive_sample', false)}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label={t('toolings.fields.sample_status')}
                                    value={toolingSampleStatusLabel(
                                        t,
                                        tooling.sample_status,
                                    )}
                                />
                                <Detail
                                    label={t(
                                        'toolings.fields.sample_send_date',
                                    )}
                                    value={tooling.sample_send_date}
                                />
                                <Detail
                                    label={t(
                                        'toolings.fields.sample_send_note',
                                    )}
                                    value={tooling.sample_send_note}
                                    className="sm:col-span-2"
                                />
                                <Detail
                                    label={t(
                                        'toolings.fields.sample_received_date',
                                    )}
                                    value={tooling.sample_received_date}
                                />
                                <Detail
                                    label={t(
                                        'toolings.fields.sample_received_by',
                                    )}
                                    value={tooling.sample_received_by_name}
                                />
                                <Detail
                                    label={t(
                                        'toolings.fields.sample_received_note',
                                    )}
                                    value={tooling.sample_received_note}
                                />
                                <Detail
                                    label={t('toolings.fields.due_date')}
                                    value={tooling.due_date}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                {t('toolings.show.section_formula')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <Detail
                                    label={t('toolings.fields.formula_info')}
                                    value={tooling.formula_info}
                                />
                                <Detail
                                    label={t('toolings.fields.volume_suggest')}
                                    value={tooling.volume_suggest}
                                />
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle className="text-base">
                            {t('toolings.show.section_check')}
                        </CardTitle>
                        {stepButton('pie_check', false)}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {!can.pieCheck &&
                            tooling.status !== 'Approved' &&
                            !approvalFor('pi')?.approved && (
                                <p className="text-xs text-muted-foreground">
                                    {t('toolings.show.pie_locked')}
                                </p>
                            )}
                        {tooling.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('toolings.show.no_tools')}
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">
                                            #
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.fields.tool')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.fields.actual')}
                                        </TableHead>
                                        <TableHead>
                                            {t('toolings.fields.tool_status')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tooling.items.map((item, i) => (
                                        <TableRow key={i}>
                                            <TableCell className="text-muted-foreground">
                                                {String.fromCharCode(97 + i)}.
                                            </TableCell>
                                            <TableCell>
                                                {item.tool || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.actual || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {item.status ? (
                                                    <Badge
                                                        variant="outline"
                                                        className={toolingToolStatusClassName(
                                                            item.status,
                                                        )}
                                                    >
                                                        {toolingToolStatusLabel(
                                                            t,
                                                            item.status,
                                                        )}
                                                    </Badge>
                                                ) : (
                                                    '-'
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Detail
                                label={t('toolings.fields.recommended_machine')}
                                value={tooling.recommended_machine}
                            />
                            <Detail
                                label={t('toolings.fields.need_invest')}
                                value={tooling.need_invest}
                            />
                            <Detail
                                label={t('toolings.fields.risk_level')}
                                value={
                                    tooling.risk_level ? (
                                        <Badge
                                            variant="outline"
                                            className={toolingRiskClassName(
                                                tooling.risk_level,
                                            )}
                                        >
                                            {toolingRiskLabel(
                                                t,
                                                tooling.risk_level,
                                            )}
                                        </Badge>
                                    ) : null
                                }
                            />
                            <Detail
                                label={t('toolings.fields.pi_owner')}
                                value={tooling.pi_owner_name}
                            />
                            <Detail
                                label={t('toolings.fields.need_invest_note')}
                                value={tooling.need_invest_note}
                                className="sm:col-span-2"
                            />
                            <Detail
                                label={t('toolings.fields.production_note')}
                                value={tooling.production_note}
                                className="sm:col-span-2"
                            />
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('toolings.show.section_files')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {files.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('toolings.show.files_empty')}
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {files.map((f) => (
                                    <li
                                        key={f.id}
                                        className="flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0"
                                    >
                                        <div className="min-w-0">
                                            {f.is_missing ? (
                                                <span className="text-muted-foreground line-through">
                                                    {f.file_name}
                                                </span>
                                            ) : (
                                                <a
                                                    href={f.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 font-medium break-all underline underline-offset-2"
                                                >
                                                    <Download className="size-3.5 shrink-0" />
                                                    {f.file_name}
                                                </a>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {formatBytes(f.size_bytes)}
                                                {f.description
                                                    ? ` · ${f.description}`
                                                    : ''}
                                                {f.uploaded_by_name
                                                    ? ` · ${f.uploaded_by_name}`
                                                    : ''}
                                                {f.uploaded_at
                                                    ? ` · ${f.uploaded_at}`
                                                    : ''}
                                            </div>
                                        </div>
                                        {can.deleteFile && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                    >
                                                        {t('toolings.delete')}
                                                    </Button>
                                                }
                                                title={t('toolings.delete')}
                                                description={f.file_name}
                                                confirmLabel={t(
                                                    'toolings.delete',
                                                )}
                                                formProps={ToolingFileController.destroy.form(
                                                    {
                                                        tooling: tooling.id,
                                                        file: f.id,
                                                    },
                                                )}
                                            />
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {can.attach && (
                            <Form
                                {...ToolingFileController.store.form(
                                    tooling.id,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-3 border-t pt-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <StepField
                                            label={`${t('toolings.show.file')} (max ${uploadLimitMb} MB)`}
                                            error={errors.file}
                                        >
                                            <Input
                                                type="file"
                                                name="file"
                                                required
                                            />
                                        </StepField>
                                        <StepField
                                            label={t(
                                                'toolings.show.description',
                                            )}
                                            error={errors.description}
                                        >
                                            <Input name="description" />
                                        </StepField>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('toolings.show.upload')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {t('toolings.show.section_history')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {tooling.history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('toolings.show.history_empty')}
                            </p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {tooling.history.map((h, i) => {
                                    const key = `toolings.history.actions.${h.action}`;
                                    const label = h.code ? t(key) : h.action;

                                    return (
                                        <li
                                            key={`${h.at}-${i}`}
                                            className="border-b pb-2 last:border-0"
                                        >
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <span>
                                                    <span className="font-medium">
                                                        {label === key
                                                            ? h.action
                                                            : label}
                                                    </span>
                                                    {h.by && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            · {h.by}
                                                        </span>
                                                    )}
                                                    {h.status && (
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            ·{' '}
                                                            {toolingStatusLabel(
                                                                t,
                                                                h.status,
                                                            )}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {h.at}
                                                </span>
                                            </div>
                                            {h.note && (
                                                <p className="mt-1 whitespace-pre-line text-muted-foreground">
                                                    {h.note}
                                                </p>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
