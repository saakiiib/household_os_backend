<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
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
        if (!Cache::add('subscription-check-running', true, 60)) {
            $this->info('Subscription check already running — skipping.');
            return Command::SUCCESS;
        }

        try {
            $this->handleGracePeriodTransitions();
            $this->handleTrialExpiry();
            $this->sendPaidExpiryWarnings();
        } finally {
            Cache::forget('subscription-check-running');
        }

        $this->info('Subscription expiry check complete.');
        return Command::SUCCESS;
    }

    /**
     * Move active subscriptions to grace period, and grace period to expired.
     *
     * command.txt §51 Rule 10: Apple billing status — not this cron — decides
     * expiry/renewal for Apple subscriptions. So for provider=apple rows we
     * re-verify with Apple instead of transitioning locally. Legacy
     * Stripe/PayPal subscriptions keep the local transitions.
     */
    private function handleGracePeriodTransitions(): void
    {
        $now = now();

        // Active subscriptions past their period_end but not yet past expires_at → grace period
        $toGrace = Subscription::where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get();

        foreach ($toGrace as $sub) {
            if ($sub->provider === 'apple') {
                app(\App\Services\AppleIapService::class)->refreshFromApple($sub);
                continue;
            }
            $sub->moveToGracePeriod();
            $this->line("Moved to grace period: Household #{$sub->household_id}");
        }

        // Grace period / cancelled subscriptions past their access window → expired
        $toExpired = Subscription::whereIn('status', ['grace_period', 'cancelled'])
            ->get();

        foreach ($toExpired as $sub) {
            if ($sub->provider === 'apple') {
                app(\App\Services\AppleIapService::class)->refreshFromApple($sub);
                continue;
            }
            if (!$sub->isActive()) {
                $sub->markExpired();
                $this->line("Expired: Household #{$sub->household_id}");
            }
        }
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
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $now->copy()->addDays(3))
            ->with(['user', 'plan'])
            ->get();

        foreach ($graceWarning as $sub) {
            $daysLeft = (int) $now->diffInDays($sub->expires_at);
            if ($daysLeft <= 0) continue;

            $key = "grace_{$daysLeft}d";
            if (!$this->alreadyNotified($sub, $key)) {
                if ($sub->user) {
                    app(NotificationService::class)->sendToUser(
                        $sub->user->id,
                        'Subscription expiring',
                        "Your subscription expires in {$daysLeft} day" . ($daysLeft > 1 ? 's' : ''),
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
                app(NotificationService::class)->sendToUser(
                    $sub->user->id,
                    'Subscription renewal reminder',
                    "Your {$sub->plan?->name} subscription renews in {$days} day" . ($days > 1 ? 's' : ''),
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
