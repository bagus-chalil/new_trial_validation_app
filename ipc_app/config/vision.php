<?php

/*
 * Vision Service (OCR) integration — see integrations/laravel-ipc/README.md in the
 * vision-service repo. Vision Service only returns what it read + confidence + a
 * format flag; the PASS / FAIL / REVIEW decision is made here in Laravel
 * (App\Services\Vision\VisionEvaluator), per the settled IPC architecture.
 */

// Field type for the Startup Primary Coding read. `coding_formula` (default) finds batch + EXP by the
// SKU's coding formula and reads both tube photos in ONE call (file + file2). Set it to `coding_print`
// to roll back to the old per-photo path (coding_print + tube_exp_date + tube_exp_llm).
$startupCodingType = env('VISION_STARTUP_CODING_FIELD_TYPE', 'coding_formula');

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
        // EXP emboss of a tube crimp: YOLO crop + LLM, OK only when >=3 of 4 sources agree.
        // Advisory only; never a PASS (see StartupVisionEvaluator::tubeLlmItem).
        'tube_exp_llm',
        // Batch + EXP by coding formula (catalog / per-SKU), 1 or 2 photos per call (file2).
        // EXP comes back as date + granularity (day / month); see StartupVisionEvaluator::codingFormula.
        'coding_formula',
    ],

    // LLM field_types can take 25-80s for an OK read (up to 4 sources x 4 views on the GPU
    // server) plus queueing, so they get a longer HTTP timeout than the OCR ones.
    'llm_timeout' => (int) env('VISION_LLM_TIMEOUT', 150),

    // Startup AI cross-check (advisory only — never blocks the operator, never fills a
    // Conform / Not Conform toggle). Maps a Startup Check photo field to the Vision Service
    // field_type. Laravel always chooses the field_type; it is never guessed from the photo.
    // Off by default so a deploy without a reachable Vision Service stays unaffected.
    'startup_ai' => [
        'enabled' => (bool) env('VISION_STARTUP_AI_ENABLED', false),
        'fields' => [
            'date_bulk' => 'label_bulk',
            'im_number' => 'im_document',
            'coding_primary' => $startupCodingType,
            // Optional 2nd photo (tube: the other side / another angle). With coding_formula it is NOT
            // read on its own: it goes as `file2` in the same call as coding_primary (null here = no own read).
            'coding_primary_2' => $startupCodingType === 'coding_formula' ? null : 'coding_print',
        ],
        // Tube photos (packaging_type = tube) are also read through these tube field_types,
        // for display only: a tube result is always REVIEW, never PASS.
        'tube_field_types' => ['tube_exp_date'],
        // Extra LLM read of the tube crimp EXP (advisory, logged as its own ipc_logs row).
        // Set to an empty value to turn it off.
        'tube_llm_field_type' => env('VISION_TUBE_LLM_FIELD_TYPE', 'tube_exp_llm'),
        // The two tube reads above are the OLD path. With coding_formula they are skipped unless this
        // is true (kept for rollback/comparison, not deleted). With coding_print they always run.
        'legacy_tube_reads' => (bool) env('VISION_STARTUP_LEGACY_TUBE_READS', false),
        // Policy kept from coding_print: a tube Primary Coding result is always REVIEW, never PASS/FAIL.
        // Only applies to coding_formula; set false to judge tube like non-tube (user decision pending).
        'tube_force_review' => (bool) env('VISION_STARTUP_TUBE_FORCE_REVIEW', true),
    ],

    // "Analisis AI" on the admin field-issue log (Log Kendala): an anonymised digest of open issues goes
    // to the Vision Service text endpoint (same Ollama GPU route as the OCR reads). Advisory text only.
    // Off by default, like startup_ai, so a deploy without a reachable/updated Vision Service is unaffected.
    'issue_ai' => [
        'enabled' => (bool) env('VISION_ISSUE_AI_ENABLED', false),
    ],

    // "Tulis ringkasan AI" on the approver's batch page: the rule-based digest of one batch goes to the Vision
    // Service text endpoint (same Ollama route) and comes back as a short paragraph. Advisory text only, the
    // rule-based points are always shown without it. Follows VISION_STARTUP_AI_ENABLED unless set on its own,
    // so a server whose Vision Service is already wired needs no extra variable.
    'batch_summary' => [
        'enabled' => (bool) env('VISION_BATCH_SUMMARY_ENABLED', env('VISION_STARTUP_AI_ENABLED', false)),
    ],

    // "Tulis ringkasan AI" on the approver's batch page: the rule-based digest of one batch goes to the Vision
    // Service text endpoint (same Ollama route) and comes back as a short paragraph. Advisory text only, the
    // rule-based points are always shown without it. Follows VISION_STARTUP_AI_ENABLED unless set on its own,
    // so a server whose Vision Service is already wired needs no extra variable.
    'batch_summary' => [
        'enabled' => (bool) env('VISION_BATCH_SUMMARY_ENABLED', env('VISION_STARTUP_AI_ENABLED', false)),
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
