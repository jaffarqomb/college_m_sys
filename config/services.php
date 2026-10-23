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

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),
    ],

    'tap' => [
        'secret_key' => env('TAP_SECRET_KEY'),
        'currency' => env('TAP_CURRENCY', 'KWD'),
        'base_url' => env('TAP_BASE_URL', 'https://api.tap.company/v2'),
    ],

    /*
    | SMS driver slot. The "log" driver writes messages to the dedicated
    | `sms` log channel; when `driver` is null no SMS is sent at all and
    | notifications fall back to mail only. Plug a provider package in here
    | later by adding its driver to App\Notifications\Channels\SmsChannel.
    */
    'sms' => [
        'driver' => env('SMS_DRIVER'),
        'from' => env('SMS_FROM', 'AutoCollege'),
    ],

];
