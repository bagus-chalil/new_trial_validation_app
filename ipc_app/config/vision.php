<?php

/*
 * Vision Service (OCR) integration — see integrations/laravel-ipc/README.md in the
 * vision-service repo. Vision Service only returns what it read + confidence + a
 * format flag; the PASS / FAIL / REVIEW decision is made here in Laravel
 * (App\Services\Vision\VisionEvaluator), per the settled IPC architecture.
 */
return [

    'base_url' => env('VISION_SERVICE_URL', 'http://100.100.160.31:8000'),

    // One OCR request takes ~25s on the pilot VPS and the service processes requests one at
    // a time (no parallelism yet), so a second station waiting in line can take ~50s+.
    'timeout' => (int) env('VISION_SERVICE_TIMEOUT', 90),

    'connect_timeout' => (int) env('VISION_SERVICE_CONNECT_TIMEOUT', 5),

    // field_types this app is allowed to send. Must exist in the Vision Service's
    // emboss_format_patterns.json. Laravel always decides the field_type from the workflow
    // step — Vision Service never guesses it from the photo.
    'field_types' => [
        'tube_exp_date',
        'tube_mfd_date',
        'tube_emboss_default',
        'tube_batch_code',
    ],

    // LLM-backed field_types (Ollama GPU server behind the Vision Service). They return a
    // different payload than the tube OCR ones (no extracted_date_code / format_valid), so
    // they are NOT in 'field_types' above and never go through VisionEvaluator /
    // the generic vision.analyze endpoint — only through StartupVisionEvaluator.
    'llm_field_types' => [
        'label_bulk',
        'im_document',
        'coding_print',
    ],

    // Startup AI cross-check (advisory only — never blocks the operator, never fills a
    // Conform / Not Conform toggle). Maps a Startup Check photo field to the Vision Service
    // field_type. Laravel always chooses the field_type; it is never guessed from the photo.
    // Off by default so a deploy without a reachable Vision Service stays unaffected.
    'startup_ai' => [
        'enabled' => (bool) env('VISION_STARTUP_AI_ENABLED', false),
        'fields' => [
            'date_bulk' => 'label_bulk',
            'im_number' => 'im_document',
            'coding_primary' => 'coding_print',
        ],
        // Tube photos (packaging_type = tube) are also read through these tube field_types,
        // for display only: a tube result is always REVIEW, never PASS.
        'tube_field_types' => ['tube_exp_date'],
    ],

    // How each field_type's OCR value is checked against IPC data. A field_type with no rule
    // here always ends up REVIEW (never PASS) until a rule is added.
    //   batch_exp_date      : OCR'd EXP (DDMMYY) must equal ipc_batches.exp_date
    //   mfd_plus_shelf_life : OCR'd MFD + master_products.shelf_life_months must equal
    //                         ipc_batches.exp_date
    'rules' => [
        'tube_exp_date' => 'batch_exp_date',
        'tube_mfd_date' => 'mfd_plus_shelf_life',
    ],

];
