<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
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

        $jsonPath = config('services.google_play.service_account_json_path', '');
        if (!empty($jsonPath) && file_exists($jsonPath)) {
            $this->serviceAccountJson = file_get_contents($jsonPath);
        } else {
            $this->serviceAccountJson = '';
        }
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

        Log::info('GooglePlayIapService: verifyReceipt start', [
            'product_id' => $googleProductId,
            'plan_slug' => $planSlug,
            'billing_type' => $billingType,
            'user_id' => $user?->id,
        ]);

        try {
            $accessToken = $this->_getAccessToken();
            Log::info('GooglePlayIapService: got access token');

            // Resolve plan + billing period from the central product config so
            // the server (not the client) is the source of truth. The client
            // should never send plan/billing info to Google - only the receipt.
            $googleProducts = config('google_products.google_products', []);
            if (empty($googleProducts) || !isset($googleProducts[$googleProductId])) {
                Log::warning('GooglePlayIapService: unknown Google product ID in config', ['product_id' => $googleProductId]);
                return ['success' => false, 'message' => 'Unknown Google product.'];
            }
            $cfg = $googleProducts[$googleProductId];
            $planSlug = $cfg['plan'] ?? throw new \Exception('plan slug missing in config for ' . $googleProductId);
            $billingType = $cfg['billing_period'] ?? throw new \Exception('billing_period missing in config for ' . $googleProductId);

            // Verify the subscription with Google Play Developer API
            $result = $this->_verifySubscription($accessToken, $receiptData, $googleProductId);

            if (!$result) {
                Log::error('GooglePlayIapService: _verifySubscription returned null');
                return ['success' => false, 'message' => 'Failed to verify Google Play receipt.'];
            }

            Log::info('GooglePlayIapService: Google API response', [
                'paymentState' => $result['paymentState'] ?? 'missing',
                'orderId' => $result['orderId'] ?? 'missing',
                'expiryTimeMillis' => $result['expiryTimeMillis'] ?? 'missing',
                'autoRenewing' => $result['autoRenewing'] ?? 'missing',
            ]);

            // Check payment state
            // paymentState: 0=pending, 1=approved, 2=free trial
            // Mapped from v2 subscriptionState: ACTIVE/IN_GRACE_PERIOD=1, PENDING=0
            $paymentState = $result['paymentState'] ?? -1;
            if ($paymentState != 1 && $paymentState != 2) {
                Log::warning('GooglePlayIapService: payment not approved', ['paymentState' => $paymentState]);
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
        bool $isRestored = false,
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

        // The HouseholdOS trial is controlled entirely by the backend. Apple/Google
        // dates are authoritative — do NOT manufacture an expiry date locally.

        $existingSubscription = Subscription::where('household_id', $household->id)->first();

        $subscription = DB::transaction(function () use ($user, $household, $plan, $billingType, $googleProductId, $orderId, $autoRenewing, $isRestored, $existingSubscription, $periodStart, $periodEnd) {
            $data = [
                'user_id' => $user->id,
                'household_id' => $household->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'plan_status' => 'paid',
                'paid_plan' => $plan->slug,
                'billing_period' => $billingType,
                'provider' => 'google_play',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'expires_at' => $periodEnd,
                'cancelled_at' => null,
                'payment_method' => 'google_play',
                'google_product_id' => $googleProductId,
                'google_order_id' => $orderId,
                'original_transaction_id' => $orderId,
                'latest_transaction_id' => $orderId,
                'last_verified_at' => now(),
                'trial_started_at' => null,
                'trial_ends_at' => null,
            ];

            // Merge auto_renewing into metadata
            $existingMetadata = ($existingSubscription?->metadata) ?? [];
            $data['metadata'] = array_merge($existingMetadata, [
                'auto_renewing' => $autoRenewing,
                'google_product_id' => $googleProductId,
            ]);

            if ($existingSubscription) {
                $existingSubscription->update($data);
                $subscription = $existingSubscription;
            } else {
                $subscription = Subscription::create($data);
            }

            // Ensure only one active subscription per household.
            Subscription::where('household_id', $household->id)
                ->where('id', '!=', $subscription->id)
                ->update(['status' => 'replaced']);

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

            // Record the transaction for a full audit trail.
            SubscriptionTransaction::create([
                'subscription_id' => $subscription->id,
                'transaction_id' => $orderId,
                'original_transaction_id' => $orderId,
                'product_id' => $googleProductId,
                'environment' => 'google_play',
                'purchase_date' => $periodStart,
                'expires_date' => $periodEnd,
                'transaction_reason' => $isRestored ? 'restore' : 'purchase',
            ]);

            Log::info('GooglePlayIapService: subscription activated', [
                'user_id' => $user->id,
                'household_id' => $household->id,
                'plan' => $plan->slug,
                'expires_at' => $periodEnd->toIso8601String(),
                'auto_renewing' => $autoRenewing,
            ]);

            return $subscription;
        });

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
     * Extends from current_period_end (not now()) so renewals add correctly.
     */
    private function _handleRenewalFromRtdn(Subscription $subscription, array $fullPayload): void
    {
        // Try to extract expiry from the RTDN payload
        $subscriptionNotification = $fullPayload['subscriptionNotification'] ?? [];
        // RTDN doesn't always include expiry directly — we may need to call the API

        $plan = $subscription->plan;
        if ($plan) {
            $billingType = $subscription->billing_period ?? 'monthly';
            // Anchor on the existing current_period_end so a renewal of a
            // year-long sub adds a full year (not "today + 1 year" which
            // would shorten the access window on every renewal).
            $anchor = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end
                : now();
            $extendBy = $billingType === 'annual' ? $anchor->copy()->addYear() : $anchor->copy()->addMonth();

            $subscription->update([
                'status' => 'active',
                'current_period_start' => $anchor,
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
     * Re-query Google Play for the authoritative subscription state and
     * apply it locally. Used by the on-demand refresh path.
     * - If Google's expiry is in the past, the sub is marked expired.
     * - If Google says active, the expires_at is updated to Google's value.
     * - If Google says expired but our local expires_at is still in the
     *   future, we keep the local active status to avoid a transient
     *   false-expiry (same guard as AppleIapService::applyRawStatus).
     */
    public function refreshFromGoogle(Subscription $subscription): bool
    {
        if (!$subscription->google_order_id || empty($this->serviceAccountJson)) {
            return false;
        }

        try {
            $accessToken = $this->_getAccessToken();

            // For v2 API, we need the purchaseToken, not the orderId. The
            // orderId alone cannot be re-verified. We can attempt via the
            // v1 endpoint using orderId as a token, but it's deprecated.
            // Use the latest_transaction_id field if it contains the token.
            $token = $subscription->google_order_id;
            $subId = $subscription->google_product_id;

            $url = sprintf(
                '%s/%s/purchases/subscriptionsv2/tokens/%s',
                self::API_BASE,
                $this->packageName,
                $token
            );
            $response = Http::withToken($accessToken)->get($url);

            if (!$response->successful()) {
                Log::warning('GooglePlayIapService::refreshFromGoogle: API call failed', [
                    'status' => $response->status(),
                ]);
                return false;
            }

            $data = $response->json();
            $subscriptionState = $data['subscriptionState'] ?? 'SUBSCRIPTION_STATE_PENDING';
            $lineItem = $data['lineItems'][0] ?? [];
            $expiryTime = $lineItem['expiryTime'] ?? null;
            $expiresAt = $expiryTime ? \Carbon\Carbon::parse($expiryTime) : null;

            $currentStatus = $subscription->status;
            $newStatus = match ($subscriptionState) {
                'SUBSCRIPTION_STATE_ACTIVE' => 'active',
                'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace_period',
                'SUBSCRIPTION_STATE_CANCELED' => 'cancelled',
                'SUBSCRIPTION_STATE_EXPIRED' => 'expired',
                default => $currentStatus,
            };

            // Guard: don't downgrade from active unless Google's expiresAt is
            // genuinely in the past. (Same logic as AppleIapService::applyRawStatus.)
            $finalStatus = $newStatus;
            if ($currentStatus === 'active' && in_array($newStatus, ['expired', 'cancelled'], true)) {
                if ($expiresAt && now()->isAfter($expiresAt)) {
                    $finalStatus = $newStatus;
                } else {
                    $finalStatus = 'active';
                    Log::warning('GooglePlayIapService::refreshFromGoogle: ignoring downgrade', [
                        'subscription_id' => $subscription->id,
                        'google_state' => $subscriptionState,
                    ]);
                }
            }

            $update = [
                'status' => $finalStatus,
                'last_verified_at' => now(),
            ];
            if ($expiresAt) {
                $update['current_period_end'] = $expiresAt;
                $update['expires_at'] = $expiresAt;
            }
            $subscription->update($update);

            Log::info('GooglePlayIapService::refreshFromGoogle: updated', [
                'subscription_id' => $subscription->id,
                'status' => $finalStatus,
                'expires_at' => $expiresAt?->toIso8601String(),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('GooglePlayIapService::refreshFromGoogle: exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
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
     * Uses the v2 API (purchases.subscriptionsv2.get) as the v1 endpoint
     * (purchases.subscriptions.get) is deprecated and restricted.
     */
    private function _verifySubscription(string $accessToken, string $purchaseToken, string $subscriptionId): ?array
    {
        if (empty($purchaseToken)) {
            Log::warning('GooglePlayIapService: missing purchaseToken for verification');
            return null;
        }

        // GET .../purchases/subscriptionsv2/tokens/{token}
        // Note: v2 endpoint does not include subscriptionId in the URL path.
        $url = sprintf(
            '%s/%s/purchases/subscriptionsv2/tokens/%s',
            self::API_BASE,
            $this->packageName,
            $purchaseToken
        );

        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            $data = $response->json();

            // Normalize v2 response to match the legacy v1 format expected
            // by the rest of the code (paymentState, orderId, expiryTimeMillis, etc.)
            $subscriptionState = $data['subscriptionState'] ?? 'SUBSCRIPTION_STATE_PENDING';

            $paymentState = match ($subscriptionState) {
                'SUBSCRIPTION_STATE_ACTIVE',
                'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 1,

                'SUBSCRIPTION_STATE_PENDING' => 0,

                default => 0,
            };

            $lineItem = $data['lineItems'][0] ?? [];
            $expiryTime = $lineItem['expiryTime'] ?? null;
            $startTime = $data['startTime'] ?? null;
            $latestOrderId = $data['latestOrderId'] ?? null;
            $autoRenewing = $lineItem['autoRenewingPlan']['autoRenewEnabled'] ?? false;

            return [
                'paymentState' => $paymentState,
                'orderId' => $latestOrderId,
                'expiryTimeMillis' => $expiryTime ? \Carbon\Carbon::parse($expiryTime)->valueOf() : null,
                'startTimeMillis' => $startTime ? \Carbon\Carbon::parse($startTime)->valueOf() : null,
                'autoRenewing' => $autoRenewing,
                'subscriptionState' => $subscriptionState,
                'productId' => $lineItem['productId'] ?? $subscriptionId,
                'testPurchase' => $data['testPurchase'] ?? null,
            ];
        }

        Log::warning('GooglePlayIapService: subscription verification failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }
}
