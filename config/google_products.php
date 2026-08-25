<?php

/*
 * Central Google Play product configuration.
 *
 * Mirrors config/apple_products.php: every Google Play subscription product
 * ID is defined in ONE place and mapped to a SubscriptionPlan + billing
 * period. The server resolves the plan from this map (never trusting the
 * client-supplied plan_slug), keeping Google dynamic like Apple.
 */

return [

    'google_products' => [
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
