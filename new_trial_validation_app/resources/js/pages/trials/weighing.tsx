import { Form, Head, Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useMemo, useState } from 'react';
import TrialWeighingController from '@/actions/App/Http/Controllers/TrialWeighingController';
import Heading from '@/components/heading';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { TrialWizardSteps } from '@/components/trial-wizard-steps';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import {
    trialStatusBadgeClassName,
    trialStatusLabel,
} from '@/lib/trial-status';
import { dashboard } from '@/routes';
import validation from '@/routes/trials/validation';
import weighing from '@/routes/trials/weighing';

type Section = 'Packaging' | 'Filling';

type TrialData = {
    id: number;
    trial_code: string;
    current_step: string | null;
    attachments_count: number;
    progress_status: string;
    final_decision: string | null;
    product_type: string;
};

type PageProps = {
    trial: TrialData;
    section: Section;
    values: Record<string, string | null>;
    skip: boolean;
    canEdit: boolean;
};

// Port of legacy's section_display_name() in app/bootstrap.php, as
// translation keys. `section` itself stays the raw stored value.
const SECTION_LABEL_KEYS: Record<Section, string> = {
    Packaging: 'wizard.weighing.labels.packaging',
    Filling: 'wizard.weighing.labels.filling',
};

const WIZARD_STEP: Record<Section, number> = {
    Packaging: 3,
    Filling: 4,
};

function parseNumericValues(
    values: Record<number, string>,
    itemNumbers: number[],
): number[] {
    const parsed: number[] = [];

    for (const itemNo of itemNumbers) {
        const raw = values[itemNo];

        if (raw === undefined || raw.trim() === '') {
            continue;
        }

        const n = parseFloat(raw);

        if (!Number.isNaN(n)) {
            parsed.push(n);
        }
    }

    return parsed;
}

// Keyed by `section` from the parent so switching between Packaging and
// Filling (same Inertia page component, different props) fully unmounts and
// remounts this component instead of re-rendering it in place — otherwise
// every useState here keeps whichever section's data it was first
// initialized with, and the *other* section's page would silently show (and
// on submit, overwrite) stale data left over from before the switch.
export default function TrialWeighing(props: PageProps) {
    return <WeighingForm key={props.section} {...props} />;
}

function WeighingForm({
    trial,
    section,
    values,
    skip: initialSkip,
    canEdit,
}: PageProps) {
    const { t } = useTranslation();
    const sectionLabel = t(SECTION_LABEL_KEYS[section]);
    const { initialItemNumbers, initialMaxItemNo } = useMemo(() => {
        const keys = Object.keys(values).map((k) => parseInt(k, 10));
        const max = keys.length ? Math.max(30, ...keys) : 30;

        return {
            initialItemNumbers: Array.from({ length: max }, (_, i) => i + 1),
            initialMaxItemNo: max,
        };
    }, [values]);

    const [skip, setSkip] = useState(initialSkip);
    const [itemNumbers, setItemNumbers] =
        useState<number[]>(initialItemNumbers);
    const [sampleValues, setSampleValues] = useState<Record<number, string>>(
        () => {
            const initial: Record<number, string> = {};

            for (const [itemNo, value] of Object.entries(values)) {
                initial[parseInt(itemNo, 10)] = value ?? '';
            }

            return initial;
        },
    );

    function addSample() {
        setItemNumbers((prev) => [...prev, Math.max(0, ...prev) + 1]);
    }

    function removeSample(itemNo: number) {
        setItemNumbers((prev) => prev.filter((n) => n !== itemNo));
        setSampleValues((prev) => {
            const next = { ...prev };
            delete next[itemNo];

            return next;
        });
    }

    const stats = useMemo(() => {
        const nums = parseNumericValues(sampleValues, itemNumbers);

        return {
            total: nums.length,
            average: nums.length
                ? (nums.reduce((a, b) => a + b, 0) / nums.length).toFixed(2)
                : '-',
            min: nums.length ? Math.min(...nums).toFixed(2) : '-',
            max: nums.length ? Math.max(...nums).toFixed(2) : '-',
        };
    }, [sampleValues, itemNumbers]);

    const backHref =
        section === 'Packaging'
            ? validation.edit(trial.id).url
            : weighing.edit({ trial: trial.id, section: 'Packaging' }).url;

    const hasAnyValue = stats.total > 0;
    const showMissingSampleError = canEdit && !skip && !hasAnyValue;

    const grid = (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6">
            {itemNumbers.map((itemNo) => (
                <div key={itemNo} className="flex flex-col gap-1">
                    <div className="flex items-center justify-between gap-1">
                        <Label
                            htmlFor={`w-${itemNo}`}
                            className="text-xs text-muted-foreground"
                        >
                            {itemNo}
                        </Label>
                        {canEdit && !skip && itemNo > initialMaxItemNo && (
                            <button
                                type="button"
                                aria-label={t('wizard.weighing.remove_sample', {
                                    number: itemNo,
                                })}
                                className="text-muted-foreground hover:text-destructive"
                                onClick={() => removeSample(itemNo)}
                            >
                                <X className="size-3" />
                            </button>
                        )}
                    </div>
                    <Input
                        id={`w-${itemNo}`}
                        type="number"
                        step="0.001"
                        min="0"
                        inputMode="decimal"
                        name={`w[${itemNo}]`}
                        value={sampleValues[itemNo] ?? ''}
                        onChange={(e) =>
                            setSampleValues((prev) => ({
                                ...prev,
                                [itemNo]: e.target.value,
                            }))
                        }
                        disabled={!canEdit || skip}
                    />
                </div>
            ))}
        </div>
    );

    const statsBar = (
        <div className="flex flex-wrap gap-6 text-sm">
            <div>
                <span className="font-medium">
                    {t('report.weighing.total_sample')}:
                </span>{' '}
                {stats.total}
            </div>
            <div>
                <span className="font-medium">
                    {t('report.weighing.average')}:
                </span>{' '}
                {stats.average}
            </div>
            <div>
                <span className="font-medium">
                    {t('report.weighing.minimum')}:
                </span>{' '}
                {stats.min}
            </div>
            <div>
                <span className="font-medium">
                    {t('report.weighing.maximum')}:
                </span>{' '}
                {stats.max}
            </div>
        </div>
    );

    return (
        <>
            <Head
                title={t('wizard.weighing.page_title', {
                    label: sectionLabel,
                    code: trial.trial_code,
                })}
            />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('wizard.weighing.title', {
                            label: sectionLabel,
                        })}
                        description={t('wizard.weighing.description', {
                            code: trial.trial_code,
                        })}
                    />
                    <Badge
                        variant="outline"
                        className={trialStatusBadgeClassName(
                            trial.progress_status,
                            trial.final_decision,
                        )}
                    >
                        {trialStatusLabel(t, trial.progress_status)}
                    </Badge>
                </div>

                <TrialWizardSteps
                    currentStep={WIZARD_STEP[section]}
                    trial={trial}
                />

                {canEdit ? (
                    <Form
                        {...TrialWeighingController.update.form({
                            trial: trial.id,
                            section,
                        })}
                        options={{ preserveScroll: true }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            {t('wizard.weighing.title', {
                                                label: sectionLabel,
                                            })}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {errors.w && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {errors.w}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                        {showMissingSampleError && (
                                            <Alert variant="destructive">
                                                <AlertDescription>
                                                    {t(
                                                        'messages.validation.weighing_empty',
                                                    )}
                                                </AlertDescription>
                                            </Alert>
                                        )}

                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="skip"
                                                name="skip"
                                                value="1"
                                                checked={skip}
                                                onCheckedChange={(value) =>
                                                    setSkip(value === true)
                                                }
                                            />
                                            <Label htmlFor="skip">
                                                {t('wizard.weighing.skip', {
                                                    label: sectionLabel,
                                                })}
                                            </Label>
                                        </div>

                                        {grid}

                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            disabled={skip}
                                            onClick={addSample}
                                        >
                                            {t('wizard.weighing.add_sample')}
                                        </Button>

                                        {statsBar}
                                    </CardContent>
                                </Card>

                                <StickyActionBar>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        asChild
                                    >
                                        <Link href={backHref}>
                                            {t('common.actions.back')}
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {t('wizard.save_next')}
                                    </Button>
                                </StickyActionBar>
                            </>
                        )}
                    </Form>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('wizard.weighing.title', {
                                        label: sectionLabel,
                                    })}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="skip"
                                        checked={skip}
                                        disabled
                                    />
                                    <Label htmlFor="skip">
                                        {t('wizard.weighing.skip', {
                                            label: sectionLabel,
                                        })}
                                    </Label>
                                </div>
                                {grid}
                                {statsBar}
                            </CardContent>
                        </Card>

                        <StickyActionBar>
                            <Button type="button" variant="secondary" asChild>
                                <Link href={backHref}>
                                    {t('common.actions.back')}
                                </Link>
                            </Button>
                        </StickyActionBar>
                    </>
                )}
            </div>
        </>
    );
}

TrialWeighing.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Weighing', href: '#' },
    ],
};
