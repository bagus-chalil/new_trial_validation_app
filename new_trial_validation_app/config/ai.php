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
];
