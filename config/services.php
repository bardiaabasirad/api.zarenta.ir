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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'sms' => [
        'base_url' => env('SMS_BASE_URL'),
        'token' => env('SMS_TOKEN'),
    ],

    'hamtala' => [
        'group_id' => env('HAMTALA_GROUP_ID'),
        'base_url' => env('HAMTALA_BASE_URL'),
        'app_key'  => env('HAMTALA_APP_KEY'),
    ],

    'kimia_api' => [
        'base_url' => env('KIMIA_API_BASE_URL', 'http://188.121.117.125:11000/api'),
        'username' => env('KIMIA_API_USERNAME'),
        'password' => env('KIMIA_API_PASSWORD'),
    ],

    'internal' => [
        'secret' => env('INTERNAL_API_SECRET'),
    ],

    'jibit' => [
        'ppg' => [
            'base_url' => env('JIBIT_PPG_BASE_URL', 'https://napi.jibit.ir/ppg/v3'),
        ],
    ],
];
