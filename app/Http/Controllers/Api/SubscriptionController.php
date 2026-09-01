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

        $subscription->load(['plan', 'subscriber', 'user']);
        $household = $user->activeHousehold();

        $payerId = $subscription->subscriber_user_id ?? $subscription->user_id;
        $isSubscriber = ($user->id === $payerId);
        $isCreator = $household ? ($household->created_by_user_id === $user->id) : false;
        $payer = $subscription->subscriber ?? $subscription->user;
        $payerName = $payer ? ($payer->first_name ? $payer->first_name . ' ' . $payer->last_name : ($payer->name ?? $payer->email)) : null;

        $hasActivePaidOrTrial = $subscription->isActive() || $subscription->isTrial() || $subscription->isInGracePeriod();
        $canPurchase = !$hasActivePaidOrTrial;
        $canManage = $isSubscriber;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'subscriber_user_id' => $payerId,
                'is_subscriber' => $isSubscriber,
                'is_creator' => $isCreator,
                'payer_name' => $payerName,
                'can_manage' => $canManage,
                'can_purchase' => $canPurchase,
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
                'message' => 'Only the household admin can cancel the subscription.',
            ], 403);
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled. Access continues until ' . $subscription->current_period_end->format('d M Y') . '.',
            'data' => [
                'cancelled_at' => $subscription->cancelled_at->toIso8601String(),
                'access_until' => $subscription->current_period_end->toIso8601String(),
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
