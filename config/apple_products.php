<?php

/*
 * Central Apple App Store product configuration (command.txt §9).
 *
 * Every Apple Product ID is defined in ONE place. Controllers, the IAP
 * service and the Flutter app must never hard-code these IDs elsewhere.
 *
 * Monthly and annual versions of the same plan grant the SAME entitlements
 * (command.txt §8 / §51 Rule 9) — only the billing duration differs.
 */

return [

    /*
     * The 10 App Store Connect products (5 monthly + 5 annual) all live inside
     * the same "HouseholdOS Membership" subscription group.
     */
    'apple_products' => [
        'com.mentosoftware.householdos.tasks.monthly' => [
            'plan' => 'tasks',
            'billing_period' => 'monthly',
            'level' => 3,
        ],
        'com.mentosoftware.householdos.tasks.annual' => [
            'plan' => 'tasks',
            'billing_period' => 'annual',
            'level' => 3,
        ],
        'com.mentosoftware.householdos.renewals.monthly' => [
            'plan' => 'renewals',
            'billing_period' => 'monthly',
            'level' => 3,
        ],
        'com.mentosoftware.householdos.renewals.annual' => [
            'plan' => 'renewals',
            'billing_period' => 'annual',
            'level' => 3,
        ],
        'com.mentosoftware.householdos.essentials.monthly' => [
            'plan' => 'essentials',
            'billing_period' => 'monthly',
            'level' => 2,
        ],
        'com.mentosoftware.householdos.essentials.annual' => [
            'plan' => 'essentials',
            'billing_period' => 'annual',
            'level' => 2,
        ],
        'com.mentosoftware.householdos.documents.monthly' => [
            'plan' => 'documents',
            'billing_period' => 'monthly',
            'level' => 2,
        ],
        'com.mentosoftware.householdos.documents.annual' => [
            'plan' => 'documents',
            'billing_period' => 'annual',
            'level' => 2,
        ],
        'com.mentosoftware.householdos.complete.monthly' => [
            'plan' => 'complete',
            'billing_period' => 'monthly',
            'level' => 1,
        ],
        'com.mentosoftware.householdos.complete.annual' => [
            'plan' => 'complete',
            'billing_period' => 'annual',
            'level' => 1,
        ],
    ],

];
