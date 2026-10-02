<?php

// Trial wizard pages (Header form, Validation, Weighing, Attachments, Review
// & Submit). Field labels shared with the Report page come from
// report.info / report.header / report.validation / report.weighing.
return [
    'save_next' => 'Save & Continue',
    'progress_note' => 'This trial has already progressed to the :step step.',

    'form' => [
        'title_new' => 'New Trial',
        'title_edit' => 'Edit Trial — :code',
        'description' => 'Complete the trial header information. Validation, weighing, review, and approval follow on separate screens.',
        'sections' => [
            'product' => 'Product Information',
            'scope' => 'Scope & Machines',
            'batch' => 'Batch & Team',
            'reason' => 'Reason & BOM',
        ],
        'product' => 'Product',
        'placeholders' => [
            'product' => 'Select product',
            'product_search' => 'Search product...',
            'product_type' => 'Select product type',
            'validation_category' => 'Select category',
            'risk_level' => 'Select risk level',
            'validation_scope' => 'Select scope',
            'validation_scope_search' => 'Search scope...',
            'machine_used' => 'Select machine',
            'machine_used_search' => 'Search machine...',
        ],
        'save_changes' => 'Save Changes',
    ],

    'validation' => [
        'page_title' => 'Validation — :code',
        'title' => 'Trial Parameter Validation — :type',
        'description' => 'Fill in the Decision, Result, and Remark for each validation parameter.',
        'no_parameters' => 'No validation parameters have been configured for this product type yet.',
    ],

    'weighing' => [
        'page_title' => 'Weighing :label — :code',
        'title' => 'Weighing :label',
        'description' => 'Enter the sampling results for trial :code.',
        'labels' => [
            'packaging' => 'Empty Packaging (gr)',
            'filling' => 'Filling Weight (gr)',
        ],
        'skip' => 'Skip :label (N/A)',
        'add_sample' => 'Add Sample',
        'remove_sample' => 'Remove sample :number',
    ],

    'attachments' => [
        'page_title' => 'Attachments — :code',
        'title' => 'Attachments',
        'description' => 'Upload and manage photo evidence for trial :code.',
        'upload_title' => 'Upload Photos',
        'category' => 'Category',
        'category_placeholder' => 'Select category...',
        'category_search' => 'Search category...',
        'caption' => 'Caption (Optional)',
        'caption_placeholder' => 'Add a short description of this photo...',
        'photos' => 'Photos',
        'upload' => 'Upload',
        'remove' => 'Remove',
        'delete' => 'Delete',
        'delete_confirm' => 'Remove this photo?',
        'readonly' => 'Attachments are read-only. Photos can only be deleted while the status is Draft or Need Revision.',
        'photo_count' => ':count photo(s)',
        'empty' => 'No attachments yet.',
        'continue_review' => 'Continue to Review',
    ],

    'review' => [
        'page_title' => 'Review — :code',
        'title' => 'Review & Submit',
        'description' => 'Submit trial :code for review by the relevant departments.',
        'status_title' => 'Department Review Status',
        'assigned_to' => 'Assigned To',
        'not_ready' => 'Not ready to submit for review',
        'select_title' => 'Select Review Department / Team',
        'no_reviewer' => 'No users are assigned to review team :department yet. Set this up in Access Rights.',
        'reviewer_placeholder' => 'Select reviewer...',
        'reviewer_search' => 'Search reviewer...',
        'approver' => 'Approver',
        'approver_placeholder' => 'Select approver...',
        'approver_search' => 'Search approver...',
        'view_detail' => 'View Trial Detail',
        'submit' => 'Submit for Review',
    ],
];
