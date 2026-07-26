<?php

namespace App\Services;

use App\Models\User;

class SubscriptionGuard
{
    /**
     * Check if a user's household has an active subscription (including grace period).
     */
    public static function isActive(User $user): bool
    {
        $household = $user->activeHousehold();
        if (!$household) return false;

        $subscription = $household->subscription;
        if (!$subscription) return false;

        return $subscription->isActive();
    }

    /**
     * Check if a user's subscription has fully expired (past grace period).
     */
    public static function isExpired(User $user): bool
    {
        $household = $user->activeHousehold();
        if (!$household) return true;

        $subscription = $household->subscription;
        if (!$subscription) return true;

        return $subscription->isFullyExpired();
    }

    /**
     * Check if a user is in the grace period (period ended but still has access).
     */
    public static function isInGracePeriod(User $user): bool
    {
        $household = $user->activeHousehold();
        if (!$household) return false;

        $subscription = $household->subscription;
        if (!$subscription) return false;

        return $subscription->isInGracePeriod();
    }

    /**
     * Get subscription status summary for a user.
     */
    public static function getStatus(User $user): array
    {
        $household = $user->activeHousehold();
        if (!$household) {
            return ['status' => 'none', 'has_subscription' => false];
        }

        $subscription = $household->subscription;
        if (!$subscription) {
            return ['status' => 'none', 'has_subscription' => false];
        }

        return [
            'has_subscription' => true,
            'status' => $subscription->status,
            'is_active' => $subscription->isActive(),
            'is_in_grace_period' => $subscription->isInGracePeriod(),
            'is_fully_expired' => $subscription->isFullyExpired(),
            'days_remaining' => $subscription->daysRemaining(),
            'grace_days_remaining' => $subscription->graceDaysRemaining(),
        ];
    }
}
