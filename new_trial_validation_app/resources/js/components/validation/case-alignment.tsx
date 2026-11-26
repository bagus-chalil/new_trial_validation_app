import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ValidationCaseAlignmentController from '@/actions/App/Http/Controllers/ValidationCaseAlignmentController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

export type CaseClassification = {
    process_area: string;
    purpose: string;
    subject: string;
    document_type: string | null;
    other_purpose_note: string | null;
    derived: boolean;
};

export type ClassificationOptions = {
    process_areas: string[];
    purposes: string[];
    subjects: string[];
    document_types: string[];
};

export type FgMapping = {
    status: 'verified' | 'unverified' | 'mismatch' | 'missing';
    fg_code: string | null;
    official_name: string | null;
};

const fgStatusClassName: Record<FgMapping['status'], string> = {
    verified:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    unverified:
        'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    mismatch: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
    missing: 'bg-muted text-muted-foreground',
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm';

function ClassificationForm({
    caseId,
    classification,
    options,
    onDone,
}: {
    caseId: number;
    classification: CaseClassification;
    options: ClassificationOptions;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [purpose, setPurpose] = useState(classification.purpose);

    return (
        <Form
            {...ValidationCaseAlignmentController.classify.form({
                case: caseId,
            })}
            onSuccess={onDone}
            className="grid gap-4 sm:grid-cols-2"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="process_area">
                            {t('journey.classification.process_area')}
                        </Label>
                        <select
                            id="process_area"
                            name="process_area"
                            defaultValue={classification.process_area}
                            className={selectClassName}
                        >
                            {options.process_areas.map((v) => (
                                <option key={v} value={v}>
                                    {t(
                                        `journey.classification.process_areas.${v}`,
                                    )}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.process_area} />
                    </div>
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="purpose">
                            {t('journey.classification.purpose')}
                        </Label>
                        <select
                            id="purpose"
                            name="purpose"
                            value={purpose}
                            onChange={(e) => setPurpose(e.target.value)}
                            className={selectClassName}
                        >
                            {options.purposes.map((v) => (
                                <option key={v} value={v}>
                                    {t(`journey.classification.purposes.${v}`)}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.purpose} />
                    </div>
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="subject">
                            {t('journey.classification.subject')}
                        </Label>
                        <select
                            id="subject"
                            name="subject"
                            defaultValue={classification.subject}
                            className={selectClassName}
                        >
                            {options.subjects.map((v) => (
                                <option key={v} value={v}>
                                    {t(`journey.classification.subjects.${v}`)}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.subject} />
                    </div>
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="document_type">
                            {t('journey.classification.document_type')}
                        </Label>
                        <select
                            id="document_type"
                            name="document_type"
                            defaultValue={classification.document_type ?? ''}
                            className={selectClassName}
                        >
                            <option value="">
                                {t('journey.classification.not_set')}
                            </option>
                            {options.document_types.map((v) => (
                                <option key={v} value={v}>
                                    {t(
                                        `journey.classification.document_types.${v}`,
                                    )}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.document_type} />
                    </div>
                    {purpose === 'Other' && (
                        <div className="grid min-w-0 gap-2 sm:col-span-2">
                            <Label htmlFor="other_purpose_note">
                                {t('journey.classification.other_note')}
                            </Label>
                            <Input
                                id="other_purpose_note"
                                name="other_purpose_note"
                                defaultValue={
                                    classification.other_purpose_note ?? ''
                                }
                            />
                            <InputError message={errors.other_purpose_note} />
                        </div>
                    )}
                    <div className="flex gap-2 sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            {t('journey.classification.save')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onDone}
                        >
                            {t('common.cancel')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

export function CaseAlignment({
    caseId,
    classification,
    options,
    canClassify,
    cohort,
    cohortCutoff,
    fgMapping,
    canVerifyFg,
}: {
    caseId: number;
    classification: CaseClassification;
    options: ClassificationOptions;
    canClassify: boolean;
    cohort: string;
    cohortCutoff: string;
    fgMapping: FgMapping;
    canVerifyFg: boolean;
}) {
    const { t, formatDateOnly } = useTranslation();
    const [editing, setEditing] = useState(false);

    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between gap-2">
                    <CardTitle>{t('journey.classification.title')}</CardTitle>
                    {canClassify && !editing && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            {t('journey.classification.edit')}
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    {editing ? (
                        <ClassificationForm
                            caseId={caseId}
                            classification={classification}
                            options={options}
                            onDone={() => setEditing(false)}
                        />
                    ) : (
                        <>
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t(
                                            'journey.classification.process_area',
                                        )}
                                    </dt>
                                    <dd>
                                        {t(
                                            `journey.classification.process_areas.${classification.process_area}`,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('journey.classification.purpose')}
                                    </dt>
                                    <dd>
                                        {t(
                                            `journey.classification.purposes.${classification.purpose}`,
                                        )}
                                        {classification.other_purpose_note
                                            ? ` · ${classification.other_purpose_note}`
                                            : ''}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t('journey.classification.subject')}
                                    </dt>
                                    <dd>
                                        {t(
                                            `journey.classification.subjects.${classification.subject}`,
                                        )}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {t(
                                            'journey.classification.document_type',
                                        )}
                                    </dt>
                                    <dd>
                                        {classification.document_type
                                            ? t(
                                                  `journey.classification.document_types.${classification.document_type}`,
                                              )
                                            : t(
                                                  'journey.classification.not_set',
                                              )}
                                    </dd>
                                </div>
                            </dl>
                            {classification.derived && (
                                <p className="text-xs text-muted-foreground">
                                    {t('journey.classification.derived_hint')}
                                </p>
                            )}
                        </>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between gap-2">
                    <CardTitle>{t('journey.fg.title')}</CardTitle>
                    <Badge
                        variant="secondary"
                        className={fgStatusClassName[fgMapping.status]}
                    >
                        {t(`journey.fg.status.${fgMapping.status}`)}
                    </Badge>
                </CardHeader>
                <CardContent className="space-y-3 text-sm">
                    <p>{t(`journey.fg.hint.${fgMapping.status}`)}</p>
                    {fgMapping.official_name && (
                        <p>
                            <span className="text-muted-foreground">
                                {t('journey.fg.official_name')}:
                            </span>{' '}
                            {fgMapping.official_name}
                        </p>
                    )}
                    {canVerifyFg && fgMapping.status === 'unverified' && (
                        <Form
                            {...ValidationCaseAlignmentController.verifyFg.form(
                                { case: caseId },
                            )}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {t('journey.fg.verify')}
                                </Button>
                            )}
                        </Form>
                    )}
                    <div className="flex items-center gap-2 border-t pt-3">
                        <Badge
                            variant="secondary"
                            className={
                                cohort === 'eligible'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-muted text-muted-foreground'
                            }
                        >
                            {cohort === 'eligible'
                                ? t('journey.cohort.badge_eligible')
                                : t('journey.cohort.badge_historical')}
                        </Badge>
                        {cohort !== 'eligible' && (
                            <span className="text-xs text-muted-foreground">
                                {t('journey.cohort.historical_hint', {
                                    date: formatDateOnly(cohortCutoff),
                                })}
                            </span>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
