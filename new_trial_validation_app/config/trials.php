<?php

return [
    /*
    | Trials created before this moment came from the legacy app's data
    | transfer and are flagged "Data lama" in the UI. Default = the day the
    | first trial with per-person review assignment (new-app-only) appeared.
    */
    'legacy_cutoff' => env('TRIAL_LEGACY_CUTOFF', '2026-09-21 00:00:00'),

    /*
    | Finished (Approved/Rejected) trials older than this many days — counted
    | from the decision date — are archived by `php artisan trials:auto-archive`.
    */
    'auto_archive_days' => (int) env('TRIAL_AUTO_ARCHIVE_DAYS', 365),
];
