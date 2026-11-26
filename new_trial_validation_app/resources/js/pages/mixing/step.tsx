import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MixingWizardSteps } from '@/components/mixing-wizard-steps';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { mixingUrl } from '@/lib/validation-steps';

type MixingSummary = {
    id: number;
    mixing_code: string;
    product_name: string;
    product_type: string;
    progress_status: string;
    current_step: string | null;
    files_count: number;
};

type Parameter = {
    id: number;
    parameter_name: string;
    specification: string | null;
};

type ResultRow = {
    parameter_id: number;
    result_value: string | null;
    decision: string | null;
    remark: string | null;
};

type FileRow = {
    id: number;
    category: string;
    original_name: string;
    mime: string;
    caption: string | null;
};

type PageProps = {
    step: 'validation' | 'attachments' | 'review';
    mixing: MixingSummary;
    canEdit: boolean;
    parameters?: Parameter[];
    results?: Record<string, ResultRow>;
    categories?: string[];
    files?: FileRow[];
    completeness?: string[];
    reviewerDepartments?: string[];
    approvers?: { id: number; label: string }[];
    selectedApproverId?: number | null;
};

const DECISIONS = ['OK', 'NOT OK', 'N/A'] as const;

function ParameterRow({
    index,
    parameter,
    initial,
    canEdit,
}: {
    index: number;
    parameter: Parameter;
    initial: ResultRow | undefined;
    canEdit: boolean;
}) {
    const [decision, setDecision] = useState(initial?.decision ?? 'OK');
    const [result, setResult] = useState(initial?.result_value ?? 'Conform');

    function onDecision(value: string) {
        setDecision(value);

        if (value === 'N/A') {
            setResult('N/A');
        } else if (value === 'OK') {
            setResult('Conform');
        } else {
            setResult('');
        }
    }

    return (
        <TableRow className={decision === 'NOT OK' ? 'bg-destructive/10' : ''}>
            <TableCell className="font-medium">
                {parameter.parameter_name}
                <input
                    type="hidden"
                    name={`results[${index}][parameter_id]`}
                    value={parameter.id}
                />
            </TableCell>
            <TableCell className="text-muted-foreground">
                {parameter.specification}
            </TableCell>
            <TableCell>
                <Select
                    name={`results[${index}][decision]`}
                    value={decision}
                    onValueChange={onDecision}
                    disabled={!canEdit}
                >
                    <SelectTrigger className="w-28">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {DECISIONS.map((d) => (
                            <SelectItem key={d} value={d}>
                                {d}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </TableCell>
            <TableCell>
                <Input
                    name={`results[${index}][result]`}
                    value={result}
                    onChange={(e) => setResult(e.target.value)}
                    disabled={!canEdit}
                />
            </TableCell>
            <TableCell>
                <Textarea
                    name={`results[${index}][remark]`}
                    defaultValue={initial?.remark ?? ''}
                    rows={2}
                    disabled={!canEdit}
                />
            </TableCell>
        </TableRow>
    );
}

function ValidationStep({
    mixing,
    canEdit,
    parameters = [],
    results = {},
}: PageProps) {
    const { t } = useTranslation();

    if (parameters.length === 0) {
        return (
            <Alert>
                <AlertDescription>
                    {t('mixing.validation.no_parameters', {
                        type: mixing.product_type,
                    })}
                </AlertDescription>
            </Alert>
        );
    }

    return (
        <Form
            action={mixingUrl.validation(mixing.id)}
            method="put"
            className="space-y-4"
        >
            {({ errors, processing }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('mixing.validation.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {errors.results && (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        {errors.results}
                                    </AlertDescription>
                                </Alert>
                            )}
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>
                                            {t('mixing.validation.parameter')}
                                        </TableHead>
                                        <TableHead>
                                            {t(
                                                'mixing.validation.specification',
                                            )}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.validation.decision')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.validation.result')}
                                        </TableHead>
                                        <TableHead>
                                            {t('mixing.validation.remark')}
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {parameters.map((p, i) => (
                                        <ParameterRow
                                            key={p.id}
                                            index={i}
                                            parameter={p}
                                            initial={results[String(p.id)]}
                                            canEdit={canEdit}
                                        />
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                    <StickyActionBar>
                        <Button variant="outline" asChild>
                            <Link href={mixingUrl.edit(mixing.id)}>
                                {t('mixing.validation.back')}
                            </Link>
                        </Button>
                        {canEdit ? (
                            <Button type="submit" disabled={processing}>
                                {t('mixing.validation.save_continue')}
                            </Button>
                        ) : (
                            <Button asChild>
                                <Link href={mixingUrl.attachments(mixing.id)}>
                                    {t('mixing.attachments.next')}
                                </Link>
                            </Button>
                        )}
                    </StickyActionBar>
                </>
            )}
        </Form>
    );
}

function AttachmentsStep({
    mixing,
    canEdit,
    categories = [],
    files = [],
}: PageProps) {
    const { t } = useTranslation();
    const [category, setCategory] = useState(categories[0] ?? '');
    const hasBatchRecord = files.some(
        (f) => f.category === 'Revisi Batch Record',
    );

    return (
        <div className="space-y-4">
            {!hasBatchRecord && (
                <Alert>
                    <AlertDescription>
                        {t('mixing.attachments.batch_record_missing')}
                    </AlertDescription>
                </Alert>
            )}

            {canEdit && (
                <Card>
                    <CardHeader>
                        <CardTitle>{t('mixing.attachments.title')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={mixingUrl.attachments(mixing.id)}
                            method="post"
                            resetOnSuccess
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="category"
                                        value={category}
                                    />
                                    <div className="grid gap-2">
                                        <Label>
                                            {t('mixing.attachments.category')}
                                        </Label>
                                        <Select
                                            value={category}
                                            onValueChange={setCategory}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {categories.map((c) => (
                                                    <SelectItem
                                                        key={c}
                                                        value={c}
                                                    >
                                                        {t(
                                                            `mixing.attachments.category_names.${c}`,
                                                        )}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.category} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="caption">
                                            {t('mixing.attachments.caption')}
                                        </Label>
                                        <Input id="caption" name="caption" />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="files">
                                            {t('mixing.attachments.files')}
                                        </Label>
                                        <Input
                                            id="files"
                                            name="files[]"
                                            type="file"
                                            multiple
                                            accept="image/jpeg,image/png,image/webp,application/pdf"
                                        />
                                        <InputError message={errors.files} />
                                    </div>
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('mixing.attachments.upload')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardContent className="pt-6">
                    {files.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('mixing.attachments.empty')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {files.map((f) => (
                                <li
                                    key={f.id}
                                    className="flex items-center justify-between gap-3 py-2 text-sm"
                                >
                                    <div className="min-w-0">
                                        <a
                                            href={mixingUrl.file(
                                                mixing.id,
                                                f.id,
                                            )}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="truncate font-medium underline-offset-4 hover:underline"
                                        >
                                            {f.original_name}
                                        </a>
                                        <div className="text-xs text-muted-foreground">
                                            {t(
                                                `mixing.attachments.category_names.${f.category}`,
                                            )}
                                            {f.caption ? ` · ${f.caption}` : ''}
                                        </div>
                                    </div>
                                    {canEdit && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    ×
                                                </Button>
                                            }
                                            title={t(
                                                'mixing.attachments.delete_confirm',
                                            )}
                                            description={f.original_name}
                                            formProps={{
                                                action: `${mixingUrl.attachments(mixing.id)}/${f.id}`,
                                                method: 'delete',
                                            }}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <StickyActionBar>
                <Button variant="outline" asChild>
                    <Link href={mixingUrl.validation(mixing.id)}>
                        {t('mixing.attachments.back')}
                    </Link>
                </Button>
                <Button asChild>
                    <Link href={mixingUrl.review(mixing.id)}>
                        {t('mixing.attachments.next')}
                    </Link>
                </Button>
            </StickyActionBar>
        </div>
    );
}

function ReviewStep({
    mixing,
    canEdit,
    completeness = [],
    reviewerDepartments = [],
    approvers = [],
    selectedApproverId = null,
}: PageProps) {
    const { t } = useTranslation();
    const [approver, setApprover] = useState(
        selectedApproverId ? String(selectedApproverId) : '',
    );
    const ready = completeness.length === 0;

    return (
        <div className="space-y-4">
            {!ready && (
                <Alert variant="destructive">
                    <AlertDescription>
                        <p className="font-medium">
                            {t('mixing.review.incomplete')}
                        </p>
                        <ul className="list-disc pl-5">
                            {completeness.map((c) => (
                                <li key={c}>{c}</li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            )}
            {!canEdit && (
                <Alert>
                    <AlertDescription>
                        {t('mixing.review.locked')}
                    </AlertDescription>
                </Alert>
            )}

            {canEdit && ready && (
                <Form
                    action={mixingUrl.review(mixing.id)}
                    method="post"
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="approver_user_id"
                                value={approver}
                            />
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {t('mixing.review.title')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label>
                                            {t('mixing.review.departments')}
                                        </Label>
                                        <div className="flex flex-wrap gap-4">
                                            {reviewerDepartments.map((d) => (
                                                <label
                                                    key={d}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        name="departments[]"
                                                        value={d}
                                                    />
                                                    {d}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError
                                            message={errors.departments}
                                        />
                                    </div>
                                    <div className="grid max-w-sm gap-2">
                                        <Label>
                                            {t('mixing.review.approver')}
                                        </Label>
                                        <Select
                                            value={approver}
                                            onValueChange={setApprover}
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={t(
                                                        'mixing.review.select_approver',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {approvers.map((a) => (
                                                    <SelectItem
                                                        key={a.id}
                                                        value={String(a.id)}
                                                    >
                                                        {a.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.approver_user_id}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                            <StickyActionBar>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={mixingUrl.attachments(mixing.id)}
                                    >
                                        {t('mixing.review.back')}
                                    </Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('mixing.review.submit')}
                                </Button>
                            </StickyActionBar>
                        </>
                    )}
                </Form>
            )}

            {!(canEdit && ready) && (
                <StickyActionBar>
                    <Button variant="outline" asChild>
                        <Link href={mixingUrl.attachments(mixing.id)}>
                            {t('mixing.review.back')}
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={mixingUrl.show(mixing.id)}>
                            {t('mixing.review.view_detail')}
                        </Link>
                    </Button>
                </StickyActionBar>
            )}
        </div>
    );
}

export default function MixingStep(props: PageProps) {
    const { t } = useTranslation();
    const { mixing, step } = props;
    const number = step === 'validation' ? 2 : step === 'attachments' ? 3 : 4;

    return (
        <>
            <Head
                title={`${mixing.mixing_code} - ${t(`mixing.steps.${step}`)}`}
            />
            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <Heading
                    title={`${mixing.mixing_code} · ${mixing.product_name}`}
                    description={t(`mixing.steps.${step}`)}
                />
                <MixingWizardSteps currentStep={number} mixing={mixing} />
                {step === 'validation' && <ValidationStep {...props} />}
                {step === 'attachments' && <AttachmentsStep {...props} />}
                {step === 'review' && <ReviewStep {...props} />}
            </div>
        </>
    );
}

MixingStep.layout = {
    breadcrumbs: [
        { title: 'Mixing', href: '/mixings' },
        { title: 'Step', href: '#' },
    ],
};
