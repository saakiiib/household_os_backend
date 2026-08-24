<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CheckSubscriptionExpiry extends Command
{
    protected $signature = 'subscription:check-expiry';
    protected $description = 'Check for expiring/expired subscriptions and send notifications';

    public function handle(): int
    {
        if (!Cache::add('subscription-check-running', true, 60)) {
            $this->info('Subscription check already running — skipping.');
            return Command::SUCCESS;
        }

        try {
            $this->handleGracePeriodTransitions();
            $this->sendExpiryWarnings();
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

        // Grace period subscriptions past expires_at → expired
        $toExpired = Subscription::where('status', 'grace_period')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->get();

        foreach ($toExpired as $sub) {
            if ($sub->provider === 'apple') {
                app(\App\Services\AppleIapService::class)->refreshFromApple($sub);
                continue;
            }
            $sub->markExpired();
            $this->line("Expired: Household #{$sub->household_id}");
        }
    }

    /**
     * Send notification warnings at 7, 3, 1 days before renewal, and on expiry.
     */
    private function sendExpiryWarnings(): void
    {
        $now = now();

        // 7 days before renewal
        $this->sendWarningAtDays(7, 'renewal_7d');

        // 3 days before renewal
        $this->sendWarningAtDays(3, 'renewal_3d');

        // 1 day before renewal
        $this->sendWarningAtDays(1, 'renewal_1d');

        // Grace period warnings
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

    private function sendWarningAtDays(int $days, string $key): void
    {
        $now = now();
        $targetDate = $now->copy()->addDays($days);

        $subs = Subscription::where('status', 'active')
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', $targetDate->toDateString())
            ->with(['user', 'plan'])
            ->get();

        foreach ($subs as $sub) {
            if (!$this->alreadyNotified($sub, $key)) {
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
