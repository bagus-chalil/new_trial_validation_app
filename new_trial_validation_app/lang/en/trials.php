<?php

// Trial lists (per-status + tracking), the shared trials table, and the
// Need Review / Need Approval queues.
return [
    'table' => [
        'trial_code' => 'Trial ID',
        'product_name' => 'Product Name',
        'finish_good_code' => 'Finished Good Code',
        'product_type' => 'Product Type',
        'validation_scope' => 'Trial Type',
        'status' => 'Status',
        'progress' => 'Progress',
        'created_at' => 'Created Date',
        'pending_with' => 'Pending With',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_title' => 'Delete this draft trial?',
        'delete_description' => ':code will be moved to Trash. An Admin can restore it from the Trash menu.',
        'archive' => 'Archive',
        'archive_title' => 'Archive this trial?',
        'archive_description' => ':code will be hidden from the normal trial list. Its data stays intact and can be unarchived later.',
        'empty' => 'No trials match the selected filters.',
        'item_label' => 'trials',
    ],

    'list' => [
        'new_trial' => 'New Trial',
        'search_placeholder' => 'Trial ID, product, FG code, trial type, machine',
        'empty' => 'No trials on this page.',
        'filters' => [
            'search' => 'Search',
            'status' => 'Status',
            'product_type' => 'Product Type',
            'validation_scope' => 'Trial Type',
            'all_validation_scopes' => 'All trial types',
            'date_from' => 'Date From',
            'date_to' => 'Date To',
            'chip_from' => 'From',
            'chip_to' => 'To',
        ],
        'groups' => [
            'approved' => [
                'title' => 'Approved Trials',
                'subtitle' => 'Trials that have been approved.',
            ],
            'tracking' => [
                'title' => 'Process Tracking',
                'subtitle' => 'Monitor all trials currently in progress (In Review and Ready for Approval). This page is for monitoring only; review and approval actions are performed under Pending Review and Pending Approval.',
            ],
            'need_revision' => [
                'title' => 'Trials Requiring Revision',
                'subtitle' => 'Trials returned to Staff for revision.',
            ],
            'rejected' => [
                'title' => 'Rejected Trials',
                'subtitle' => 'Trials that have been finally rejected.',
            ],
            'draft' => [
                'title' => 'Draft Trials',
                'subtitle' => 'Trials that are still in draft.',
            ],
        ],
    ],

    'queue' => [
        'search_placeholder' => 'Search by trial ID or product',
        'trial' => 'Trial',
        'product' => 'Product',
        'product_type' => 'Product Type',
        'status' => 'Status',
    ],

    'reviews' => [
        'title' => 'Pending Review',
        'description' => 'Trials awaiting review by your department. All involved departments can submit their reviews on this page.',
        'round' => 'Round',
        'reviewer' => 'Reviewer',
        'comment' => 'Comment',
        'action' => 'Open & Review',
        'empty' => 'No pending reviews.',
        'item_label' => 'reviews',
        'status' => [
            'pending' => 'Pending',
            'reviewed' => 'Reviewed',
        ],
    ],

    'approvals' => [
        'title' => 'Pending Approval',
        'description' => 'Trials awaiting a final decision from the QAC Manager / approver. All assigned approvers can record their decision on this page.',
        'approver' => 'Approver',
        'action' => 'Open & Decide',
        'empty' => 'No trials are awaiting approval.',
    ],
];
