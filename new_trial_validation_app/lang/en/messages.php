<?php

// Server-side messages: toast notifications after saving, and the custom
// validation / completeness messages built in FormRequests and Actions.
return [
    'toast' => [
        'trial_created' => 'Trial created successfully.',
        'trial_updated' => 'Trial updated successfully.',
        'trial_deleted' => 'Trial :code moved to Trash.',
        'validation_saved' => 'Validation saved successfully.',
        'weighing_saved' => 'Weighing saved successfully.',
        'photos_uploaded' => ':count photo(s) uploaded successfully.',
        'upload_partial' => 'Some files failed: :errors',
        'attachment_deleted' => 'Attachment deleted successfully.',
        'submitted_for_review' => 'Trial submitted for review successfully.',
        'review_saved' => 'Review saved successfully.',
        'approval_saved' => 'Approval decision saved successfully.',
        'additional_uploaded' => ':count additional attachment(s) uploaded successfully.',
        'additional_none_uploaded' => 'No files were uploaded.',
        'additional_deleted' => 'Additional attachment deleted successfully.',
        'line_config_saved' => 'Line Configuration Report saved successfully.',
        'line_config_draft_saved' => 'Line Configuration Report draft saved.',
        'line_config_submitted' => 'Line Configuration Report submitted for approval.',
        'line_config_confirmed' => ':label confirmed successfully.',
        'line_config_returned' => 'Line Configuration Report returned for revision.',
    ],

    'upload' => [
        'failed' => 'Upload of file #:number failed.',
        'too_large' => 'File :name exceeds 10 MB.',
        'not_image' => 'File :name is not an allowed image type.',
        'store_failed' => 'File :name could not be saved.',
    ],

    'completeness' => [
        'required' => ':field is required.',
        'no_parameters' => 'No validation parameters have been configured for product type :type yet.',
        'no_decision' => 'Parameter :name has no decision yet.',
        'not_ok_incomplete' => 'Parameter :name is NOT OK and must have a result and remark.',
    ],

    'validation' => [
        'parameter_missing' => 'Parameter :name has not been filled in.',
        'parameter_not_ok' => 'Parameter :name is NOT OK; Result and Remark are required.',
        'weighing_invalid' => 'Weighing samples must be numeric and cannot be negative.',
        'weighing_empty' => 'Please enter at least 1 weighing sample or tick Skip.',
        'submit_incomplete' => 'Cannot submit for review yet: :errors',
        'reviewer_required' => 'Select a reviewer for department :department.',
        'reviewer_invalid' => 'The selected reviewer is not valid for department :department.',
        'not_ready_for_approval' => 'This trial is not ready for approval yet.',
        'signature_wrong' => 'Incorrect e-signature password.',
        'return_reason_min_words' => 'The return reason must be at least 5 words.',
        'additional_required' => 'Select at least one file.',
        'additional_max_per_upload' => 'A maximum of :max files per upload.',
        'additional_too_large' => 'The maximum file size is 10 MB.',
        'additional_file_type' => 'Only PDF or image files (JPG, PNG, WEBP, GIF) are allowed.',
        'additional_remaining' => 'A maximum of :max attachments per trial — :remaining slot(s) left.',
        'additional_limit_reached' => 'The limit of :max additional attachments for this trial has been reached.',
    ],
];
