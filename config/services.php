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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
    ],

    'apple' => [
        // Sign in with Apple (existing)
        'client_id' => env('APPLE_CLIENT_ID'),
        'team_id'   => env('APPLE_TEAM_ID'),
        'key_path'  => env('APPLE_KEY_PATH', storage_path('app/AuthKey.p8')),

        // App Store Server API (App Store Connect > Users & Access > Integrations > In-App Purchase)
        // The .p8 key MUST only ever exist on this server (never in the app, iOS binary or Git).
        'iap_key_id'          => env('APPLE_IAP_KEY_ID'),
        'iap_issuer_id'       => env('APPLE_IAP_ISSUER_ID'),
        'iap_private_key_path' => env('APPLE_IAP_PRIVATE_KEY_PATH', storage_path('app/AuthKey.p8')),
        'bundle_id'           => env('APPLE_BUNDLE_ID', 'com.mentosoftware.householdos'),
        'app_id'              => env('APPLE_APP_ID'),

        // Legacy verifyReceipt shared secret (kept only as fallback; new flow uses the Server API).
        'shared_secret' => env('APPLE_SHARED_SECRET', ''),
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.householdosapp.household'),
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON', ''),
    ],

];
