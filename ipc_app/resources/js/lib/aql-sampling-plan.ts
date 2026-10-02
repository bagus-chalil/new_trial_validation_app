// Client mirror of App\Services\AqlSamplingPlan (the paper AQL single sampling table at the QC
// Finished Good station) — keep both in sync. The server re-derives these on save, so this only
// drives the live, read-only display while Quantity WI is typed.

// [lot size from, sample size, special inspection, critical, major, minor], ascending.
const TABLE: [number, number, number, number, number, number][] = [
    [91, 20, 3, 0, 1, 1],
    [151, 32, 3, 0, 1, 2],
    [281, 50, 3, 0, 2, 3],
    [501, 80, 5, 0, 3, 5],
    [1201, 125, 5, 0, 5, 7],
    [3201, 200, 5, 0, 7, 10],
    [10001, 315, 5, 0, 10, 14],
    [35001, 500, 8, 0, 14, 21],
    [150001, 800, 8, 0, 21, 21],
    [500001, 1250, 8, 0, 21, 21],
];

// Below the first row the whole lot is inspected (100%), zero defects accepted.
const FULL_INSPECTION_BELOW = 91;

export const AQL_DERIVED_FIELDS = [
    'quantity_sampling_aql',
    'quantity_sample_aql_cd',
    'quantity_sample_aql_md',
    'quantity_sample_aql_mnd',
    'quantity_special_inspection',
    'quantity_special_inspection_cd',
    'quantity_special_inspection_md',
    'quantity_special_inspection_mnd',
] as const;

export type AqlDerivedField = (typeof AQL_DERIVED_FIELDS)[number];

export function aqlPlanForLotSize(lotSize: number): Record<AqlDerivedField, number> | null {
    if (!Number.isInteger(lotSize) || lotSize <= 0) return null;

    let sample: number, special: number, critical: number, major: number, minor: number;
    if (lotSize < FULL_INSPECTION_BELOW) {
        [sample, special, critical, major, minor] = [lotSize, Math.min(3, lotSize), 0, 0, 0];
    } else {
        const row = [...TABLE].reverse().find(([from]) => lotSize >= from)!;
        [, sample, special, critical, major, minor] = row;
    }

    return {
        quantity_sampling_aql: sample,
        quantity_sample_aql_cd: critical,
        quantity_sample_aql_md: major,
        quantity_sample_aql_mnd: minor,
        quantity_special_inspection: special,
        quantity_special_inspection_cd: 0,
        quantity_special_inspection_md: 0,
        quantity_special_inspection_mnd: 0,
    };
}
