<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SubscriptionController extends Controller
{
    /**
     * List all subscription plans (public).
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    /**
     * Return all Apple Product IDs for StoreKit to query (command.txt §21/§22).
     * Flutter calls this on startup to know which products to load.
     */
    public function products(): JsonResponse
    {
        $products = config('apple_products.apple_products', []);

        $result = [];
        foreach ($products as $productId => $cfg) {
            $result[] = [
                'product_id' => $productId,
                'plan' => $cfg['plan'],
                'billing_period' => $cfg['billing_period'],
                'level' => $cfg['level'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get current household's subscription.
     * Subscription is per-household, not per-user.
     *
     * On-demand Apple refresh: when the user opens a screen that calls this
     * endpoint, the server re-verifies the Apple subscription with Apple's
     * App Store Server API. This is the ONLY place expiry is decided for
     * Apple IAP — no cron, no local timestamp logic.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->householdSubscription();

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        // On-demand provider refresh — re-query the store for authoritative
        // current state. Apple Sandbox can compress a one-month subscription
        // to only a few minutes, so a fixed five-minute throttle can create a
        // false Free window between Sandbox renewals. Production stays on the
        // normal five-minute throttle, while Sandbox is allowed to refresh
        // once per minute.
        //
        // Most importantly: if the locally stored Apple expiry has already
        // passed, ALWAYS re-query Apple before returning the household as Free.
        // This rule is useful in Production too because it prevents a webhook
        // delay from temporarily removing paid access.
        $lastVerified = $subscription->last_verified_at;
        $localExpiry = $subscription->expires_at ?? $subscription->current_period_end;

        // A HouseholdOS 30-day trial is local entitlement. Some older rows
        // may still contain provider=apple because the historical DB column
        // defaulted to Apple. Never contact Apple/Google for a trial. Also
        // require a real store identity before scheduling a provider refresh.
        $provider = strtolower((string) $subscription->provider);
        $hasStoreIdentity = match ($provider) {
            'apple' => !empty($subscription->original_transaction_id)
                || !empty($subscription->latest_transaction_id)
                || !empty($subscription->product_id),
            'google_play' => !empty($subscription->google_purchase_token)
                || !empty($subscription->google_order_id)
                || !empty($subscription->product_id),
            default => false,
        };
        $isStoreManaged = !$subscription->isTrial() && $hasStoreIdentity;
        // Only bypass the normal refresh throttle when the local Apple period
        // has expired AND a renewal may still be expected. Once Apple has
        // confirmed expired + auto_renew=false, do not query Apple on every
        // /subscription/current request.
        $localApplePeriodExpired = $subscription->provider === 'apple'
            && $localExpiry
            && now()->greaterThanOrEqualTo($localExpiry);

        $appleRenewalMayStillBeExpected = $subscription->provider === 'apple'
            && !(
                strtolower((string) $subscription->status) === 'expired'
                && $subscription->auto_renew === false
            );

        $forceAppleExpiryRefresh = $localApplePeriodExpired
            && $appleRenewalMayStillBeExpected;

        $refreshAfterMinutes = strtolower((string) $subscription->environment) === 'sandbox'
            ? 1
            : 5;

        $stale = !$lastVerified
            || $lastVerified->lt(now()->subMinutes($refreshAfterMinutes));

        // Purchase/plan-change reconciliation can explicitly request one
        // synchronous provider refresh. Normal screen loads remain fast and use
        // the background refresh below. This is intentionally restricted to the
        // household payer and store-managed subscriptions so it cannot become a
        // general-purpose Apple/Google polling endpoint.
        $explicitRefreshPerformed = false;
        $explicitProviderRefresh = $request->boolean('refresh_provider');
        $payerIdForRefresh = $subscription->subscriber_user_id ?? $subscription->user_id;
        $mayExplicitlyRefresh = $explicitProviderRefresh
            && $isStoreManaged
            && (int) $payerIdForRefresh === (int) $user->id;

        if ($mayExplicitlyRefresh) {
            $lock = Cache::lock('subscription-refresh:' . $subscription->id, 15);
            if ($lock->get()) {
                try {
                    if ($provider === 'apple') {
                        app(\App\Services\AppleIapService::class)->refreshFromApple($subscription);
                    } elseif ($provider === 'google_play') {
                        app(\App\Services\GooglePlayIapService::class)->refreshFromGoogle($subscription);
                    }
                    $subscription->refresh();
                    $explicitRefreshPerformed = true;
                } catch (\Throwable $e) {
                    \Log::warning('SubscriptionController@current: explicit provider refresh failed', [
                        'subscription_id' => $subscription->id,
                        'provider' => $provider,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    optional($lock)->release();
                }
            }
        }

        $shouldRefresh = !$explicitRefreshPerformed
            && $isStoreManaged
            && ($stale || $forceAppleExpiryRefresh);

        if ($shouldRefresh) {
            // Prevent multiple app requests from re-querying Apple/Google at the
            // same time for the same subscription. This reduces unnecessary
            // provider calls and protects the API from retry bursts.
            $lock = Cache::lock('subscription-refresh:' . $subscription->id, 15);

            if ($lock->get()) {
                // IMPORTANT: Return the local subscription data IMMEDIATELY so
                // the app gets a fast response. The provider refresh runs after
                // the response is sent via app()->terminating(). This prevents
                // slow Apple/Google API calls (15-30s on shared hosting) from
                // blocking the entire /subscription/current response and causing
                // frontend timeouts.
                $subscriptionId = $subscription->id;
                $provider = $subscription->provider;
                $lockKey = 'subscription-refresh:' . $subscriptionId;

                app()->terminating(function () use ($subscriptionId, $provider, $lockKey) {
                    try {
                        $sub = \App\Models\Subscription::find($subscriptionId);
                        if (!$sub) return;

                        if ($provider === 'apple') {
                            \Log::info('SubscriptionController@current: background refresh Apple subscription', [
                                'subscription_id' => $subscriptionId,
                            ]);
                            app(\App\Services\AppleIapService::class)->refreshFromApple($sub);
                        } elseif ($provider === 'google_play') {
                            \Log::info('SubscriptionController@current: background refresh Google subscription', [
                                'subscription_id' => $subscriptionId,
                            ]);
                            app(\App\Services\GooglePlayIapService::class)->refreshFromGoogle($sub);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('SubscriptionController@current: background provider refresh failed', [
                            'subscription_id' => $subscriptionId,
                            'error' => $e->getMessage(),
                        ]);
                    } finally {
                        Cache::lock($lockKey)->forceRelease();
                    }
                });
            } else {
                \Log::debug('SubscriptionController@current: provider refresh skipped because another request is already refreshing', [
                    'subscription_id' => $subscription->id,
                    'household_id' => $subscription->household_id,
                ]);
            }
        }

        $subscription->load(['plan', 'subscriber', 'user']);
        $household = $user->activeHousehold();

        $payerId = $subscription->subscriber_user_id ?? $subscription->user_id;
        $isSubscriber = ($user->id === $payerId);
        $isCreator = $household ? ($household->created_by_user_id === $user->id) : false;
        $payer = $subscription->subscriber ?? $subscription->user;
        $payerName = $payer ? ($payer->first_name ? $payer->first_name . ' ' . $payer->last_name : ($payer->name ?? $payer->email)) : null;

        $hasActivePaidSubscription = $subscription->isActive() && !$subscription->isTrial();
        // A paid household must not create a second subscription. The current
        // payer, however, must be allowed to change the existing App Store /
        // Play subscription (upgrade, downgrade or billing duration).
        $canPurchase = !$hasActivePaidSubscription || $isSubscriber;
        $canManage = $isSubscriber;

        // Normalised entitlement state — single source of truth that the
        // client should display instead of mixing status / is_active /
        // plan_status / paid_plan / is_trial (which can be contradictory).
        $entitlementService = new EntitlementService();
        $effectivePlan = $household
            ? $entitlementService->getPlanCode($household)
            : 'free';
        $accessState = match (true) {
            $subscription->isTrial() && $subscription->isActive() => 'trial',
            $subscription->isActive() && !$subscription->isTrial() => 'paid',
            default => 'free',
        };

        \Log::info('SUBSCRIPTION CURRENT RESPONSE DEBUG', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'is_active' => $subscription->isActive(),
            'access_state' => $accessState,
            'effective_plan' => $effectivePlan,
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'last_verified_at' => $subscription->last_verified_at?->toIso8601String(),
            'environment' => $subscription->environment,
            'auto_renew' => $subscription->auto_renew,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan_status' => $subscription->plan_status,
                'paid_plan' => $subscription->paid_plan,
                'subscriber_user_id' => $payerId,
                'is_subscriber' => $isSubscriber,
                'is_creator' => $isCreator,
                'payer_name' => $payerName,
                'can_manage' => $canManage,
                'can_purchase' => $canPurchase,
                // Normalised state — Flutter should display this, not infer.
                'access_state' => $accessState,
                'effective_plan' => $effectivePlan,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                    'monthly_price' => $subscription->plan->monthly_price,
                    'annual_price' => $subscription->plan->annual_price,
                ],
                'trial_started_at' => $subscription->trial_started_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_start' => $subscription->current_period_start?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                'payment_method' => $subscription->payment_method,
                'billing_type' => $subscription->billing_period ?? $this->guessBillingType($subscription),
                'provider' => $subscription->provider,
                'product_id' => $subscription->product_id,
                'environment' => $subscription->environment,
                'auto_renew' => $subscription->auto_renew,
                'grace_period_expires_at' => $subscription->grace_period_expires_at?->toIso8601String(),
                'pending_product_id' => $subscription->metadata['pending_product_id'] ?? null,
                'pending_plan' => $subscription->metadata['pending_plan'] ?? null,
                'pending_billing_period' => $subscription->metadata['pending_billing_period'] ?? null,
                'pending_change_effective_at' => $subscription->metadata['pending_change_effective_at'] ?? null,
                'days_remaining' => $subscription->daysRemaining(),
                'days_until_renewal' => $subscription->daysUntilRenewal(),
                'grace_days_remaining' => $subscription->graceDaysRemaining(),
                'is_in_grace_period' => $subscription->isInGracePeriod(),
                'is_active' => $subscription->isActive(),
                'is_trial' => $subscription->isTrial(),
            ],
        ]);
    }

    /**
     * Cancel household's active subscription.
     * Only the household admin can cancel.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->householdSubscription();

        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to cancel.',
            ], 404);
        }

        // Check if user is admin of the household
        $membership = HouseholdMember::where('household_id', $subscription->household_id)
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Only the Household Coordinator can manage this subscription.',
            ], 403);
        }

        // Store-managed subscriptions must be cancelled in the store. Marking
        // them cancelled locally while Apple/Google still considers them active
        // can incorrectly remove access and will be overwritten by the next
        // webhook/reconciliation anyway.
        if (in_array(strtolower((string) $subscription->provider), ['apple', 'google'], true)) {
            return response()->json([
                'success' => false,
                'code' => 'STORE_MANAGED_SUBSCRIPTION',
                'message' => $subscription->provider === 'apple'
                    ? 'Manage or cancel this subscription in your Apple App Store subscription settings.'
                    : 'Manage or cancel this subscription in your Google Play subscription settings.',
            ], 409);
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $accessUntil = $subscription->current_period_end ?? $subscription->expires_at;

        return response()->json([
            'success' => true,
            'message' => $accessUntil
                ? 'Subscription cancelled. Access continues until ' . $accessUntil->format('d M Y') . '.'
                : 'Subscription cancelled.',
            'data' => [
                'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
                'access_until' => $accessUntil?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get household's payment history.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $household = $user->activeHousehold();

        if (!$household) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $payments = $household->payments()
            ->with('plan:id,name,slug')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'payment_method' => $p->payment_method,
                'status' => $p->status,
                'plan' => $p->plan?->name,
                'paid_by' => $p->user?->name,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Household entitlement / usage summary (command.txt §22 usage endpoint).
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $household = $user->activeHousehold();

        if (!$household) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => (new EntitlementService())->summary($household),
        ]);
    }

    private function guessBillingType(Subscription $subscription): string
    {
        if (!$subscription->current_period_start || !$subscription->current_period_end) {
            return 'monthly';
        }
        $days = $subscription->current_period_start->diffInDays($subscription->current_period_end);
        return $days > 35 ? 'annual' : 'monthly';
    }
}
