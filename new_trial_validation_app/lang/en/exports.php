<?php

// PDF (resources/views/pdf/*) and Excel exports. On-screen terms are reused
// from report.php / line_config.php; only export-specific text lives here.
return [
    // Carbon translatedFormat() patterns (App\Services\Pdf\ExportFormat).
    'formats' => [
        'datetime' => 'F j, Y, H:i',
        'date' => 'j M Y',
    ],

    'titles' => [
        'approved' => 'Approved Report',
        'rejected' => 'Rejected Report',
        'trial_summary' => 'Trial Summary Report',
        'department_review' => 'Department Review Report',
        'audit_print_log' => 'Audit Print Log',
        'line_config_version' => 'Line Configuration Report — :code (v:version)',
    ],

    'columns' => [
        'fg_code' => 'FG Code',
        'approved_date' => 'Approved Date',
        'rejected_date' => 'Rejected Date',
        'reason' => 'Reason / Final Remark',
        'printed_by' => 'Printed By',
        'printed_at' => 'Printed At',
        'report_type' => 'Report Type',
        'review_status' => 'Review Status',
        'pending_department' => 'Pending Department',
        'current_step' => 'Current Step',
        'created_date' => 'Created Date',
    ],

    'empty' => [
        'approved' => 'No approved reports yet.',
        'rejected' => 'No rejected reports yet.',
        'trial_summary' => 'No trial data.',
        'department_review' => 'No department review data yet.',
        'audit_print_log' => 'No audit print log entries yet.',
    ],

    'default_report_type' => 'Report',

    'attachments' => [
        'file_not_found' => 'File not found',
        'pdf_listed_only' => 'PDF file — download it from the Report page',
    ],

    'line_config' => [
        'version' => 'Version :version',
        'locked' => 'Locked (read-only)',
        'latest' => 'Latest',
        'return_prod' => 'Return (PROD)',
        'yes' => 'Yes',
    ],

    'excel' => [
        'sheets' => [
            'info' => 'Trial Info',
            'validation' => 'Validation',
            'weighing' => 'Weighing',
            'review' => 'Department Review',
            'decision' => 'Decision',
        ],
        'info_title' => 'Trial Information',
        'section' => 'Section',
        'reviewer' => 'Reviewer',
        'no_data' => 'No data.',
    ],
];
