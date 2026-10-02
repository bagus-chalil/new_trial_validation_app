<?php

// Reports hub (reports/index) and its 5 report list pages.
return [
    'index' => [
        'title' => 'Reports',
        'description' => 'Select a trial validation report type.',
    ],

    'approved' => [
        'title' => 'Approved Report',
        'card_description' => 'List of approved trials.',
        'description' => 'Trials with Approved status.',
        'empty' => 'No approved reports yet.',
    ],

    'rejected' => [
        'title' => 'Rejected Report',
        'card_description' => 'List of rejected trials or trials requiring revision.',
        'description' => 'Trials given a final rejection by the QAC Manager.',
        'reason' => 'Reason / Final Remark',
        'empty' => 'No rejected reports yet.',
    ],

    'trial_summary' => [
        'title' => 'Trial Summary Report',
        'card_description' => 'Summary of all trials, with filters.',
        'description' => 'Summary of all trial validations.',
        'current_step' => 'Current Step',
        'all_product_types' => 'All product types',
        'all_scopes' => 'All scopes',
        'all_machines' => 'All machines',
        'product_name_placeholder' => 'Product name',
        'empty' => 'No trial data.',
        'steps' => [
            'header' => 'Header Information',
            'validation' => 'Validation',
            'weighing_packaging' => 'Weighing (Packaging)',
            'weighing_filling' => 'Weighing (Filling)',
            'attachment' => 'Attachments',
            'review' => 'Review',
            'approval' => 'Approval',
        ],
    ],

    'department_review' => [
        'title' => 'Department Review Report',
        'card_description' => 'Review progress by department.',
        'description' => 'Review progress by department.',
        'review_status' => 'Review Status',
        'pending_department' => 'Pending Department',
        'empty' => 'No department review data yet.',
    ],

    'audit_print_log' => [
        'title' => 'Audit Print Log',
        'card_description' => 'Report print log, where available.',
        'description' => 'Report print activity log, where available.',
        'printed_by' => 'Printed By',
        'printed_at' => 'Printed At',
        'report_type' => 'Report Type',
        'empty' => 'No audit print log entries yet.',
        'item_label' => 'log entries',
    ],

    'actions' => [
        'view_report' => 'View Report',
        'view_summary' => 'View Summary',
        'view_review' => 'View Review',
    ],
];
