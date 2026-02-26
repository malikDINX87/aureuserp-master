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
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'dinx_erp_sync' => [
        'enabled' => env('DINX_ERP_SYNC_ENABLED', true),
        'webhook_secret' => env('DINX_ERP_WEBHOOK_SECRET'),
        'max_skew_seconds' => env('DINX_ERP_SYNC_MAX_SKEW_SECONDS', 300),
        'queue' => env('DINX_ERP_SYNC_QUEUE'),
    ],

    'dinx_erp_sso' => [
        'enabled' => env('DINX_ERP_SSO_ENABLED', true),
        'shared_secret' => env('DINX_ERP_SSO_SHARED_SECRET'),
        'issuer' => env('DINX_ERP_SSO_ISSUER', 'dinxsolutions.com'),
        'audience' => env('DINX_ERP_SSO_AUDIENCE', 'dinx-erp'),
        'max_clock_skew_seconds' => env('DINX_ERP_SSO_MAX_CLOCK_SKEW_SECONDS', 60),
        'jti_ttl_seconds' => env('DINX_ERP_SSO_JTI_TTL_SECONDS', 600),
    ],

];
