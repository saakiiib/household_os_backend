<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Services\AppleIapService;
use App\Services\GooglePlayIapService;
use App\Models\Subscription;
use App\Models\User;
use App\Models\HouseholdMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckSubscriptionExpiry extends Command
{
    protected $signature = 'subscription:check-expiry';
    protected $description = 'Check for expiring/expired subscriptions and trial expiries';

    public function handle(): int
    {
        if (!Cache::add('subscription-check-running', true, 240)) {
            \Log::info('[SubscriptionCheck] Run skipped — already running.');
            $this->info('Subscription check already running — skipping.');
            return Command::SUCCESS;
        }

        try {
            // Webhooks are the primary source of store changes, but this
            // reconciliation is the safety net that keeps entitlement current
            // even when nobody opens the app and a webhook is delayed/missed.
            $this->reconcileStoreSubscriptions();
            $this->handleTrialExpiry();
            $this->sendPaidExpiryWarnings();
        } finally {
            Cache::forget('subscription-check-running');
        }

        \Log::info('[SubscriptionCheck] Run complete.');
        $this->info('Subscription expiry check complete.');
        return Command::SUCCESS;
    }

    /**
     * NO automatic expiry based on local timestamps.
     *
     * Store subscription state is NEVER invented from local timestamps.
     * Apple/Google/webhooks remain authoritative. This command does perform a
     * conservative server-side reconciliation for paid store subscriptions as
     * a safety net, so expiry/renewal/grace recovery can still be reflected
     * when the mobile app is closed.
     */

    /**
     * Reconcile paid Apple/Google subscriptions without requiring the app to be
     * open. Webhooks are still primary; this is a safety net only.
     *
     * Refresh when:
     *  - the local period is within 10 minutes of its end (or already ended),
     *  - the subscription is in billing retry / grace, or
     *  - we have not verified it with the store in the last 24 hours.
     *
     * A cap keeps one scheduler run bounded. Oldest verification rows are
     * handled first, so a larger estate is naturally drained over later runs.
     */
    private function reconcileStoreSubscriptions(): void
    {
        $now = now();
        $nearPeriodEnd = $now->copy()->addMinutes(10);
        $staleBefore = $now->copy()->subDay();

        $subscriptions = Subscription::query()
            ->where('plan_status', 'paid')
            ->whereIn('provider', ['apple', 'google'])
            ->where(function ($q) use ($nearPeriodEnd, $staleBefore) {
                $q->whereIn('status', ['grace_period', 'billing_retry'])
                    ->orWhere(function ($q2) use ($nearPeriodEnd) {
                        $q2->whereNotNull('current_period_end')
                            ->where('current_period_end', '<=', $nearPeriodEnd);
                    })
                    ->orWhereNull('last_verified_at')
                    ->orWhere('last_verified_at', '<=', $staleBefore);
            })
            ->where(function ($q) {
                $q->where(function ($apple) {
                    $apple->where('provider', 'apple')
                        ->where(function ($ids) {
                            $ids->whereNotNull('latest_transaction_id')
                                ->orWhereNotNull('original_transaction_id')
                                ->orWhereNotNull('apple_original_transaction_id');
                        });
                })->orWhere(function ($google) {
                    $google->where('provider', 'google')
                        ->whereNotNull('google_purchase_token');
                });
            })
            ->orderByRaw('last_verified_at IS NULL DESC')
            ->orderBy('last_verified_at')
            ->limit(100)
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $ok = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $refreshed = $subscription->provider === 'apple'
                    ? app(AppleIapService::class)->refreshFromApple($subscription)
                    : app(GooglePlayIapService::class)->refreshFromGoogle($subscription);

                $refreshed ? $ok++ : $failed++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning('[SubscriptionCheck] Store reconciliation failed', [
                    'subscription_id' => $subscription->id,
                    'provider' => $subscription->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        \Log::info('[SubscriptionCheck] Store reconciliation complete', [
            'checked' => $subscriptions->count(),
            'refreshed' => $ok,
            'failed' => $failed,
        ]);
    }

    /**
     * Handle trial expiry:
     * - Notify at 7d, 3d, 1d before trial_ends_at
     * - Auto-downgrade to free when trial_ends_at has passed and no active paid sub
     */
    private function handleTrialExpiry(): void
    {
        $now = now();

        // Send trial expiry warnings
        $this->sendTrialWarnings(7, 'trial_7d');
        $this->sendTrialWarnings(3, 'trial_3d');
        $this->sendTrialWarnings(1, 'trial_1d');

        // Auto-downgrade expired trials to free
        $expiredTrials = Subscription::where('status', 'trial')
            ->where('plan_status', 'trial_complete')
            ->where('trial_ends_at', '<=', $now)
            ->get();

        foreach ($expiredTrials as $sub) {
            // Only downgrade if no other active paid subscription exists
            $hasPaid = Subscription::where('household_id', $sub->household_id)
                ->where('plan_status', 'paid')
                ->where('status', 'active')
                ->exists();

            if (!$hasPaid) {
                $sub->update([
                    'status' => 'expired',
                    'plan_status' => 'free',
                    'paid_plan' => null,
                    'billing_period' => null,
                    'trial_started_at' => null,
                    'trial_ends_at' => null,
                    'subscription_plan_id' => null,
                ]);

                $this->line("Trial expired → Free: Household #{$sub->household_id}");

                // Notify all household members
                $members = HouseholdMember::where('household_id', $sub->household_id)
                    ->where('status', 'active')
                    ->with('user')
                    ->get();

                foreach ($members as $member) {
                    if ($member->user) {
                        app(NotificationService::class)->sendToUser(
                            $member->user->id,
                            'Trial ended',
                            'Your Complete trial has ended. You are now on the Free plan with limited features.',
                            'trial_expiry',
                            [
                                'subscription_id' => $sub->id,
                                'household_id' => $sub->household_id,
                                'plan_name' => $sub->plan?->name,
                                'type' => 'trial_expired',
                                'action' => 'view_subscription',
                            ],
                            'high'
                        );
                    }
                }
            }
        }
    }

    /**
     * Send paid subscription renewal warnings at 7, 3, 1 days before renewal.
     */
    private function sendPaidExpiryWarnings(): void
    {
        $this->sendWarningAtDays(7, 'renewal_7d');
        $this->sendWarningAtDays(3, 'renewal_3d');
        $this->sendWarningAtDays(1, 'renewal_1d');

        // Grace period warnings
        $now = now();
        $graceWarning = Subscription::where('status', 'grace_period')
            ->whereNotNull('grace_period_expires_at')
            ->where('grace_period_expires_at', '>', $now)
            ->where('grace_period_expires_at', '<=', $now->copy()->addDays(3))
            ->with(['user', 'plan'])
            ->get();

        foreach ($graceWarning as $sub) {
            $daysLeft = (int) $now->diffInDays($sub->grace_period_expires_at);
            if ($daysLeft <= 0) continue;

            $key = "grace_{$daysLeft}d";
            if (!$this->alreadyNotified($sub, $key)) {
                if ($sub->user) {
                    app(NotificationService::class)->sendToUser(
                        $sub->user->id,
                        'Billing grace period ending',
                        "Your billing grace period ends in {$daysLeft} day" . ($daysLeft > 1 ? 's' : ''),
                        'subscription_expiry',
                        [
                            'subscription_id' => $sub->id,
                            'household_id' => $sub->household_id,
                            'plan_name' => $sub->plan?->name,
                            'type' => 'grace_period',
                            'action' => 'renew_now',
                        ],
                        'critical'
                    );
                }
                $this->markNotified($sub, $key);
            }
        }
    }

    private function sendTrialWarnings(int $days, string $key): void
    {
        $now = now();
        $targetDate = $now->copy()->addDays($days);

        $trials = Subscription::where('status', 'trial')
            ->where('plan_status', 'trial_complete')
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', $targetDate->toDateString())
            ->with(['plan'])
            ->get();

        foreach ($trials as $sub) {
            if ($this->alreadyNotified($sub, $key)) continue;

            // Notify all household members
            $members = HouseholdMember::where('household_id', $sub->household_id)
                ->where('status', 'active')
                ->with('user')
                ->get();

            foreach ($members as $member) {
                if (!$member->user) continue;

                $message = match ($days) {
                    7 => 'Your Complete trial ends in 7 days. Choose a plan or continue with Free.',
                    3 => 'Your Complete trial ends in 3 days. Choose how you\'d like to continue.',
                    1 => 'Your Complete trial ends tomorrow. Choose a plan or continue with Free.',
                    default => "Your Complete trial ends in {$days} days.",
                };

                app(NotificationService::class)->sendToUser(
                    $member->user->id,
                    $days <= 1 ? 'Trial ending soon' : 'Trial reminder',
                    $message,
                    'trial_expiry',
                    [
                        'subscription_id' => $sub->id,
                        'household_id' => $sub->household_id,
                        'plan_name' => $sub->plan?->name,
                        'type' => 'trial_warning',
                        'days_remaining' => $days,
                        'action' => 'view_subscription',
                    ],
                    $days <= 1 ? 'high' : 'normal'
                );
            }

            $this->markNotified($sub, $key);
            $this->line("Trial {$days}d warning sent: Household #{$sub->household_id}");
        }
    }

    private function sendWarningAtDays(int $days, string $key): void
    {
        $now = now();
        $targetDate = $now->copy()->addDays($days);

        $subs = Subscription::where('status', 'active')
            ->where('plan_status', 'paid')
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', $targetDate->toDateString())
            ->with(['user', 'plan'])
            ->get();

        foreach ($subs as $sub) {
            if ($this->alreadyNotified($sub, $key)) continue;

            if ($sub->user) {
                $willRenew = $sub->auto_renew !== false;
                $title = $willRenew ? 'Subscription renewal reminder' : 'Subscription ending soon';
                $verb = $willRenew ? 'renews' : 'ends';

                app(NotificationService::class)->sendToUser(
                    $sub->user->id,
                    $title,
                    "Your {$sub->plan?->name} subscription {$verb} in {$days} day" . ($days > 1 ? 's' : ''),
                    'subscription_expiry',
                    [
                        'subscription_id' => $sub->id,
                        'household_id' => $sub->household_id,
                        'plan_name' => $sub->plan?->name,
                        'type' => 'renewal',
                        'action' => 'view_subscription',
                    ],
                    'high'
                );
            }

            $this->markNotified($sub, $key);
        }
    }

    private function alreadyNotified(Subscription $sub, string $key): bool
    {
        $meta = $sub->metadata ?? [];
        return isset($meta["notified_{$key}"]);
    }

    private function markNotified(Subscription $sub, string $key): void
    {
        $meta = $sub->metadata ?? [];
        $meta["notified_{$key}"] = now()->toIso8601String();
        $sub->update(['metadata' => $meta]);
    }
}
