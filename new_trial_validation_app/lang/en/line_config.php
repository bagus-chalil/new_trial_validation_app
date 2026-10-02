<?php

return [
    'title' => 'Line Configuration Report',
    'dialog_title' => 'Line Configuration Report (Production)',
    'subtitle' => 'Production — line configuration, production standard, and :first → :second sign-off.',
    'empty' => 'No Line Configuration Report yet.',
    'no_data' => 'No data available.',

    'badge' => [
        'signed_off' => 'Sign-off Complete',
        'in_approval' => 'Approval in Progress',
    ],

    'actions' => [
        'edit' => 'Edit Report',
        'create' => 'Create Line Configuration Report',
        'add_row' => 'Add Row',
        'remove_row' => 'Remove row',
        'save' => 'Save Line Configuration Report',
        'approve' => 'Approve',
        'mark_checked' => 'Mark as Checked',
        'return' => 'Return',
        'download_pdf' => 'PDF',
    ],

    'return_note' => [
        'title' => 'Returned for Revision',
        'approver' => 'Approver',
    ],

    'versions' => [
        'title' => 'Version History',
        'hint' => 'Returned versions are locked here — they can be downloaded but no longer edited.',
        'locked_at' => 'Locked :date',
    ],

    'sign_off' => [
        'title' => 'Sign-off',
        'select_user' => 'Select user...',
        'search_user' => 'Search user...',
        'no_eligible_users' => 'No users currently meet the team requirement for :lane. Configure this under Access Rights / Lane Configuration.',
        'hint' => 'The selected users will receive an email and then approve/check directly on this page (sequentially — :second can only proceed after :first is complete). The date is recorded automatically at that time. Once either user is assigned, this form is locked (no longer editable) until it is returned.',
        'not_confirmed' => 'Not yet confirmed.',
        'confirmed_by' => 'Confirmed by :name',
        'confirmed_by_at' => 'Confirmed by :name on :date',
    ],

    'fields' => [
        'date' => 'Date',
        'client' => 'Client',
        'validation' => 'Validation',
        'pic' => 'PIC',
        'operator' => 'Operator',
        'total' => 'Total',
        'setting' => 'Setting',
        'pass' => 'PASS',
        'ng' => 'NG',
        'ng_hint' => 'Calculated automatically as Total − Setting − PASS (may be adjusted manually if needed).',
        'opinion' => 'Opinion',
    ],

    'sections' => [
        'production_standard' => 'Production Standard',
        'line_configuration' => 'Line Configuration',
    ],

    'columns' => [
        'line' => 'Line',
        'workers' => 'Workers',
        'capacity' => 'Capacity/Speed',
        'remark' => 'Remarks',
        'no' => 'No.',
        'equipment' => 'Equipment',
        'process' => 'Process',
        'worker' => 'Workers',
        'trial' => 'Trial',
    ],

    'total_workers' => 'Total Workers',
    'total_workers_inline' => 'Total workers:',

    'trial_status' => [
        'pass' => 'Pass',
        'no_trial' => 'No Trial',
    ],

    'stage' => [
        'prepared' => 'Prepared (PIE)',
        'not_filled' => 'Not yet filled in',
        'not_assigned' => 'Not yet assigned',
        'confirmed' => 'Confirmed',
        'waiting_for' => 'Awaiting :name',
        'awaiting_you' => 'Awaiting your action',
        'assigned_to' => 'Assigned to :name',
    ],

    'action_panel' => [
        'title' => 'Action Required: :lane',
        'description' => 'Confirm :lane for this report, or return it for revision.',
        'comment' => 'Comment',
        'comment_placeholder' => 'Comment (optional for confirmation, required for Return)...',
        'min_words' => 'Return requires at least :count words —',
    ],
];
