<?php

// Notification emails (app/Mail/*, resources/views/emails/*). Rendered in
// the language of the user whose action triggered the email.
return [
    'common' => [
        'greeting' => 'Hi :name,',
        'trial_code' => 'Trial Code',
        'product' => 'Product',
        'thanks' => 'Thanks,',
    ],

    'review_requested' => [
        'subject' => 'Trial :code Needs Your Review',
        'heading' => 'Trial Needs Your Review',
        'intro' => 'A trial has been submitted for review by the :department department, and you have been assigned as the reviewer.',
        'button' => 'Open & Review Trial',
        'outro' => 'Please click the button above to view the trial details and submit your review.',
    ],

    'approval_requested' => [
        'subject' => 'Trial :code Waiting for Your Approval',
        'heading' => 'Trial Waiting for Your Approval',
        'intro' => 'All department reviewers have completed their review for the following trial, and it is now waiting for your approval.',
        'button' => 'Open & Approve Trial',
        'outro' => 'Please click the button above to view the trial details and submit your approval decision.',
    ],

    'line_config_signoff' => [
        'subject' => 'Line Configuration Report — :label Sign-Off Needed (:code)',
        'heading' => 'Line Configuration Report: :label Sign-Off',
        'intro' => 'You\'ve been named :label on the :report for the trial below — this is the production-line setup form attached to the trial, not the trial\'s own review/approval decision. No action is needed on the overall trial itself, only a sign-off on this specific report.',
        'button' => 'Open Line Configuration Report',
        'outro' => 'Please click the button above, scroll to the :report section, then use its :label button to confirm — the date will be recorded automatically.',
    ],

    'line_config_returned' => [
        'subject' => 'Line Configuration Report Returned for Revision (:code)',
        'heading' => 'Line Configuration Report Returned for Revision',
        'intro' => 'The :report you filled in for the trial below has been sent back for revision — this is only about that report, not the trial\'s own review/approval decision.',
        'intro_by' => 'The :report you filled in for the trial below has been sent back for revision by :stage — this is only about that report, not the trial\'s own review/approval decision.',
        'reason' => 'Reason for return:',
        'button' => 'Open Line Configuration Report',
        'outro' => 'Please revise the report and resubmit it for sign-off.',
    ],
];
