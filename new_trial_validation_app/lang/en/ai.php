<?php

// Rule-based trial bot: answer templates (App\Services\Ai\Bot\BotAnswerComposer).
// Placeholders use :name. Every key must exist in id/en/ko.
return [
    'title' => 'Trial Assistant',

    'answers' => [
        'my_queue' => [
            'intro' => 'Here is what is on your plate right now:',
            'draft' => ':count Draft trial(s) for you to continue.',
            'need_revision' => ':count Need Revision trial(s) for you to fix.',
            'in_progress' => ':count of your trial(s) are in progress (In Review / Ready for Approval), nothing for you to do.',
            'pending_reviews' => ':count review(s) waiting for you.',
            'pending_approvals' => ':count trial(s) waiting for your approval.',
            'empty' => 'Nothing is waiting for you right now.',
        ],

        'trials_by_status' => [
            'total' => 'There are :total trial(s):filter.',
            'none' => 'No matching trials:filter.',
            'latest' => 'Latest:',
            'filter_status' => ' with status :status',
            'filter_type' => ' for product type :type',
            'more' => '(showing the top :shown)',
        ],

        'trial_status' => [
            'found' => 'Trial :code (:product): status :status, step :step.',
            'pending_with' => 'Currently waiting for :who.',
            'revision' => 'Revision no. :no.',
            'not_found' => 'Trial :code was not found or you do not have access to it.',
        ],

        'stuck_trials' => [
            'total' => ':total trial(s) have not moved for more than :days days.',
            'none' => 'No trials are stuck for more than :days days.',
            'item' => ':code (:product) - :status, waiting for :who, last updated :date',
            'nobody' => 'no assignee yet',
        ],

        'not_ok_hotspots' => [
            'intro' => 'Parameters that most often come out NOT OK:',
            'item' => ':type / :parameter: :count of :total trials (:rate%)',
            'none' => 'No NOT OK pattern is strong enough to report yet (at least 5 trials per group are needed).',
            'scope' => 'Based on automatic learning from past trial data.',
        ],

        'weighing_norm' => [
            'found' => 'For :type :section, the normal spread between weighing samples (coefficient of variation) is about :median%. Above :high% is considered unusual. Based on :n trials, confidence :confidence.',
            'none' => 'There is no reliable weighing norm for :type :section yet (at least 5 trials that actually weighed are needed).',
            'need_type' => 'Please name the product type and section, e.g. "weighing spread Tube Packaging".',
        ],

        'unknown' => [
            'text' => 'Sorry, I did not understand that question. Try one of these examples:',
        ],
    ],

    'confidence' => [
        'rendah' => 'low',
        'sedang' => 'medium',
        'tinggi' => 'high',
    ],

    'examples' => [
        'my_queue' => 'What are my tasks today?',
        'trials_by_status' => 'How many trials are In Review?',
        'trial_status' => 'Status of TRIAL-20260914-124439-QF1D',
        'stuck_trials' => 'Which trials have been stuck for more than 14 days?',
        'not_ok_hotspots' => 'Which parameters fail the most?',
        'weighing_norm' => 'What is the normal weighing spread for Tube Packaging?',
    ],
];
