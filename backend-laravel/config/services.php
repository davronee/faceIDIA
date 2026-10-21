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

    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://backend:8000'),
        'secret' => env('INTERNAL_AI_SECRET', 'faceidia_internal_secret_token_2026'),
    ],

    'egov' => [
        'base_url' => env('EGOV_PASSPORT_BASE_URL', 'https://gateway.imv.uz/api/v1'),
        'auth_endpoint' => env('EGOV_AUTH_ENDPOINT', '/auth/token'),
        'endpoint' => env('EGOV_PASSPORT_ENDPOINT', '/egov/passport-gcp-document-birthdate'),
        'auth_username' => env('EGOV_AUTH_USERNAME', 'moliyachi_faceid'),
        'auth_password' => env('EGOV_AUTH_PASSWORD', ''),
        'basic_auth' => env('EGOV_BASIC_AUTH'),
        'token' => env('EGOV_PASSPORT_TOKEN'),
    ],

];
