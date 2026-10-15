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

        'summary' => [
            'intro' => 'Summary of the trials you can see: :total in total (:filling filling, :mixing mixing).',
            'none' => 'There are no trials you can see yet.',
        ],

        'my_trials' => [
            'total' => 'You created :total trial(s):filter.',
            'none' => 'You have not created any trial:filter.',
        ],

        'latest_trials' => [
            'intro' => 'The :count newest trial(s):filter:',
            'none' => 'No matching trials:filter.',
        ],

        'search_trials' => [
            'total' => ':total trial(s) match ":keyword":',
            'none' => 'No trial matches ":keyword" (or you do not have access to it).',
        ],

        'trial_not_ok' => [
            'intro' => 'Trial :code has :count NOT OK parameter(s) out of :total checked:',
            'item' => ':parameter - result: :result; remark: :remark',
            'none' => 'Trial :code has no NOT OK parameters.',
            'not_validated' => 'Trial :code has no validation results yet.',
        ],

        'trial_reviews' => [
            'intro' => 'Review status of trial :code (round :round):',
            'done' => ':dept: reviewed by :who on :date',
            'pending' => ':dept: not reviewed yet, waiting for :who',
            'none' => 'Trial :code has not been submitted for review.',
        ],

        'step_duration' => [
            'intro' => 'How long each stage takes (learned from past trials, typical / slow):',
            'item' => ':stage:scope: typically :typical, considered slow beyond :slow (:n trials)',
            'stages' => [
                'prepare' => 'Trial preparation (created until submitted)',
                'review' => 'Department review',
                'approval' => 'Final approval',
            ],
            'hours' => ':n hours',
            'days' => ':n days',
            'none' => 'There is not enough reliable duration data yet (needs at least 5 trials per stage).',
            'scope' => 'Based on automatic learning from past trial data.',
        ],

        'how_to' => [
            'choose' => 'What would you like to know how to do? Try one of these:',
            'topics' => [
                'create_trial' => "How to create a new trial:\n1. Open Trials > New Trial.\n2. Fill in the header (product, scope, machines, batch, etc.) and click Save & Continue.\n3. Continue in order: Validation, Weighing Packaging, Weighing Filling, Attachments, then Review & Submit.",
                'validation' => "How to fill in Validation:\n1. Pick a decision for each parameter: OK, NOT OK or N/A.\n2. For NOT OK, the result and remark are required.\n3. Every parameter must be filled in, then Save.",
                'weighing' => "How to fill in Weighing (Packaging, then Filling):\n1. Enter each sample weight (a number, not negative). Use Add Sample for more.\n2. If this section is not weighed, tick Skip.\n3. Save to continue to the next section.",
                'attachments' => "How to attach photos:\n1. Open the Attachments step and choose a category.\n2. Pick photos (JPG/PNG/WEBP/GIF, max 10 MB per file) and Upload.\n3. Photos can be deleted while the trial is still editable.",
                'submit_review' => "How to submit for review:\n1. Make sure Validation is complete, then open the Review & Submit step.\n2. Tick the reviewer departments and pick the person for each, then pick an approver.\n3. Click Submit for Review.",
                'do_review' => "How to do a review:\n1. Open Need Review and click a trial waiting for you.\n2. Study the data on the Report Summary page.\n3. Write your review comment in the review section and submit.",
                'approve' => "How to approve:\n1. Open Need Approval and click the trial assigned to you.\n2. After reading the Report Summary, choose Approve, Need Revision or Reject.\n3. Enter a comment and your account password as the e-signature.",
                'revision' => "How to handle Need Revision:\n1. Open Need Revision and click your trial.\n2. Fix the data according to the approver's comment.\n3. Submit again through Review & Submit.",
            ],
        ],

        'help' => [
            'text' => 'I am the trial assistant. I can answer the following, try one of these examples:',
        ],

        'unknown' => [
            'text' => 'Sorry, I did not understand that question yet. It has been recorded so an admin can teach it to me. Meanwhile, try one of these examples:',
        ],
    ],

    'widget' => [
        'placeholder' => 'Ask about trials…',
        'send' => 'Send',
        'close' => 'Close',
        'open' => 'Open assistant',
        'helpful' => 'Helpful',
        'not_helpful' => 'Not helpful',
        'thanks' => 'Thanks for the feedback.',
        'error' => 'Something went wrong. Please try again.',
        'try_asking' => 'Try asking:',
        'greeting' => 'Hi! Ask me about your tasks, trial status, or stuck trials.',
        'open_trial' => 'Open trial',
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
        'summary' => 'Give me a summary of all trials',
        'my_trials' => 'Show my trials that are Draft',
        'latest_trials' => 'Show the 5 latest trials',
        'search_trials' => 'Search trials Tube',
        'trial_not_ok' => 'Why is TRIAL-20260914-124439-QF1D NOT OK?',
        'trial_reviews' => 'Who has not reviewed TRIAL-20260914-124439-QF1D yet?',
        'step_duration' => 'How long does review usually take?',
        'how_to' => 'How do I create a new trial?',
    ],

    'how_to_examples' => [
        'create_trial' => 'How do I create a new trial?',
        'validation' => 'How to fill in validation?',
        'weighing' => 'How to fill in weighing?',
        'attachments' => 'How to upload attachment photos?',
        'submit_review' => 'How to submit for review?',
        'do_review' => 'How to do a review?',
        'approve' => 'How to approve a trial?',
        'revision' => 'How to handle a revision?',
    ],
];
