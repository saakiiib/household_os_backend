<?php

/*
 * Central Stripe product configuration.
 *
 * Mirrors config/apple_products.php and config/google_products.php: every
 * Stripe Price ID is defined in ONE place and mapped to a SubscriptionPlan +
 * billing period.
 */

return [

    'stripe_products' => [
        'price_1UPI9t9S2MXuNCbuMPtC5p8s' => ['plan' => 'tasks', 'billing_period' => 'monthly', 'level' => 3],
        'price_1UPIAS9S2MXuNCbufqgysOgml' => ['plan' => 'tasks', 'billing_period' => 'annual', 'level' => 3],
        'price_1UPIO9S2MXuNCbuoCvvcrhq' => ['plan' => 'renewals', 'billing_period' => 'monthly', 'level' => 3],
        'price_1UPIBr9S2MXuNCbu35mh8VFR' => ['plan' => 'renewals', 'billing_period' => 'annual', 'level' => 3],
        'price_1UPI7M9S2MXuNCbu5E82Ulsz' => ['plan' => 'essentials', 'billing_period' => 'monthly', 'level' => 2],
        'price_1UPI959S2MXuNCbugycknTIN' => ['plan' => 'essentials', 'billing_period' => 'annual', 'level' => 2],
        'price_1UPID49S2MXuNCbuQLhrmF7w' => ['plan' => 'documents', 'billing_period' => 'monthly', 'level' => 2],
        'price_1UPIDg9S2MXuNCbu78gmfLtl' => ['plan' => 'documents', 'billing_period' => 'annual', 'level' => 2],
        'price_1UPI4T9S2MXuNCbu5WuhUqj4' => ['plan' => 'complete', 'billing_period' => 'monthly', 'level' => 1],
        'price_1UPI5p9S2MXuNCboubNXJaWGn' => ['plan' => 'complete', 'billing_period' => 'annual', 'level' => 1],
    ],

];
