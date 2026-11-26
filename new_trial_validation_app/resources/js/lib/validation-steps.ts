/**
 * Wizard steps per Case kind (v2.0 Tahap 4). The Trial wizard keeps its own
 * six steps (lib/trial-wizard.ts); Mixing has no Weighing. Mirrors
 * App\Support\ValidationStepConfig on the PHP side.
 */
export type MixingStepKey = 'header' | 'validation' | 'attachments' | 'review';

export type MixingStep = {
    key: MixingStepKey;
    number: number;
    labelKey: string;
};

export const MIXING_STEPS: MixingStep[] = [
    { key: 'header', number: 1, labelKey: 'mixing.steps.header' },
    { key: 'validation', number: 2, labelKey: 'mixing.steps.validation' },
    { key: 'attachments', number: 3, labelKey: 'mixing.steps.attachments' },
    { key: 'review', number: 4, labelKey: 'mixing.steps.review' },
];

export const mixingUrl = {
    index: () => '/mixings',
    create: () => '/mixings/create',
    show: (id: number) => `/mixings/${id}`,
    edit: (id: number) => `/mixings/${id}/edit`,
    validation: (id: number) => `/mixings/${id}/validation`,
    attachments: (id: number) => `/mixings/${id}/attachments`,
    review: (id: number) => `/mixings/${id}/review`,
    file: (id: number, fileId: number) => `/mixings/${id}/files/${fileId}`,
    step: (key: MixingStepKey, id: number) =>
        ({
            header: `/mixings/${id}/edit`,
            validation: `/mixings/${id}/validation`,
            attachments: `/mixings/${id}/attachments`,
            review: `/mixings/${id}/review`,
        })[key],
};

/** How many wizard steps are done, from the real status/current step. */
export function resolveMixingCompletedSteps(
    status: string,
    currentStep: string | null,
    filesCount: number,
): number {
    if (status !== 'Draft') {
        return 4;
    }

    if (currentStep === 'Attachment') {
        return filesCount > 0 ? 3 : 2;
    }

    return currentStep === 'Validation' ? 1 : 1;
}

export function mixingStatusClassName(status: string): string {
    switch (status) {
        case 'In Review':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200';
        case 'Ready for Approval':
        case 'Need Revision':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
        case 'Approved':
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
        case 'Rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
        default:
            return 'bg-muted text-muted-foreground';
    }
}
