<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // QAC Super Apps portal (static chooser in portal/). Null = derive it from
    // the current request's scheme+host on the default port, which is where
    // the portal lives in production (https://<server-ip>).
    'portal' => [
        'url' => env('PORTAL_URL'),
    ],

    // new_trial_validation_app's server-to-server API (same host) for the Status Trial per Produk report.
    // Token must match INTERNAL_API_TOKEN there; an empty token makes the report say the status is unavailable.
    'trial_app' => [
        'url' => env('TRIAL_API_URL', 'http://127.0.0.1:9001'),
        'token' => env('TRIAL_API_TOKEN'),
        'timeout' => (int) env('TRIAL_API_TIMEOUT', 3),
        'cache_seconds' => (int) env('TRIAL_API_CACHE_SECONDS', 15),
        // An unfinished trial idle this many days is flagged "perlu perhatian" on the Status Trial report.
        'stale_days' => (int) env('TRIAL_STALE_DAYS', 90),
    ],

];
