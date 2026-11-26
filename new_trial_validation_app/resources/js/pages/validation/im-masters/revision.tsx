import { Form, Head, Link, router } from '@inertiajs/react';
import { FileDown, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ValidationImRevisionController from '@/actions/App/Http/Controllers/ValidationImRevisionController';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import {
    IM_CONTENT_KEYS,
    IM_SECTIONS,
    imStatusClassName,
} from '@/lib/im-master';
import type { ImSection } from '@/lib/im-master';
import { show as masterShow } from '@/routes/validation/im-masters';
import { index as mastersIndex } from '@/routes/validation/im-masters';

type Param = {
    section: string;
    code: string | null;
    name: string;
    category: string | null;
    description: string | null;
    specification: string | null;
    frequency: string | null;
    value_min: string | null;
    value_max: string | null;
    unit: string | null;
};

type Evidence = {
    kind: string;
    trial_id: number | null;
    title: string;
    reference: string;
    note: string | null;
};

type Revision = {
    id: number;
    master_id: number;
    fg_code: string;
    product_name: string;
    bulk_wip_refs: string[];
    im_number: string;
    revision_no: number;
    status: string;
    description: string;
    appearance: string;
    mfg_category: string;
    customer: string;
    customer_ref: string;
    line_label: string;
    issue_date: string | null;
    effective_from: string | null;
    revision_reason: string;
    replaces_number: string | null;
    legacy_reference: string | null;
    is_onboarded: boolean;
    content: Record<string, string>;
    prepared_by_name: string;
    pending_with: string;
    approval_comment: string | null;
    approved_by: string | null;
    approved_at: string | null;
    review_round: number;
};

type Signoff = {
    id: number;
    round: number;
    stage: string;
    role_label: string;
    user_name: string;
    status: string;
    comment: string | null;
    signed_at: string | null;
};

type PageProps = {
    revision: Revision;
    parameters: Param[];
    evidence: Evidence[];
    files: {
        id: number;
        category: string;
        caption: string;
        original_name: string;
        mime: string;
    }[];
    signoffs: Signoff[];
    fileCategories: string[];
    completeness: string[];
    qaUsers: { id: number; label: string }[];
    approvedTrials: { id: number; label: string }[];
    cases: { id: number; label: string }[];
    linkedCase: { id: number; case_no: string | null } | null;
    mySignoffId: number | null;
    can: { edit: boolean; delete: boolean; approve: boolean };
};

type Col = {
    key: keyof Param;
    label: string;
    numeric?: boolean;
    wide?: boolean;
};

const COLS: Record<ImSection, Col[]> = {
    bom: [
        { key: 'code', label: 'im_master.cols.item_no' },
        { key: 'name', label: 'im_master.cols.material', wide: true },
        { key: 'category', label: 'im_master.cols.category' },
    ],
    filling_point: [
        { key: 'name', label: 'im_master.cols.point', wide: true },
        { key: 'description', label: 'im_master.cols.detail', wide: true },
    ],
    appearance_point: [
        { key: 'name', label: 'im_master.cols.point', wide: true },
        { key: 'description', label: 'im_master.cols.detail', wide: true },
    ],
    weight: [
        { key: 'name', label: 'im_master.cols.product', wide: true },
        { key: 'code', label: 'im_master.cols.bulk_code' },
        { key: 'category', label: 'im_master.cols.fg_wip_code' },
        { key: 'value_min', label: 'im_master.cols.min', numeric: true },
        { key: 'value_max', label: 'im_master.cols.max', numeric: true },
        { key: 'unit', label: 'im_master.cols.unit' },
    ],
    coding: [
        { key: 'name', label: 'im_master.cols.point', wide: true },
        { key: 'description', label: 'im_master.cols.detail', wide: true },
        { key: 'specification', label: 'im_master.cols.specification' },
    ],
    packing_point: [
        { key: 'name', label: 'im_master.cols.point', wide: true },
        { key: 'description', label: 'im_master.cols.detail', wide: true },
    ],
    surveillance_initial: [
        { key: 'name', label: 'im_master.cols.check', wide: true },
        { key: 'description', label: 'im_master.cols.work', wide: true },
        { key: 'specification', label: 'im_master.cols.specification' },
        { key: 'frequency', label: 'im_master.cols.frequency' },
    ],
    surveillance_periodic: [
        { key: 'name', label: 'im_master.cols.check', wide: true },
        { key: 'description', label: 'im_master.cols.work', wide: true },
        { key: 'specification', label: 'im_master.cols.specification' },
        { key: 'frequency', label: 'im_master.cols.frequency' },
    ],
};

const HEADER_FIELDS = [
    'description',
    'appearance',
    'mfg_category',
    'customer',
    'customer_ref',
    'line_label',
    'revision_reason',
    'legacy_reference',
] as const;

function emptyParam(section: string): Param {
    return {
        section,
        code: '',
        name: '',
        category: '',
        description: '',
        specification: '',
        frequency: '',
        value_min: '',
        value_max: '',
        unit: section === 'weight' ? 'Gram' : '',
    };
}

function Field({
    label,
    children,
    className,
}: {
    label: string;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div className={`grid min-w-0 gap-1.5 ${className ?? ''}`}>
            <Label className="text-xs">{label}</Label>
            {children}
        </div>
    );
}

export default function ImRevisionPage({
    revision,
    parameters,
    evidence,
    files,
    signoffs,
    fileCategories,
    completeness,
    qaUsers,
    approvedTrials,
    cases,
    linkedCase,
    mySignoffId,
    can,
}: PageProps) {
    const { t } = useTranslation();
    const editable = can.edit;

    const [header, setHeader] = useState({
        description: revision.description,
        appearance: revision.appearance,
        mfg_category: revision.mfg_category,
        customer: revision.customer,
        customer_ref: revision.customer_ref,
        line_label: revision.line_label,
        revision_reason: revision.revision_reason,
        legacy_reference: revision.legacy_reference ?? '',
        issue_date: revision.issue_date ?? '',
        effective_from: revision.effective_from ?? '',
    });
    const [content, setContent] = useState<Record<string, string>>(
        revision.content ?? {},
    );
    const [bulk, setBulk] = useState(revision.bulk_wip_refs.join(', '));
    const [params, setParams] = useState<Param[]>(parameters);
    const [ev, setEv] = useState<Evidence[]>(evidence);
    const [caseId, setCaseId] = useState(
        linkedCase ? String(linkedCase.id) : '',
    );
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [reviewers, setReviewers] = useState<number[]>([]);
    const [approverId, setApproverId] = useState('');

    function touch<T>(setter: (v: T) => void, value: T) {
        setter(value);
        setDirty(true);
    }

    function updateParam(index: number, patch: Partial<Param>) {
        touch(
            setParams,
            params.map((p, i) => (i === index ? { ...p, ...patch } : p)),
        );
    }

    function save() {
        setSaving(true);
        router.put(
            ValidationImRevisionController.update.url(revision.id),
            {
                ...header,
                content,
                bulk_wip_refs: bulk
                    .split(',')
                    .map((c) => c.trim())
                    .filter(Boolean),
                parameters: params,
                evidence: ev,
                case_id: caseId || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    setErrors({});
                },
                onError: (e) => setErrors(e as Record<string, string>),
                onFinish: () => setSaving(false),
            },
        );
    }

    const grouped = (section: string) =>
        params
            .map((p, index) => ({ p, index }))
            .filter(({ p }) => p.section === section);

    return (
        <>
            <Head title={revision.im_number} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Heading
                            title={revision.im_number}
                            description={`${revision.fg_code} · ${revision.product_name} · Rev. ${revision.revision_no}`}
                        />
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                className={imStatusClassName(revision.status)}
                            >
                                {t(`im_master.status.${revision.status}`)}
                            </Badge>
                            {revision.pending_with && (
                                <span className="text-xs text-muted-foreground">
                                    {t('im_master.waiting_for')}:{' '}
                                    {revision.pending_with}
                                </span>
                            )}
                            {revision.is_onboarded && (
                                <Badge variant="outline">
                                    {t('im_master.revision.onboarded')}
                                </Badge>
                            )}
                            {linkedCase && (
                                <Badge variant="outline">
                                    Case {linkedCase.case_no}
                                </Badge>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="gap-1.5">
                            <a
                                href={ValidationImRevisionController.pdf.url(
                                    revision.id,
                                )}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileDown className="size-4" />
                                {t('im_master.pdf_button')}
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={masterShow(revision.master_id)}>
                                {t('im_master.back_to_master')}
                            </Link>
                        </Button>
                        {can.delete && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline">
                                        <Trash2 className="size-4" />
                                        {t('im_master.discard')}
                                    </Button>
                                }
                                title={t('im_master.discard')}
                                description={t('im_master.discard_confirm')}
                                formProps={ValidationImRevisionController.destroy.form(
                                    revision.id,
                                )}
                            />
                        )}
                    </div>
                </div>

                {!editable && revision.status !== 'Effective' && (
                    <Alert>
                        <AlertDescription>
                            {t('im_master.locked_hint')}
                        </AlertDescription>
                    </Alert>
                )}

                {revision.status === 'Need Revision' &&
                    revision.approval_comment && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {t('im_master.returned_note')}:{' '}
                                {revision.approval_comment}
                            </AlertDescription>
                        </Alert>
                    )}

                {/* Header */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('im_master.sections_title.header')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {HEADER_FIELDS.map((key) => (
                            <Field
                                key={key}
                                label={t(`im_master.fields.${key}`)}
                                className={
                                    key === 'description' ||
                                    key === 'line_label' ||
                                    key === 'revision_reason'
                                        ? 'sm:col-span-2'
                                        : undefined
                                }
                            >
                                <Input
                                    value={header[key]}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        touch(setHeader, {
                                            ...header,
                                            [key]: e.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors[key]} />
                            </Field>
                        ))}
                        <Field label={t('im_master.fields.issue_date')}>
                            <Input
                                type="date"
                                value={header.issue_date}
                                disabled={!editable}
                                onChange={(e) =>
                                    touch(setHeader, {
                                        ...header,
                                        issue_date: e.target.value,
                                    })
                                }
                            />
                        </Field>
                        <Field label={t('im_master.fields.effective_from')}>
                            <Input
                                type="date"
                                value={header.effective_from}
                                disabled={!editable}
                                onChange={(e) =>
                                    touch(setHeader, {
                                        ...header,
                                        effective_from: e.target.value,
                                    })
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('im_master.fields.effective_from_hint')}
                            </p>
                        </Field>
                        <Field label={t('im_master.fields.bulk_wip_refs')}>
                            <Input
                                value={bulk}
                                disabled={!editable}
                                onChange={(e) => touch(setBulk, e.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                {t('im_master.fields.bulk_wip_hint')}
                            </p>
                        </Field>
                        {revision.replaces_number && (
                            <Field label={t('im_master.fields.replaces')}>
                                <Input
                                    value={revision.replaces_number}
                                    disabled
                                />
                            </Field>
                        )}
                        {editable && cases.length > 0 && (
                            <Field label={t('im_master.fields.case')}>
                                <Combobox
                                    options={cases.map((c) => ({
                                        value: String(c.id),
                                        label: c.label,
                                    }))}
                                    value={caseId}
                                    onChange={(v) => touch(setCaseId, v)}
                                />
                            </Field>
                        )}
                    </CardContent>
                </Card>

                {/* Structured parameters */}
                {IM_SECTIONS.map((section) => (
                    <Card key={section}>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle>
                                {t(`im_master.sections.${section}`)}
                            </CardTitle>
                            {editable && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="gap-1"
                                    onClick={() =>
                                        touch(setParams, [
                                            ...params,
                                            emptyParam(section),
                                        ])
                                    }
                                >
                                    <Plus className="size-3.5" />
                                    {t('im_master.add_row')}
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {grouped(section).length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    —
                                </p>
                            )}
                            {grouped(section).map(({ p, index }) => (
                                <div
                                    key={index}
                                    className="flex flex-wrap items-end gap-2"
                                >
                                    {COLS[section].map((col) => (
                                        <Field
                                            key={col.key}
                                            label={t(col.label)}
                                            className={
                                                col.wide
                                                    ? 'min-w-56 flex-[2]'
                                                    : 'min-w-28 flex-1'
                                            }
                                        >
                                            {col.wide && col.key !== 'name' ? (
                                                <Textarea
                                                    rows={2}
                                                    value={
                                                        (p[
                                                            col.key
                                                        ] as string) ?? ''
                                                    }
                                                    disabled={!editable}
                                                    onChange={(e) =>
                                                        updateParam(index, {
                                                            [col.key]:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            ) : (
                                                <Input
                                                    inputMode={
                                                        col.numeric
                                                            ? 'decimal'
                                                            : undefined
                                                    }
                                                    value={
                                                        (p[
                                                            col.key
                                                        ] as string) ?? ''
                                                    }
                                                    disabled={!editable}
                                                    onChange={(e) =>
                                                        updateParam(index, {
                                                            [col.key]:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            )}
                                        </Field>
                                    ))}
                                    {editable && (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label={t(
                                                'im_master.remove_row',
                                            )}
                                            onClick={() =>
                                                touch(
                                                    setParams,
                                                    params.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}

                {/* Free-form blocks */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('im_master.sections_title.content')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        {IM_CONTENT_KEYS.map((key) => (
                            <Field
                                key={key}
                                label={t(`im_master.content.${key}`)}
                            >
                                <Textarea
                                    rows={2}
                                    value={content[key] ?? ''}
                                    disabled={!editable}
                                    onChange={(e) =>
                                        touch(setContent, {
                                            ...content,
                                            [key]: e.target.value,
                                        })
                                    }
                                />
                            </Field>
                        ))}
                    </CardContent>
                </Card>

                {/* Evidence */}
                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>{t('im_master.evidence.title')}</CardTitle>
                        {editable && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                className="gap-1"
                                onClick={() =>
                                    touch(setEv, [
                                        ...ev,
                                        {
                                            kind: 'trial',
                                            trial_id: null,
                                            title: '',
                                            reference: '',
                                            note: '',
                                        },
                                    ])
                                }
                            >
                                <Plus className="size-3.5" />
                                {t('im_master.add_row')}
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-xs text-muted-foreground">
                            {t('im_master.evidence.hint')}
                        </p>
                        {ev.length === 0 && (
                            <p className="text-sm text-muted-foreground">—</p>
                        )}
                        {ev.map((row, index) => (
                            <div
                                key={index}
                                className="flex flex-wrap items-end gap-2 rounded-md border p-2"
                            >
                                <Field
                                    label={t('im_master.evidence.kind')}
                                    className="w-40"
                                >
                                    <select
                                        className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                        value={row.kind}
                                        disabled={!editable}
                                        onChange={(e) =>
                                            touch(
                                                setEv,
                                                ev.map((r, i) =>
                                                    i === index
                                                        ? {
                                                              ...r,
                                                              kind: e.target
                                                                  .value,
                                                          }
                                                        : r,
                                                ),
                                            )
                                        }
                                    >
                                        <option value="trial">
                                            {t('im_master.evidence.trial')}
                                        </option>
                                        <option value="document">
                                            {t('im_master.evidence.document')}
                                        </option>
                                    </select>
                                </Field>
                                {row.kind === 'trial' ? (
                                    <Field
                                        label={t(
                                            'im_master.evidence.approved_trial',
                                        )}
                                        className="min-w-64 flex-[2]"
                                    >
                                        <Combobox
                                            disabled={!editable}
                                            options={[
                                                ...approvedTrials.map((tr) => ({
                                                    value: String(tr.id),
                                                    label: tr.label,
                                                })),
                                                ...(row.trial_id &&
                                                !approvedTrials.some(
                                                    (tr) =>
                                                        tr.id === row.trial_id,
                                                )
                                                    ? [
                                                          {
                                                              value: String(
                                                                  row.trial_id,
                                                              ),
                                                              label: `${row.reference} · ${row.title}`,
                                                          },
                                                      ]
                                                    : []),
                                            ]}
                                            value={
                                                row.trial_id
                                                    ? String(row.trial_id)
                                                    : ''
                                            }
                                            onChange={(v) =>
                                                touch(
                                                    setEv,
                                                    ev.map((r, i) =>
                                                        i === index
                                                            ? {
                                                                  ...r,
                                                                  trial_id: v
                                                                      ? Number(
                                                                            v,
                                                                        )
                                                                      : null,
                                                              }
                                                            : r,
                                                    ),
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `evidence.${index}.trial_id`
                                                ]
                                            }
                                        />
                                    </Field>
                                ) : (
                                    <>
                                        <Field
                                            label={t(
                                                'im_master.evidence.doc_title',
                                            )}
                                            className="min-w-48 flex-[2]"
                                        >
                                            <Input
                                                value={row.title}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    touch(
                                                        setEv,
                                                        ev.map((r, i) =>
                                                            i === index
                                                                ? {
                                                                      ...r,
                                                                      title: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : r,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label={t(
                                                'im_master.evidence.reference',
                                            )}
                                            className="min-w-40 flex-1"
                                        >
                                            <Input
                                                value={row.reference}
                                                disabled={!editable}
                                                onChange={(e) =>
                                                    touch(
                                                        setEv,
                                                        ev.map((r, i) =>
                                                            i === index
                                                                ? {
                                                                      ...r,
                                                                      reference:
                                                                          e
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : r,
                                                        ),
                                                    )
                                                }
                                            />
                                        </Field>
                                    </>
                                )}
                                <Field
                                    label={t('im_master.evidence.note')}
                                    className="min-w-48 flex-[2]"
                                >
                                    <Input
                                        value={row.note ?? ''}
                                        disabled={!editable}
                                        onChange={(e) =>
                                            touch(
                                                setEv,
                                                ev.map((r, i) =>
                                                    i === index
                                                        ? {
                                                              ...r,
                                                              note: e.target
                                                                  .value,
                                                          }
                                                        : r,
                                                ),
                                            )
                                        }
                                    />
                                </Field>
                                {editable && (
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="ghost"
                                        aria-label={t('im_master.remove_row')}
                                        onClick={() =>
                                            touch(
                                                setEv,
                                                ev.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {editable && (
                    <div className="sticky bottom-0 z-10 flex items-center justify-between gap-3 rounded-md border bg-background p-3 shadow-sm">
                        <span className="text-sm text-muted-foreground">
                            {dirty
                                ? t('im_master.unsaved')
                                : t('im_master.all_saved')}
                        </span>
                        <Button onClick={save} disabled={saving || !dirty}>
                            {t('im_master.save')}
                        </Button>
                    </div>
                )}

                {/* Files */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('im_master.files.title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {editable && (
                            <Form
                                {...ValidationImRevisionController.fileStore.form(
                                    revision.id,
                                )}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="flex flex-wrap items-end gap-2"
                            >
                                {({ errors: fe, processing }) => (
                                    <>
                                        <Field
                                            label={t(
                                                'im_master.files.category',
                                            )}
                                            className="w-56"
                                        >
                                            <select
                                                name="category"
                                                className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                            >
                                                {fileCategories.map((c) => (
                                                    <option key={c} value={c}>
                                                        {t(
                                                            `im_master.files.categories.${c}`,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={fe.category} />
                                        </Field>
                                        <Field
                                            label={t('im_master.files.caption')}
                                            className="min-w-48 flex-1"
                                        >
                                            <Input name="caption" />
                                        </Field>
                                        <Field
                                            label={t('im_master.files.pick')}
                                            className="min-w-56 flex-1"
                                        >
                                            <Input
                                                type="file"
                                                name="files[]"
                                                multiple
                                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                            />
                                            <InputError message={fe.files} />
                                        </Field>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('im_master.files.upload')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                        {files.length === 0 && (
                            <p className="text-sm text-muted-foreground">—</p>
                        )}
                        <ul className="space-y-1 text-sm">
                            {files.map((f) => (
                                <li
                                    key={f.id}
                                    className="flex items-center justify-between gap-2 rounded border px-2 py-1"
                                >
                                    <span className="min-w-0 truncate">
                                        <Badge
                                            variant="outline"
                                            className="mr-2"
                                        >
                                            {t(
                                                `im_master.files.categories.${f.category}`,
                                            )}
                                        </Badge>
                                        <a
                                            className="hover:underline"
                                            target="_blank"
                                            rel="noreferrer"
                                            href={ValidationImRevisionController.fileShow.url(
                                                {
                                                    revision: revision.id,
                                                    file: f.id,
                                                },
                                            )}
                                        >
                                            {f.original_name}
                                        </a>
                                        {f.caption && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {f.caption}
                                            </span>
                                        )}
                                    </span>
                                    {editable && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    aria-label={t(
                                                        'im_master.remove_row',
                                                    )}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            }
                                            title={t('im_master.files.delete')}
                                            description={f.original_name}
                                            formProps={ValidationImRevisionController.fileDestroy.form(
                                                {
                                                    revision: revision.id,
                                                    file: f.id,
                                                },
                                            )}
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                {/* QA sign-off chain */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('im_master.chain.title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-xs text-muted-foreground">
                            {t('im_master.chain.hint')}
                        </p>

                        {signoffs.length > 0 && (
                            <ul className="space-y-1 text-sm">
                                {signoffs.map((s) => (
                                    <li
                                        key={s.id}
                                        className="flex flex-wrap items-center gap-2 rounded border px-2 py-1"
                                    >
                                        <Badge variant="outline">
                                            {t(`im_master.chain.${s.stage}`)}
                                            {s.round > 1
                                                ? ` · #${s.round}`
                                                : ''}
                                        </Badge>
                                        <span className="font-medium">
                                            {s.user_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {t(
                                                `im_master.chain.status.${s.status}`,
                                            )}
                                            {s.signed_at
                                                ? ` · ${s.signed_at}`
                                                : ''}
                                        </span>
                                        {s.comment && (
                                            <span className="w-full text-xs">
                                                {s.comment}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {editable && (
                            <Form
                                {...ValidationImRevisionController.submit.form(
                                    revision.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-3 rounded-md border p-3"
                            >
                                {({ errors: se, processing }) => (
                                    <>
                                        <h4 className="font-medium">
                                            {t('im_master.chain.submit_title')}
                                        </h4>
                                        {completeness.length > 0 && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    <ul className="list-disc pl-4">
                                                        {completeness.map(
                                                            (c) => (
                                                                <li key={c}>
                                                                    {c}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.reviewers')}
                                            </Label>
                                            <div className="flex flex-wrap gap-2">
                                                {qaUsers.map((u) => (
                                                    <label
                                                        key={u.id}
                                                        className="flex items-center gap-1.5 rounded border px-2 py-1 text-sm"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            name="reviewer_ids[]"
                                                            value={u.id}
                                                            checked={reviewers.includes(
                                                                u.id,
                                                            )}
                                                            onChange={(e) =>
                                                                setReviewers(
                                                                    e.target
                                                                        .checked
                                                                        ? [
                                                                              ...reviewers,
                                                                              u.id,
                                                                          ]
                                                                        : reviewers.filter(
                                                                              (
                                                                                  id,
                                                                              ) =>
                                                                                  id !==
                                                                                  u.id,
                                                                          ),
                                                                )
                                                            }
                                                        />
                                                        {u.label}
                                                    </label>
                                                ))}
                                            </div>
                                            {qaUsers.length === 0 && (
                                                <p className="text-xs text-muted-foreground">
                                                    {t(
                                                        'im_master.chain.no_qa_users',
                                                    )}
                                                </p>
                                            )}
                                            <InputError
                                                message={se.reviewer_ids}
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.approver')}
                                            </Label>
                                            <Combobox
                                                options={qaUsers
                                                    .filter(
                                                        (u) =>
                                                            !reviewers.includes(
                                                                u.id,
                                                            ),
                                                    )
                                                    .map((u) => ({
                                                        value: String(u.id),
                                                        label: u.label,
                                                    }))}
                                                value={approverId}
                                                onChange={setApproverId}
                                            />
                                            <input
                                                type="hidden"
                                                name="approver_user_id"
                                                value={approverId}
                                            />
                                            <InputError
                                                message={se.approver_user_id}
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.password')}
                                            </Label>
                                            <Input
                                                type="password"
                                                name="signature_password"
                                                autoComplete="current-password"
                                            />
                                            <InputError
                                                message={se.signature_password}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing || dirty}
                                        >
                                            {t('im_master.chain.submit')}
                                        </Button>
                                        {dirty && (
                                            <p className="text-xs text-muted-foreground">
                                                {t(
                                                    'im_master.chain.save_first',
                                                )}
                                            </p>
                                        )}
                                    </>
                                )}
                            </Form>
                        )}

                        {mySignoffId !== null && (
                            <Form
                                {...ValidationImRevisionController.review.form(
                                    mySignoffId,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-3 rounded-md border p-3"
                            >
                                {({ errors: re, processing }) => (
                                    <>
                                        <h4 className="font-medium">
                                            {t('im_master.chain.review_title')}
                                        </h4>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.comment')}
                                            </Label>
                                            <Textarea name="comment" rows={3} />
                                            <InputError message={re.comment} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.password')}
                                            </Label>
                                            <Input
                                                type="password"
                                                name="signature_password"
                                                autoComplete="current-password"
                                            />
                                            <InputError
                                                message={re.signature_password}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('im_master.chain.review_submit')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}

                        {can.approve && (
                            <Form
                                {...ValidationImRevisionController.decide.form(
                                    revision.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="space-y-3 rounded-md border p-3"
                            >
                                {({ errors: de, processing }) => (
                                    <>
                                        <h4 className="font-medium">
                                            {t(
                                                'im_master.chain.decision_title',
                                            )}
                                        </h4>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.decision')}
                                            </Label>
                                            <select
                                                name="decision"
                                                defaultValue="Approved"
                                                className="h-9 rounded-md border bg-transparent px-2 text-sm"
                                            >
                                                <option value="Approved">
                                                    {t(
                                                        'im_master.chain.decisions.Approved',
                                                    )}
                                                </option>
                                                <option value="Need Revision">
                                                    {t(
                                                        'im_master.chain.decisions.Need Revision',
                                                    )}
                                                </option>
                                                <option value="Rejected">
                                                    {t(
                                                        'im_master.chain.decisions.Rejected',
                                                    )}
                                                </option>
                                            </select>
                                            <InputError message={de.decision} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.comment')}
                                            </Label>
                                            <Textarea
                                                name="approval_comment"
                                                rows={3}
                                            />
                                            <InputError
                                                message={de.approval_comment}
                                            />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label className="text-xs">
                                                {t('im_master.chain.password')}
                                            </Label>
                                            <Input
                                                type="password"
                                                name="signature_password"
                                                autoComplete="current-password"
                                            />
                                            <InputError
                                                message={de.signature_password}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('im_master.chain.decide')}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ImRevisionPage.layout = {
    breadcrumbs: [{ title: 'IM Master', href: mastersIndex() }],
};
