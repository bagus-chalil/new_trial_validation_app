<?php

return [
    /*
    | Contract version of the trial document produced by `ai:export`.
    | Bump when resources/ai/trial_document.schema.json changes shape.
    */
    'schema_version' => '1.0',

    /*
    | People (emails/names) are always pseudonymized. Product identity is
    | internal-only by default; flip these before sending data to an external
    | model provider.
    */
    'anonymize_product' => (bool) env('AI_ANONYMIZE_PRODUCT', false),
    'include_bom' => (bool) env('AI_INCLUDE_BOM', true),

    'export_disk_path' => 'ai-export',

    /*
    | Written insights ("Tulis ringkasan AI" on the trial Report Summary). This app is only a
    | consumer: it sends a `kind` plus rule-based, anonymised facts to the Vision Service's
    | POST /api/insights/summarize, which owns the prompt. OFF by default, so a server whose Vision
    | Service has not been updated (or is not reachable) shows only the rule-based points.
    */
    'insights' => [
        'enabled' => (bool) env('VISION_INSIGHTS_ENABLED', false),
        'base_url' => env('VISION_SERVICE_URL', 'http://127.0.0.1:8000'),
        'timeout' => (int) env('VISION_LLM_TIMEOUT', 150),
        'connect_timeout' => (int) env('VISION_CONNECT_TIMEOUT', 5),
    ],
];
