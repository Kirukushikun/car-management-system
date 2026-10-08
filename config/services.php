<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Central organization login (bfcgroup.ph) — see config/login.php for when each path runs.
    'auth_api' => [
        'base_uri' => env('AUTH_API_BASE_URI', ''),
        'api_key' => env('AUTH_API_KEY', ''),
        'auth_user_api_key' => env('AUTH_USER_API_KEY', ''),
    ],

    // Central user directory, for granting access in Users & Roles.
    'user_api' => [
        'endpoint' => env('USER_API_ENDPOINT', ''),
        'key' => env('USER_API_KEY', ''),
    ],

    // Turnstile credentials only. Whether it runs is decided in config/login.php.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret' => env('TURNSTILE_SECRET_KEY', ''),
    ],

];
