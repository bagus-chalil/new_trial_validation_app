<?php

// Dashboard and "My Work" page strings.
return [
    'title' => 'Trial Dashboard',
    'description' => 'Overview of the trial validation system: process health, trends, and breakdowns.',

    'kpi' => [
        'no_data' => 'No data yet',
        'approval_rate' => 'Approval Rate',
        'approval_rate_caption' => 'of trials with a final decision',
        'avg_approval_time' => 'Average Approval Time',
        'avg_approval_time_caption' => 'from trial creation to approval',
        'days' => ':count days',
        'active_trials' => 'Active Trials',
        'active_trials_caption' => 'trials currently in progress',
        'bottleneck' => 'Bottleneck Department',
        'bottleneck_none' => 'None',
        'bottleneck_caption' => ':count reviews pending',
        'bottleneck_none_caption' => 'All reviews have been completed',
    ],

    'summary' => [
        'total' => 'Total Trials',
        'total_breakdown' => 'Mixing: :mixing · Filling: :filling',
    ],

    'charts' => [
        'trend_title' => 'Trials Created Over Time',
        'trend_series' => 'Trials Created',
        'status_title' => 'Status Distribution',
        'product_type_title' => 'Breakdown by Product Type',
        'product_type_share_title' => 'Trial Share by Product Type',
        'department_pending_title' => 'Pending Reviews by Department',
        'count_label' => 'Number of Trials',
        'other' => 'Other',
        'empty_trials' => 'No trials yet.',
        'empty_pending_reviews' => 'No pending reviews.',
    ],

    'filters' => [
        'search_placeholder' => 'Trial, product, FG code, category, scope, machine',
        'search' => 'Search',
        'status' => 'Status',
        'product_type' => 'Product Type',
        'trial_type' => 'Trial Type',
        'trial_type_placeholder' => 'All trial types',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'chip' => ':label: :value',
        'empty' => 'No trials match the selected filters.',
    ],

    'my_work' => [
        'description' => 'A summary of your tasks: drafts to continue, trials requiring revision, trials in progress, and reviews and approvals awaiting your action.',
        'drafts_title' => 'My Drafts (Continue)',
        'drafts_empty' => 'No drafts to continue.',
        'drafts_hint' => 'Click a Trial ID to continue filling in the form.',
        'revision_title' => 'Revision Required',
        'revision_empty' => 'No trials require revision.',
        'revision_hint' => 'Click a Trial ID to make corrections and resubmit.',
        'in_progress_title' => 'In Progress',
        'in_progress_empty' => 'You have no trials in progress.',
        'in_progress_hint' => 'Awaiting review/approval. No action is required from you at this time.',
        'reviews_title' => 'Awaiting My Review',
        'reviews_empty' => 'There are no reviews awaiting you at this time.',
        'reviews_view_all' => 'View all pending reviews',
        'approvals_title' => 'Awaiting My Approval',
        'approvals_empty' => 'There are no trials awaiting your approval at this time.',
        'approvals_view_all' => 'View all pending approvals',
        'waiting' => 'Pending with: :name',
        'more' => '+:count more trials.',
        'last' => 'Latest: :code',
        'last_decided' => 'Latest: :code (:decision)',
    ],
];
