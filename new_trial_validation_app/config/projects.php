<?php

return [
    /*
    | Where the standalone Project Tracking app keeps its uploaded files
    | (its PT_FILES_DIR). Read-only source for `projects:import-legacy`.
    */
    'legacy_files_path' => env('PT_FILES_PATH', base_path('../Project-Tracking-PIE-Team/storage/files')),

    /*
    | Explicit name => user email map for legacy PIC/actor names that cannot be
    | matched automatically (exact, case-insensitive match on user name or
    | email). Unmapped names are kept as plain text and never create accounts.
    | Can also be supplied per run with --user-map=file.json.
    */
    'legacy_user_map' => [
        // 'Zahra' => 'zahra@cosmax.com',
    ],
];
