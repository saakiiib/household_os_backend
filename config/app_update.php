<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HouseholdOS App Update Policy
    |--------------------------------------------------------------------------
    |
    | The mobile app asks the public /api/app/version endpoint whether an update
    | is available. Change these values on the server for each release, then run:
    |
    |   php artisan optimize:clear
    |
    | "latest" creates a recommended update. "minimum" creates a required
    | update only for versions older than the minimum supported build.
    |
    */
    'enabled' => env('APP_UPDATE_ENABLED', true),
    'check_interval_hours' => (int) env('APP_UPDATE_CHECK_INTERVAL_HOURS', 12),
    'remind_later_hours' => (int) env('APP_UPDATE_REMIND_LATER_HOURS', 24),

    'ios' => [
        'latest_version' => env('APP_UPDATE_IOS_LATEST_VERSION', '1.0.0'),
        'latest_build' => (int) env('APP_UPDATE_IOS_LATEST_BUILD', 4),
        'minimum_version' => env('APP_UPDATE_IOS_MINIMUM_VERSION', '1.0.0'),
        'minimum_build' => (int) env('APP_UPDATE_IOS_MINIMUM_BUILD', 1),
        // Set this to the real App Store URL once the listing ID is available.
        'store_url' => env('APP_UPDATE_IOS_STORE_URL', ''),
        'message' => env(
            'APP_UPDATE_IOS_MESSAGE',
            'Update HouseholdOS for the latest improvements, fixes and reliability updates.'
        ),
    ],

    'android' => [
        'latest_version' => env('APP_UPDATE_ANDROID_LATEST_VERSION', '1.0.0'),
        'latest_build' => (int) env('APP_UPDATE_ANDROID_LATEST_BUILD', 4),
        'minimum_version' => env('APP_UPDATE_ANDROID_MINIMUM_VERSION', '1.0.0'),
        'minimum_build' => (int) env('APP_UPDATE_ANDROID_MINIMUM_BUILD', 1),
        'store_url' => env(
            'APP_UPDATE_ANDROID_STORE_URL',
            'https://play.google.com/store/apps/details?id=com.mentosoftware.householdos'
        ),
        'message' => env(
            'APP_UPDATE_ANDROID_MESSAGE',
            'Update HouseholdOS for the latest improvements, fixes and reliability updates.'
        ),
    ],
];
