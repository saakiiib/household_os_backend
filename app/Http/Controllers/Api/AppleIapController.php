<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppleIapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppleIapController extends Controller
{
    public function __construct(
        private AppleIapService $appleIap,
    ) {}

    /**
     * Verify an Apple receipt and activate/extend subscription.
     * Called from the Flutter app after a successful StoreKit purchase.
     */
    public function verify(Request $request): JsonResponse
    {
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
            $result = $this->appleIap->verifyReceipt(
                receiptData: $request->receipt_data,
                appleProductId: $request->product_id,
                planSlug: $request->plan_slug,
                billingType: $request->billing_type,
                transactionId: $request->transaction_id,
                isRestored: $request->boolean('is_restored', false),
                user: $user,
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            // Activate subscription
            $subscription = $this->appleIap->activateSubscription(
                user: $user,
                planSlug: $request->plan_slug,
                billingType: $request->billing_type,
                appleProductId: $result['apple_product_id'],
                originalTransactionId: $result['original_transaction_id'],
                expiresAt: $result['expires_at'],
                purchaseDate: $result['purchase_date'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error('AppleIapController@verify: exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Apple receipt.',
            ], 500);
        }
    }

    /**
     * Apple App Store Server Notifications v2 webhook.
     * This is called by Apple when subscription events occur.
     * No auth required - Apple sends these directly.
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            \Log::info('AppleIapController@webhook: received notification', [
                'type' => $payload['notificationType'] ?? $payload['type'] ?? 'unknown',
            ]);

            $this->appleIap->handleServerNotification($payload);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('AppleIapController@webhook: exception', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}
