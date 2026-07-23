<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Household Tasks',
                'slug' => 'household_tasks',
                'description' => 'Manage household obligations, assignments and recurring chores.',
                'monthly_price' => 2.99,
                'annual_price' => 29.99,
                'features' => [
                    'Unlimited tasks',
                    'Recurring task schedules',
                    'Single assignee per task',
                    'Auto status tracking',
                    'Activity log for tasks',
                    'Task priority & due dates',
                    'Member task assignments',
                    'Task completion history',
                ],
                'is_popular' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Renewals',
                'slug' => 'renewals',
                'description' => 'Track insurance, MOT, tax and warranty renewals before they expire.',
                'monthly_price' => 2.99,
                'annual_price' => 29.99,
                'features' => [
                    'Unlimited renewals',
                    'Standard & vehicle renewals',
                    'Automatic expiry alerts',
                    'Renewal chain tracking',
                    'Vehicle service records',
                    'Total cost tracking',
                    'Overdue & days-until-due计算',
                    'Complete & renew flow',
                ],
                'is_popular' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Document Locker',
                'slug' => 'document_locker',
                'description' => 'Securely store and manage important household documents.',
                'monthly_price' => 2.99,
                'annual_price' => 29.99,
                'features' => [
                    'AES-256 end-to-end encryption',
                    '8 document categories',
                    'Multi-file upload (up to 10)',
                    '10MB max file size',
                    'PDF, images & doc formats',
                    'Secure file download & share',
                    'Search by title & category',
                    'Document metadata & notes',
                ],
                'is_popular' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Household Premium',
                'slug' => 'household_premium',
                'description' => 'All modules included. The complete HouseholdOS experience.',
                'monthly_price' => 5.99,
                'annual_price' => 59.99,
                'features' => [
                    'All Tasks features',
                    'All Renewals features',
                    'All Document Locker features',
                    'Unlimited members',
                    'Unlimited storage',
                    'Priority support',
                    'All future modules included',
                    'Household activity dashboard',
                ],
                'is_popular' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
