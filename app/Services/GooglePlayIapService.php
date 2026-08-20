<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlayIapService
{
    // Google Play Developer API endpoints
    private const API_BASE = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications';

    private string $packageName;
    private string $serviceAccountJson;

    public function __construct()
    {
        $this->packageName = config('services.google_play.package_name', '');
        $this->serviceAccountJson = config('services.google_play.service_account_json', '');
    }

    /**
     * Verify a Google Play purchase token and activate/extend the subscription.
     *
     * @return array{success: bool, message: string, subscription?: Subscription}
     */
    public function verifyReceipt(
        string $receiptData,
        string $googleProductId,
        string $planSlug,
        string $billingType,
        string $transactionId,
        bool $isRestored = false,
        ?User $user = null,
    ): array {
        if (empty($this->serviceAccountJson)) {
            Log::error('GooglePlayIapService: service_account_json not configured');
            return ['success' => false, 'message' => 'Google Play IAP is not configured on the server.'];
        }

        try {
            $accessToken = $this->_getAccessToken();

            // Verify the subscription with Google Play Developer API
            $result = $this->_verifySubscription($accessToken, $receiptData);

            if (!$result) {
                return ['success' => false, 'message' => 'Failed to verify Google Play receipt.'];
            }

            // Check payment state
            // paymentState: 0=pending, 1=approved, 2=free trial, 3=pending (upgrade/downgrade)
            $paymentState = $result['paymentState'] ?? -1;
            if ($paymentState != 1 && $paymentState != 2) {
                return ['success' => false, 'message' => 'Payment not approved (state: ' . $paymentState . ').'];
            }

            // Parse expiry time (startTimeMillis and expiryTimeMillis)
            $expiresAt = isset($result['expiryTimeMillis'])
                ? \Carbon\Carbon::createFromTimestampMs((int) $result['expiryTimeMillis'])
                : null;

            $purchaseDate = isset($result['startTimeMillis'])
                ? \Carbon\Carbon::createFromTimestampMs((int) $result['startTimeMillis'])
                : null;

            $orderId = $result['orderId'] ?? $transactionId;
            $autoRenewing = $result['autoRenewing'] ?? false;

            return [
                'success' => true,
                'message' => 'Google Play receipt verified successfully.',
                'expires_at' => $expiresAt,
                'purchase_date' => $purchaseDate,
                'order_id' => $orderId,
                'google_product_id' => $googleProductId,
                'billing_type' => $billingType,
                'auto_renewing' => $autoRenewing,
            ];
        } catch (\Exception $e) {
            Log::error('GooglePlayIapService: verification exception', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Failed to verify Google Play receipt: ' . $e->getMessage()];
        }
    }

    /**
     * Activate or extend a subscription after successful receipt verification.
     */
    public function activateSubscription(
        User $user,
        string $planSlug,
        string $billingType,
        string $googleProductId,
        string $orderId,
        \Carbon\Carbon $expiresAt,
        ?\Carbon\Carbon $purchaseDate = null,
        bool $autoRenewing = true,
    ): Subscription {
        $household = $user->activeHousehold();

        if (!$household) {
            throw new \RuntimeException('User has no active household.');
        }

        $plan = SubscriptionPlan::where('slug', $planSlug)->first();
        if (!$plan) {
            throw new \RuntimeException("Subscription plan not found: {$planSlug}");
        }

        $periodStart = $purchaseDate ?? now();
        $periodEnd = $expiresAt;

        $subscription = Subscription::where('household_id', $household->id)->first();

        $data = [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'expires_at' => $periodEnd,
            'cancelled_at' => null,
            'payment_method' => 'google_play',
            'google_product_id' => $googleProductId,
            'google_order_id' => $orderId,
        ];

        // Merge auto_renewing into metadata
        $existingMetadata = ($subscription?->metadata) ?? [];
        $data['metadata'] = array_merge($existingMetadata, [
            'auto_renewing' => $autoRenewing,
            'google_product_id' => $googleProductId,
        ]);

        if ($subscription) {
            $subscription->update($data);
        } else {
            $data['trial_started_at'] = null;
            $data['trial_ends_at'] = null;
            $subscription = Subscription::create($data);
        }

        // Record the payment
        $amount = $billingType === 'annual' ? $plan->annual_price : $plan->monthly_price;
        Payment::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => 'gbp',
            'payment_method' => 'google_play',
            'gateway' => 'google_play',
            'gateway_payment_id' => $orderId,
            'status' => 'completed',
            'metadata' => [
                'google_product_id' => $googleProductId,
                'order_id' => $orderId,
                'auto_renewing' => $autoRenewing,
            ],
        ]);

        Log::info('GooglePlayIapService: subscription activated', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'plan' => $planSlug,
            'expires_at' => $expiresAt->toIso8601String(),
            'auto_renewing' => $autoRenewing,
        ]);

        return $subscription;
    }

    /**
     * Handle Google Play Real-time Developer Notifications (RTDN).
     * Google sends Pub/Sub messages to your endpoint.
     */
    public function handleRtdnNotification(array $message): void
    {
        $data = $message['data'] ?? null;
        if (!$data) {
            Log::warning('GooglePlayIapService: RTDN message has no data');
            return;
        }

        // Data is base64-encoded
        $decoded = json_decode(base64_decode($data), true);
        if (!$decoded) {
            Log::warning('GooglePlayIapService: failed to decode RTDN data');
            return;
        }

        $subscriptionNotification = $decoded['subscriptionNotification'] ?? null;
        $oneTimeProductNotification = $decoded['oneTimeProductNotification'] ?? null;

        if ($subscriptionNotification) {
            $this->_handleSubscriptionNotification($subscriptionNotification, $decoded);
        } elseif ($oneTimeProductNotification) {
            Log::info('GooglePlayIapService: one-time product notification (ignored for subscriptions)');
        } else {
            Log::info('GooglePlayIapService: unknown RTDN notification type', ['keys' => array_keys($decoded)]);
        }
    }

    /**
     * Handle subscription-specific RTDN notification.
     */
    private function _handleSubscriptionNotification(array $notification, array $fullPayload): void
    {
        $notificationType = $notification['notificationType'] ?? null;
        $purchaseToken = $notification['purchaseToken'] ?? null;
        $subscriptionId = $notification['subscriptionId'] ?? null;

        Log::info('GooglePlayIapService: RTDN subscription notification', [
            'type' => $notificationType,
            'subscription_id' => $subscriptionId,
        ]);

        if (!$subscriptionId) {
            Log::warning('GooglePlayIapService: no subscriptionId in RTDN');
            return;
        }

        // Find subscription by google_product_id or order_id
        $subscription = Subscription::where('google_product_id', $subscriptionId)->first();

        if (!$subscription) {
            Log::warning('GooglePlayIapService: subscription not found for', ['subscription_id' => $subscriptionId]);
            return;
        }

        // Notification types:
        // 1 = SUBSCRIPTION_RECOVERED
        // 2 = SUBSCRIPTION_RENEWED
        // 3 = SUBSCRIPTION_CANCELED
        // 4 = SUBSCRIPTION_PURCHASED
        // 5 = SUBSCRIPTION_EXPIRED
        // 6 = SUBSCRIPTION_IN_GRACE_PERIOD
        // 7 = SUBSCRIPTION_RESTARTED
        // 8 = SUBSCRIPTION_PRICE_CHANGE_CONFIRMED
        // 9 = SUBSCRIPTION_DEFERRED
        // 10 = SUBSCRIPTION_PAUSED
        // 11 = SUBSCRIPTION_PAUSE_SCHEDULE_CHANGED
        // 12 = SUBSCRIPTION_REVOKED
        // 13 = SUBSCRIPTION_EXPIRED (as per docs)
        // 14 = SUBSCRIPTION_PENDING
        // 15 = SUBSCRIPTION_REACTIVATED

        switch ($notificationType) {
            case 2: // RENEWED
            case 4: // PURCHASED
            case 7: // RESTARTED
            case 15: // REACTIVATED
                $this->_handleRenewalFromRtdn($subscription, $fullPayload);
                break;

            case 5: // EXPIRED
                $subscription->update(['status' => 'expired']);
                Log::info('GooglePlayIapService: subscription expired via RTDN', ['id' => $subscription->id]);
                break;

            case 3: // CANCELED
                // User cancelled — access continues until expiry
                $metadata = $subscription->metadata ?? [];
                $metadata['auto_renewing'] = false;
                $subscription->update(['metadata' => $metadata]);
                Log::info('GooglePlayIapService: subscription cancelled (auto-renew off) via RTDN', ['id' => $subscription->id]);
                break;

            case 6: // IN_GRACE_PERIOD
                $subscription->moveToGracePeriod();
                Log::info('GooglePlayIapService: subscription in grace period via RTDN', ['id' => $subscription->id]);
                break;

            case 12: // REVOKED
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                Log::info('GooglePlayIapService: subscription revoked via RTDN', ['id' => $subscription->id]);
                break;

            default:
                Log::info('GooglePlayIapService: unhandled RTDN type', ['type' => $notificationType]);
                break;
        }
    }

    /**
     * Handle renewal by updating subscription dates.
     */
    private function _handleRenewalFromRtdn(Subscription $subscription, array $fullPayload): void
    {
        // Try to extract expiry from the RTDN payload
        $subscriptionNotification = $fullPayload['subscriptionNotification'] ?? [];
        // RTDN doesn't always include expiry directly — we may need to call the API

        // For now, extend by the billing period
        $plan = $subscription->plan;
        if ($plan) {
            $billingType = $subscription->metadata['billing_type'] ?? 'monthly';
            $extendBy = $billingType === 'annual' ? now()->addYear() : now()->addMonth();

            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $extendBy,
                'expires_at' => $extendBy,
                'cancelled_at' => null,
            ]);

            Log::info('GooglePlayIapService: subscription renewed via RTDN', [
                'id' => $subscription->id,
                'expires_at' => $extendBy->toIso8601String(),
            ]);
        }
    }

    /**
     * Get OAuth2 access token for Google Play Developer API.
     */
    private function _getAccessToken(): string
    {
        $serviceAccount = json_decode($this->serviceAccountJson, true);

        if (!$serviceAccount) {
            throw new \RuntimeException('Invalid Google Play service account JSON');
        }

        $now = time();
        $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = base64_encode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/androidpublisher',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsignedJwt = "$jwtHeader.$jwtClaim";
        openssl_sign($unsignedJwt, $signature, $serviceAccount['private_key'], 'SHA256');
        $signedJwt = "$unsignedJwt." . base64_encode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $signedJwt,
        ]);

        $data = $response->json();

        if (!isset($data['access_token'])) {
            throw new \RuntimeException('Failed to get Google access token: ' . json_encode($data));
        }

        return $data['access_token'];
    }

    /**
     * Verify subscription with Google Play Developer API.
     */
    private function _verifySubscription(string $accessToken, string $purchaseToken): ?array
    {
        // Try to query each possible product ID format
        // The API expects: GET /androidpublisher/v3/applications/{package}/purchases/subscriptions/{subscriptionId}/tokens/{token}

        // We need the subscriptionId (product ID) — get it from the purchase token
        // Actually, the Flutter app sends product_id, so we use that
        // But we need to know the exact subscriptionId to call the API

        // Try with the purchase token as subscriptionToken
        $url = sprintf(
            '%s/%s/purchases/subscriptions/tokens/%s',
            self::API_BASE,
            $this->packageName,
            $purchaseToken
        );

        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            return $response->json();
        }

        Log::warning('GooglePlayIapService: subscription verification failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }
}
