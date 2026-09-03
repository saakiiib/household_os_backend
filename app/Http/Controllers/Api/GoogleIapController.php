<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GooglePlayIapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleIapController extends Controller
{
    public function __construct(
        private GooglePlayIapService $googlePlay,
    ) {}

    /**
     * Verify a Google Play receipt and activate/extend subscription.
     * Called from the Flutter app after a successful Google Play purchase.
     */
    public function verify(Request $request): JsonResponse
    {
        \Log::info('GoogleIapController@verify: start', [
            'user_id' => $request->user()?->id,
            'product_id' => $request->product_id,
            'plan_slug' => $request->plan_slug,
            'billing_type' => $request->billing_type,
        ]);

        $request->validate([
            'receipt_data' => 'required|string',
            'product_id' => 'required|string',
            'plan_slug' => 'required|string',
            'billing_type' => 'required|in:monthly,annual',
            'transaction_id' => 'required|string',
            'is_restored' => 'boolean',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        try {
            $result = $this->googlePlay->verifyReceipt(
                receiptData: $request->receipt_data,
                googleProductId: $request->product_id,
                planSlug: $request->plan_slug,
                billingType: $request->billing_type,
                transactionId: $request->transaction_id,
                isRestored: $request->boolean('is_restored', false),
                user: $user,
            );

            \Log::info('GoogleIapController@verify: receipt verified', [
                'success' => $result['success'],
                'message' => $result['message'] ?? null,
            ]);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            // Activate subscription
            $subscription = $this->googlePlay->activateSubscription(
                user: $user,
                planSlug: $request->plan_slug,
                billingType: $request->billing_type,
                googleProductId: $result['google_product_id'],
                orderId: $result['order_id'],
                expiresAt: $result['expires_at'],
                purchaseDate: $result['purchase_date'],
                autoRenewing: $result['auto_renewing'] ?? true,
                isRestored: $request->boolean('is_restored', false),
            );

            \Log::info('GoogleIapController@verify: subscription activated', [
                'subscription_id' => $subscription->id,
                'household_id' => $subscription->household_id,
                'plan' => $request->plan_slug,
                'expires_at' => $result['expires_at']?->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('GoogleIapController@verify: exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Google Play receipt.',
            ], 500);
        }
    }

    /**
     * Google Play Real-time Developer Notifications (RTDN) webhook.
     * Google sends Pub/Sub push messages to this endpoint.
     * No auth required — Google authenticates via the Pub/Sub subscription.
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            // Google Pub/Sub sends a message object
            $message = $payload['message'] ?? $payload;

            \Log::info('GoogleIapController@webhook: received RTDN notification', [
                'message_id' => $message['messageId'] ?? 'unknown',
            ]);

            $this->googlePlay->handleRtdnNotification($message);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('GoogleIapController@webhook: exception', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Restore / look up an existing Google Play subscription.
     *
     * Google Play ties purchases to the Google account — there is no native
     * "restore" API like Apple's.  This endpoint simply checks whether the
     * household already has an active Google Play subscription on the server.
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'app_account_token' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $household = $user->activeHousehold();
        if (!$household) {
            return response()->json([
                'success' => false,
                'message' => 'No active household.',
            ], 404);
        }

        $subscription = \App\Models\Subscription::where('household_id', $household->id)
            ->where('payment_method', 'google_play')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere('status', 'grace_period')
                  ->orWhere('status', 'trial');
            })
            ->first();

        if ($subscription) {
            $subscription->load('plan');
            return response()->json([
                'success' => true,
                'message' => 'Google Play subscription found.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                    ],
                    'expires_at' => $subscription->expires_at?->toIso8601String(),
                    'is_active' => $subscription->isActive(),
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No active Google Play subscription found for this household.',
        ]);
    }
}
