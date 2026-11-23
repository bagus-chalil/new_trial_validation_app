<?php

// Trial summary for approvers and reviewers on the per-trial Report Summary. The `facts.*` sentences
// are BOTH shown on the page and sent (as facts) to the Vision Service, so keep them plain, one
// statement each, and free of names.
return [
    'title' => 'Trial summary',
    'subtitle' => 'Compiled from the recorded trial data. It only helps you read; the decision stays with you.',
    'button' => 'Write AI summary',
    'button_busy' => 'Writing...',
    'wait_hint' => 'This can take 1-2 minutes (the AI server handles one request at a time).',
    'ai_label' => 'AI summary (suggestion)',
    'finding' => 'Finding',

    'errors' => [
        'unreachable' => 'The Vision Service cannot be reached from this server.',
        'outdated' => 'The Vision Service on the server has not been updated yet. Use the summary points below.',
        'rejected' => 'The AI summary was rejected by the Vision Service (it broke the advisory rule). Use the summary points below.',
        'failed' => 'The AI summary failed (Ollama/Vision Service gave no answer).',
        'busy' => 'The previous summary is still running, please wait a moment.',
        'network' => 'Could not reach the server.',
    ],

    'facts' => [
        'identity' => 'Trial :code, product :product (type :type), validation category :category, risk :risk.',
        'identity_anonymous' => 'Trial :code, product type :type, validation category :category, risk :risk.',
        'status' => 'Status :status, step :step, review round :round.',
        'revised' => 'This trial has been revised :count time(s).',
        'decision_note' => 'Approver decision note: :note',
        'validation_none' => 'No validation results yet.',
        'validation_counts' => 'Validation: :ok of :total parameters OK, :not_ok NOT OK, :na N/A.',
        'validation_missing' => ':count validation parameter(s) not filled in yet.',
        'not_ok' => 'NOT OK: :parameter, result ":result", remark ":remark".',
        'not_ok_more' => ':count more NOT OK parameter(s) not listed.',
        'weighing_none' => 'Weighing :section has not been filled in.',
        'weighing_skipped' => 'Weighing :section was not performed (skipped).',
        'weighing_data' => 'Weighing :section: :count samples, average :avg, min :min, max :max.',
        'photos' => 'Attached photos: :count.',
        'photos_none' => 'No attached photos yet.',
        'review_pending' => 'Review is waiting on departments: :departments.',
        'review_ok' => 'OK review without extra notes from: :departments.',
        'review_note' => 'Review note from :department: :note',
        'review_more' => ':count more review note(s) not listed.',
    ],
];
