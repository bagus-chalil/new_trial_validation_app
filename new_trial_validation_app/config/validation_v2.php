<?php

return [
    // Brief 03: trials from this date are eligible for the integrated flow;
    // earlier ones stay historical (no automatic backfill into new KPIs).
    'cohort_start' => env('VALIDATION_COHORT_START', '2026-11-16'),

    // Brief 02 approval controls: nobody signs, reviews or approves a
    // document they prepared (Admin included). Turn off only for a
    // single-person test environment.
    'prevent_self_approval' => env('VALIDATION_PREVENT_SELF_APPROVAL', true),
];
