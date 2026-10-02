<?php

// Login / password-reset screens, the guest layout, and the account Settings
// pages (profile, security, appearance).
return [
    'auth_layout' => [
        'back_to_portal' => 'Back to Portal',
        'tagline' => 'Manage production trials from submission and cross-department review through to approval and reporting — in a single workflow.',
        'highlights' => [
            'form' => 'Trial form, parameter validation & weighing',
            'review' => 'Tiered review by department',
            'approval' => 'Approval, reports, and activity history',
        ],
        'copyright' => '© :year Cosmax Indonesia. All rights reserved.',
    ],

    'login' => [
        'head' => 'Log in',
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'email' => 'Email address',
        'password' => 'Password',
        'forgot' => 'Forgot your password?',
        'remember' => 'Remember me',
        'submit' => 'Log in',
    ],

    'forgot_password' => [
        'head' => 'Forgot password',
        'title' => 'Forgot password',
        'description' => 'Enter your email to receive a password reset link',
        'email' => 'Email address',
        'submit' => 'Email password reset link',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],

    'reset_password' => [
        'head' => 'Reset password',
        'title' => 'Reset password',
        'description' => 'Please enter your new password below',
        'email' => 'Email',
        'password' => 'Password',
        'confirm' => 'Confirm password',
        'submit' => 'Reset password',
    ],

    'password_input' => [
        'show' => 'Show password',
        'hide' => 'Hide password',
    ],

    'appearance_tabs' => [
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],

    'settings' => [
        'title' => 'Settings',
        'description' => 'Manage your profile and account settings',
        'nav' => [
            'profile' => 'Profile',
            'security' => 'Security',
            'appearance' => 'Appearance',
        ],
        'save' => 'Save',
    ],

    'profile' => [
        'head' => 'Profile settings',
        'title' => 'Profile',
        'description' => 'Update your name and email address',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
    ],

    'security' => [
        'head' => 'Security settings',
        'title' => 'Update password',
        'description' => 'Ensure your account is using a long, random password to stay secure',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
    ],

    'appearance' => [
        'head' => 'Appearance settings',
        'title' => 'Appearance settings',
        'description' => 'Update the appearance settings for your account',
    ],

    'delete_user' => [
        'title' => 'Delete account',
        'description' => 'Delete your account and all of its resources',
        'warning' => 'Warning',
        'warning_text' => 'Please proceed with caution, this cannot be undone.',
        'button' => 'Delete account',
        'confirm_title' => 'Are you sure you want to delete your account?',
        'confirm_description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
        'password' => 'Password',
    ],

    'header' => [
        'navigation_menu' => 'Navigation menu',
        'search' => 'Search',
        'repository' => 'Repository',
        'documentation' => 'Documentation',
    ],

    'alert_error' => [
        'title' => 'Something went wrong.',
    ],

    'toast' => [
        'profile_updated' => 'Profile updated.',
        'password_updated' => 'Password updated.',
    ],
];
