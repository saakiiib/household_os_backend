<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::query()->delete();
        DB::table('product_plans')->truncate();

        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'code' => 'free',
                'apple_product_id' => null,
                'google_product_id' => null,
                'description' => 'Get started and stay organised. Free forever.',
                'monthly_price' => 0.00,
                'annual_price' => 0.00,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 0,
                'features' => [
                    '10 active Tasks',
                    '3 active Renewals',
                    '100 MB Document Locker',
                    '2 household members',
                ],
            ],
            [
                'name' => 'Tasks',
                'slug' => 'tasks',
                'code' => 'tasks',
                'apple_product_id' => 'com.mentosoftware.householdos.tasks.monthly',
                'google_product_id' => 'com.mentosoftware.hos.tasks.monthly',
                'description' => 'Manage household obligations, assignments and recurring chores.',
                'monthly_price' => 2.99,
                'annual_price' => 29.99,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 1,
                'features' => [
                    'Unlimited Tasks',
                    'Recurring task schedules',
                    'Task priority & due dates',
                    'Member task assignments',
                    'Task completion history',
                ],
            ],
            [
                'name' => 'Renewals',
                'slug' => 'renewals',
                'code' => 'renewals',
                'apple_product_id' => 'com.mentosoftware.householdos.renewals.monthly',
                'google_product_id' => 'com.mentosoftware.hos.renewals.monthly',
                'description' => 'Track insurance, MOT, tax and warranty renewals before they expire.',
                'monthly_price' => 2.99,
                'annual_price' => 29.99,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 2,
                'features' => [
                    'Unlimited Renewals',
                    'Standard & vehicle renewals',
                    'Automatic expiry alerts',
                    'Renewal chain tracking',
                    'Vehicle service records',
                ],
            ],
            [
                'name' => 'Essentials',
                'slug' => 'essentials',
                'code' => 'essentials',
                'apple_product_id' => 'com.mentosoftware.householdos.essentials.monthly',
                'google_product_id' => 'com.mentosoftware.hos.essentials.monthly',
                'description' => 'Tasks + Renewals combined. Perfect for households that need both.',
                'monthly_price' => 4.49,
                'annual_price' => 44.99,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 3,
                'features' => [
                    'All Tasks features',
                    'All Renewals features',
                    'Tasks + Renewals combined',
                    'Save vs buying separately',
                ],
            ],
            [
                'name' => 'Document Locker',
                'slug' => 'documents',
                'code' => 'documents',
                'apple_product_id' => 'com.mentosoftware.householdos.documents.monthly',
                'google_product_id' => 'com.mentosoftware.hos.documents.monthly',
                'description' => 'Securely store and manage important household documents.',
                'monthly_price' => 3.99,
                'annual_price' => 39.99,
                'is_active' => true,
                'is_popular' => false,
                'sort_order' => 4,
                'features' => [
                    '5 GB secure storage',
                    'End-to-end encryption',
                    'Multi-file upload',
                    'Secure file download & share',
                    'Search by title & category',
                ],
            ],
            [
                'name' => 'Complete',
                'slug' => 'complete',
                'code' => 'complete',
                'apple_product_id' => 'com.mentosoftware.householdos.complete.monthly',
                'google_product_id' => 'com.mentosoftware.hos.complete.monthly',
                'description' => 'Everything organised in one place. The complete HouseholdOS experience.',
                'monthly_price' => 6.99,
                'annual_price' => 69.99,
                'is_active' => true,
                'is_popular' => true,
                'sort_order' => 5,
                'features' => [
                    'All Tasks features',
                    'All Renewals features',
                    'All Document Locker features',
                    '5 GB secure Document Locker',
                    'Household sharing',
                    'All future modules included',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }

        // Seed the normalized product_plans mapping (command.txt §14) from the
        // single source of truth in config files.
        $appleProducts = config('apple_products.apple_products', []);
        foreach ($appleProducts as $productId => $cfg) {
            $plan = SubscriptionPlan::where('code', $cfg['plan'])->first();
            if (!$plan) {
                continue;
            }
            DB::table('product_plans')->insert([
                'plan_id' => $plan->id,
                'provider' => 'apple',
                'product_id' => $productId,
                'billing_period' => $cfg['billing_period'],
                'level' => $cfg['level'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $googleProducts = config('google_products.google_products', []);
        foreach ($googleProducts as $productId => $cfg) {
            $plan = SubscriptionPlan::where('code', $cfg['plan'])->first();
            if (!$plan) {
                continue;
            }
            DB::table('product_plans')->insert([
                'plan_id' => $plan->id,
                'provider' => 'google',
                'product_id' => $productId,
                'billing_period' => $cfg['billing_period'],
                'level' => $cfg['level'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
