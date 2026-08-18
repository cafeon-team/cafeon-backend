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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'kakao' => [
        'client_id' => env('KAKAO_CLIENT_ID'),
        'client_secret' => env('KAKAO_CLIENT_SECRET'),
        'redirect' => env('KAKAO_REDIRECT_URI'),
        'rest_api_key' => env('KAKAO_REST_API_KEY', env('KAKAO_CLIENT_ID')),
    ],

    'naver' => [
        'client_id' => env('NAVER_CLIENT_ID'),
        'client_secret' => env('NAVER_CLIENT_SECRET'),
        'redirect' => env('NAVER_REDIRECT_URI'),
    ],

    'toss_payments' => [
        'client_key' => env('TOSS_PAYMENTS_CLIENT_KEY'),
        'secret_key' => env('TOSS_PAYMENTS_SECRET_KEY'),
        'base_url' => env('TOSS_PAYMENTS_BASE_URL', 'https://api.tosspayments.com'),
    ],

    'social_login' => [
        'frontend_callback' => env('FRONTEND_SOCIAL_CALLBACK_URL', '/test/social-login/callback'),
        'frontend_callbacks' => [
            'customer' => env('FRONTEND_SOCIAL_CALLBACK_URL_CUSTOMER'),
            'owner' => env('FRONTEND_SOCIAL_CALLBACK_URL_OWNER'),
        ],
    ],

];
