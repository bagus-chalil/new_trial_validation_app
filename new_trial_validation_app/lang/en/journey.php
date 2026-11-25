<?php

// Trial Validation v2.0: Product Journey (Case) pages and related fields.
return [
    'title' => 'Product Journey',
    'description' => 'One trail per product: how many times it was trialled, the result of each attempt, and the documents linked to it.',
    'search_placeholder' => 'Case number, product, or FG code',
    'all_types' => 'All types',
    'empty' => 'No matching Cases yet.',

    'type' => [
        'NPD' => 'NPD',
        'IMP' => 'Improvement',
        'MIX' => 'Mixing',
    ],

    'result' => [
        'pass' => 'Pass',
        'pass_client' => 'Pass with Client Approval',
        'retrial' => 'Re-trial',
        'fail' => 'Fail',
        'pending' => 'Awaiting result',
    ],

    'columns' => [
        'case_no' => 'Case No.',
        'product' => 'Product',
        'fg' => 'FG Code',
        'type' => 'Type',
        'attempts' => 'Trials',
        'last_result' => 'Last Result',
    ],

    'summary' => [
        'attempts' => ':count trial(s)',
        'none' => 'No trial submitted yet',
        'last_result' => 'Last result',
        'lead_time' => 'Average lead time',
        'lead_time_value' => ':days days (:samples trials)',
        'lead_time_none' => 'No PM/Bulk ready dates entered yet',
        'first_trial' => 'First trial',
        'breakdown' => 'Result breakdown',
        'fg_master_note' => 'The FG code is read from the product master.',
    ],

    'attempt' => [
        'title' => 'Attempt #:no',
        'timeline' => 'Timeline',
        'trial_date' => 'Trial date',
        'decided_at' => 'Decided',
        'pending_with' => 'Waiting on',
        'revision' => 'Revision #:no',
        'pm_ready' => 'PM ready',
        'bulk_ready' => 'Bulk ready',
        'dates_defaulted' => 'Default dates (D-1), excluded from lead time',
        'lead_time' => 'Lead time :days days',
        'stale_fg' => 'FG code on the trial is outdated; master is used',
        'has_not_ok' => 'Has NOT OK parameters',
        'line_config' => 'Line Configuration: :status',
        'open_trial' => 'Open trial report',
        'private' => 'You do not have access to this trial',
    ],

    'line_config_status' => [
        'Draft' => 'Draft',
        'Submitted' => 'Submitted',
        'Approved' => 'Approved',
    ],

    'client' => [
        'title' => 'Client Approval',
        'approved' => 'Client approved',
        'note' => 'Note / evidence (letter number, name, date)',
        'evidence_hint' => 'Upload the document through the trial additional attachments, then note the reference here.',
        'save' => 'Save',
        'clear' => 'Remove approval',
        'edit' => 'Record client approval',
    ],

    'projects' => [
        'title' => 'Linked projects',
        'empty' => 'No project is linked to this Case yet.',
    ],

    'form' => [
        'pm_ready_date' => 'PM Ready Date',
        'bulk_ready_date' => 'Bulk Ready Date',
    ],

    'kpi' => [
        'title' => 'Realized Trial Lead Time (NPD)',
        'value' => ':days days',
        'none' => 'No data yet',
        'note' => 'Trial date minus the latest PM/Bulk ready date, from :samples NPD trials with real dates.',
        'excluded' => ':count trials use default dates and are excluded.',
    ],

    'toast' => [
        'client_approval_saved' => 'Client approval saved.',
    ],
];
