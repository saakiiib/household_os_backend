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
        if (!Cache::add('subscription-check-running', true, 60)) {
            \Log::info('[SubscriptionCheck] Run skipped — already running.');
            $this->info('Subscription check already running — skipping.');
            return Command::SUCCESS;
        }

        try {
            \Log::info('[SubscriptionCheck] Run start');
            $this->refreshProviderSubscriptions();
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
     * Periodically re-verify Apple/Google subscriptions whose local period
     * has expired. This closes the gap where the app is closed and
     * Apple/Google renewed but nobody queried the provider — the local DB
     * would stay stale until the user opens the app and triggers the
     * on-demand refresh.
     *
     * Matches the on-demand refresh logic: queries Apple/Google whenever
     * last_verified_at is older than 5 minutes, regardless of local status
     * or auto_renew flag — because a missed webhook can leave the local
     * DB showing expired+auto_renew=false even though Apple actually renewed.
     */
    private function refreshProviderSubscriptions(): void
    {
        $now = now();

        // Any paid Apple/Google subscription not verified in the last 5 minutes.
        // No status or auto_renew filter — we must re-check even "expired"
        // subscriptions because a missed webhook can leave stale local data.
        // Only cap by expires_at within the last 24h to avoid re-checking
        // ancient expired subscriptions.
        $staleSubs = Subscription::where('plan_status', 'paid')
            ->whereIn('provider', ['apple', 'google_play'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $now->copy()->subDay())
            ->where(function ($q) use ($now) {
                $q->whereNull('last_verified_at')
                    ->orWhere('last_verified_at', '<', $now->copy()->subMinutes(5));
            })
            ->limit(50)
            ->get();

        if ($staleSubs->isEmpty()) {
            return;
        }

        $this->line("[SubscriptionCheck] Refreshing {$staleSubs->length} stale provider subscription(s)...");

        $appleService = app(AppleIapService::class);
        $googleService = app(GooglePlayIapService::class);

        foreach ($staleSubs as $sub) {
            $lock = Cache::lock('subscription-refresh:' . $sub->id, 30);
            if (!$lock->get()) {
                continue; // Another process is already refreshing this one.
            }

            try {
                $oldStatus = $sub->status;

                if ($sub->provider === 'apple') {
                    $ok = $appleService->refreshFromApple($sub);
                } else {
                    $ok = $googleService->refreshFromGoogle($sub);
                }

                // Reload to see what changed
                $sub->refresh();

                if ($ok && $sub->status !== $oldStatus) {
                    $this->line("  [SubscriptionCheck] #{$sub->id} status: {$oldStatus} → {$sub->status}");
                    \Log::info('[SubscriptionCheck] Provider refresh changed status', [
                        'subscription_id' => $sub->id,
                        'old_status' => $oldStatus,
                        'new_status' => $sub->status,
                        'provider' => $sub->provider,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning('[SubscriptionCheck] Provider refresh failed', [
                    'subscription_id' => $sub->id,
                    'provider' => $sub->provider,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                $lock->forceRelease();
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
