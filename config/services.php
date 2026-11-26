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

    // The public Next.js site (the spravato-app repository): canonical base
    // URL, the shared secret for the on-publish revalidation webhook
    // (docs/ARCHITECTURE.md §6), the token that lets the site forward a
    // visitor's IP with a form post (App\Support\Http\SiteClient), and where
    // its checkout lives for `composer dev`.
    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
        'dev_path' => env('SITE_DIR', base_path('../spravato-app')),
        'revalidate_secret' => env('FRONTEND_REVALIDATE_SECRET'),
        'site_token' => env('FRONTEND_SITE_TOKEN'),
    ],

    'turnstile' => [
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
