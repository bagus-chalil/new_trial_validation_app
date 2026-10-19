<?php

// Bottleneck monitor: read-only overview of every active trial for all users.
return [
    'title' => 'All Trials Overview',
    'description' => 'Every trial still in progress, regardless of who owns it, so you can see at which stage and with whom trials are held up. This page is view-only.',
    'stage_cards' => [
        'count' => ':count trials',
        'avg' => 'Average :days days without activity',
        'oldest' => 'Oldest :days days',
        'stale' => ':count trials past :days days',
    ],
    'holders' => [
        'title' => 'Where most trials are held up',
        'subtitle' => 'The party each trial is currently waiting on. Click a row to filter the list below.',
        'name' => 'Waiting on',
        'kind' => 'Type',
        'count' => 'Trials',
        'avg_days' => 'Average (days)',
        'oldest_days' => 'Oldest (days)',
        'empty' => 'There are no trials in progress.',
        'kinds' => [
            'owner' => 'Trial owner',
            'team' => 'Reviewer department',
            'approver' => 'Approver',
        ],
    ],
    'filters' => [
        'search' => 'Search',
        'status' => 'Status',
        'holder' => 'Waiting on',
    ],
    'search_placeholder' => 'Trial code or product name',
    'table' => [
        'trial_code' => 'Trial Code',
        'product_name' => 'Product Name',
        'status' => 'Status',
        'waiting_for' => 'Waiting on',
        'owner' => 'Owner',
        'idle' => 'Idle',
        'created_at' => 'Created Date',
        'no_access' => 'You do not have access to this trial\'s details.',
        'empty' => 'There are no trials in progress.',
        'item_label' => 'trials',
    ],
    'days' => ':count days',
    'legend' => 'Idle column colors: yellow from :warn days, red from :critical days since the trial\'s last activity.',
];
