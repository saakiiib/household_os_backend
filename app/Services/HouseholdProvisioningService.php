<?php

namespace App\Services;

use App\Http\Controllers\Api\CategoriesController;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invitation;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a household for a web subscriber.
 *
 * Mirrors Api\HouseholdController@store (single-household guards, 30-day
 * Complete trial, default categories) so web and app households behave
 * identically. Only the Web controllers use this service; the mobile API
 * path is untouched.
 */
class HouseholdProvisioningService
{
    /**
     * Provision a household with trial for the given user.
     *
     * @throws \RuntimeException when the user already belongs to a household.
     */
    public function provision(User $user, string $name): Household
    {
        // Enforce single household per user (active OR pending)
        $existingMembership = HouseholdMember::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if ($existingMembership) {
            $existingHousehold = Household::find($existingMembership->household_id);
            throw new \RuntimeException(
                'You already belong to a household (' . ($existingHousehold->name ?? 'Unknown') . '). Leave your current household first before creating a new one.'
            );
        }

        // Also check for accepted invitations (user accepted but hasn't been
        // approved yet), mirroring the mobile API guard.
        $acceptedInvitation = Invitation::where('invited_email', $user->email)
            ->where('status', 'accepted')
            ->whereHas('household', function ($q) {
                $q->where('status', 'active');
            })
            ->whereNotIn('household_id', function ($q) use ($user) {
                $q->select('household_id')
                    ->from('household_members')
                    ->where('user_id', $user->id)
                    ->where('status', 'removed');
            })
            ->first();

        if ($acceptedInvitation) {
            throw new \RuntimeException(
                'You have a pending join request for a household. Wait for approval or decline it before creating a new household.'
            );
        }

        return DB::transaction(function () use ($user, $name) {
            // Cancel any remaining pending invitations for this user since
            // they are about to become the creator of a new household.
            Invitation::where('invited_email', $user->email)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            $household = Household::create([
                'name' => $name,
                'created_by_user_id' => $user->id,
                'privacy_level' => 'private',
                'status' => 'active',
                'app_account_token' => (string) Str::uuid(),
            ]);

            // Automatically make the creator an admin
            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Create 1-month free trial on Complete plan for this household
            $completePlan = SubscriptionPlan::where('code', 'complete')
                ->orWhere('slug', 'complete')
                ->first();
            if (!$completePlan) {
                throw new \RuntimeException('Complete subscription plan is not configured.');
            }

            $now = now();
            $trialEnd = $now->copy()->addDays(30);
            Subscription::create([
                'user_id' => $user->id,
                'subscriber_user_id' => $user->id,
                'household_id' => $household->id,
                'subscription_plan_id' => $completePlan->id,
                'status' => 'trial',
                'plan_status' => 'trial_complete',
                'paid_plan' => null,
                // A HouseholdOS trial is local, not an App Store / Play
                // subscription. Explicit values are important because older
                // schemas default provider=apple and auto_renew=true.
                'provider' => null,
                'product_id' => null,
                'billing_period' => null,
                'auto_renew' => false,
                'trial_started_at' => $now,
                'trial_ends_at' => $trialEnd,
                'current_period_start' => $now,
                'current_period_end' => $trialEnd,
                'expires_at' => $trialEnd,
            ]);

            // Seed default categories for this household
            app(CategoriesController::class)->seed($household->id);

            return $household;
        });
    }
}
