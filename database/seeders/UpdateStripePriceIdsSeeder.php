<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateStripePriceIdsSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'tasks' => [
                'monthly' => 'price_1UPI9t9S2MXuNCbuMPtC5p8s',
                'annual' => 'price_1UPIAS9S2MXuNCbufqgysOgml',
                'level' => 3,
            ],
            'renewals' => [
                'monthly' => 'price_1UPIO9S2MXuNCbuoCvvcrhq',
                'annual' => 'price_1UPIBr9S2MXuNCbu35mh8VFR',
                'level' => 3,
            ],
            'essentials' => [
                'monthly' => 'price_1UPI7M9S2MXuNCbu5E82Ulsz',
                'annual' => 'price_1UPI959S2MXuNCbugycknTIN',
                'level' => 2,
            ],
            'documents' => [
                'monthly' => 'price_1UPID49S2MXuNCbuQLhrmF7w',
                'annual' => 'price_1UPIDg9S2MXuNCbu78gmfLtl',
                'level' => 2,
            ],
            'complete' => [
                'monthly' => 'price_1UPI4T9S2MXuNCbu5WuhUqj4',
                'annual' => 'price_1UPI5p9S2MXuNCboubNXJaWGn',
                'level' => 1,
            ],
        ];

        foreach ($map as $code => $ids) {
            $plan = SubscriptionPlan::where('code', $code)->first();
            if (!$plan) {
                continue;
            }

            $plan->update([
                'stripe_price_monthly' => $ids['monthly'],
                'stripe_price_annual' => $ids['annual'],
            ]);

            foreach (['monthly' => $ids['monthly'], 'annual' => $ids['annual']] as $period => $priceId) {
                DB::table('product_plans')->updateOrInsert(
                    ['provider' => 'stripe', 'product_id' => $priceId],
                    [
                        'plan_id' => $plan->id,
                        'billing_period' => $period,
                        'level' => $ids['level'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
