/** Badge colours for an IM revision status (QA lifecycle). */
export function imStatusClassName(status: string): string {
    switch (status) {
        case 'Effective':
            return 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200';
        case 'Scheduled':
            return 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200';
        case 'In Review':
        case 'Ready for Approval':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
        case 'Need Revision':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200';
        case 'Rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200';
        case 'Superseded':
            return 'bg-muted text-muted-foreground line-through';
        default:
            return 'bg-muted text-foreground';
    }
}

/** Sections of the IM worksheet, in the order of the paper IM. */
export const IM_SECTIONS = [
    'bom',
    'filling_point',
    'appearance_point',
    'weight',
    'coding',
    'packing_point',
    'surveillance_initial',
    'surveillance_periodic',
] as const;

export type ImSection = (typeof IM_SECTIONS)[number];

export const IM_CONTENT_KEYS = [
    'master_box_qty',
    'pallet_layout',
    'shipper_format',
    'batch_format_tube',
    'batch_format_unit_box',
    'batch_format_master_box',
    'legal_note',
    'notes',
] as const;
