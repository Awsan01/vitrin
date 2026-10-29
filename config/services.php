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

    /*
    | Paddle sells the credit packs as merchant of record. Sandbox and live
    | are separate accounts, each with its own token, key, secret and prices.
    */
    'paddle' => [
        // "sandbox" (test cards, no real money) or "production".
        'environment' => env('PADDLE_ENVIRONMENT', 'sandbox') === 'production' ? 'production' : 'sandbox',
        // Public token for Paddle.js: Developer tools > Authentication > Client-side tokens.
        'client_token' => env('PADDLE_CLIENT_TOKEN'),
        // Server-side API key. Reads the prices shown on the site, checks
        // payments and fetches invoices, so it needs the "price.read" and
        // "transaction.read" permissions; add "notification_setting.read" so
        // `php artisan paddle:check` can check the webhook destination too.
        'api_key' => env('PADDLE_API_KEY'),
        // Secret of the notification destination that points at /webhooks/paddle.
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        // How old a webhook signature may be, in seconds. Paddle's own default
        // is 5; a wider window keeps payments flowing if the server clock
        // drifts, and replays are harmless because fulfilment is idempotent.
        'webhook_tolerance' => (int) env('PADDLE_WEBHOOK_TOLERANCE', 300),
    ],

];
