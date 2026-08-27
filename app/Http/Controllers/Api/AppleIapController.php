<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppleIapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppleIapController extends Controller
{
    public function __construct(
        private AppleIapService $appleIap,
    ) {}

    /**
     * Verify an Apple purchase and activate the household subscription.
     * Flutter sends only the Apple transaction id (command.txt §23 / §24).
     * The server re-verifies with Apple — Flutter is never the source of truth.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'app_account_token' => 'nullable|string',
            'device_id' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            Log::warning('AppleIapController@verify: no authenticated user', [
                'transaction_id' => $request->transaction_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        Log::info('AppleIapController@verify: start', [
            'user_id' => $user->id,
            'transaction_id' => $request->transaction_id,
            'has_app_account_token' => !empty($request->input('app_account_token')),
        ]);

        try {
            $result = $this->appleIap->verifyAndActivate(
                user: $user,
                transactionId: $request->transaction_id,
                appAccountToken: $request->input('app_account_token'),
                deviceId: $request->input('device_id'),
            );

            if (!$result['success']) {
                // This is the most common "no log" culprit: verifyAndActivate
                // returns a failure reason that we must surface for debugging.
                Log::warning('AppleIapController@verify: verification failed', [
                    'user_id' => $user->id,
                    'transaction_id' => $request->transaction_id,
                    'message' => $result['message'] ?? 'unknown',
                    'code' => $result['code'] ?? null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            $subscription = $result['subscription'];
            $subscription->load('plan');

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                    ],
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                    'expires_at' => $subscription->expires_at?->toIso8601String(),
                    'is_active' => $subscription->isActive(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AppleIapController@verify: exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify Apple purchase.',
            ], 500);
        }
    }

    /**
     * Restore purchases: verify an original transaction ID and activate
     * the subscription if valid (command.txt §33).
     */
    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'original_transaction_id' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        try {
            $result = $this->appleIap->restoreSubscription(
                user: $user,
                originalTransactionId: $request->original_transaction_id,
                deviceId: $request->input('device_id'),
            );

            if (!$result['success']) {
                Log::warning('AppleIapController@restore: verification failed', [
                    'user_id' => $user->id,
                    'original_transaction_id' => $request->original_transaction_id,
                    'message' => $result['message'] ?? 'unknown',
                    'code' => $result['code'] ?? null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            $subscription = $result['subscription'];
            $subscription->load('plan');

            return response()->json([
                'success' => true,
                'message' => 'Subscription restored successfully.',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'slug' => $subscription->plan->slug,
                    ],
                    'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                    'expires_at' => $subscription->expires_at?->toIso8601String(),
                    'is_active' => $subscription->isActive(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AppleIapController@restore: exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore Apple purchase.',
            ], 500);
        }
    }

    /**
     * Apple App Store Server Notifications V2 webhook.
     * Receives a JWS signedPayload; verification happens in AppleIapService.
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('AppleIapController@webhook: received notification', [
                'type' => $payload['notificationType'] ?? 'unknown',
            ]);

            $this->appleIap->handleServerNotification($payload);

            // Apple expects a 200 response for acknowledged notifications.
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('AppleIapController@webhook: exception', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}
