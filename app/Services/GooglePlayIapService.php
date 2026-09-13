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

            // A transient active response may still carry the previous expiry.
            // Re-query once before asking the client to retry; never invent time.
            if ($result && in_array($result['subscriptionState'] ?? '', [
                'SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
            ], true) && (empty($result['expiryTimeMillis']) || (int) $result['expiryTimeMillis'] <= (int) now()->valueOf())) {
                $result = $this->_verifySubscription($accessToken, $receiptData, $googleProductId);
            }
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
                ? \Carbon\Carbon::instance(GoogleSubscriptionSnapshot::milliseconds((int) $result['expiryTimeMillis'], config('app.timezone', 'UTC')))
                : null;

            $purchaseDate = isset($result['startTimeMillis'])
                ? \Carbon\Carbon::instance(GoogleSubscriptionSnapshot::milliseconds((int) $result['startTimeMillis'], config('app.timezone', 'UTC')))
                : null;

            if (!$expiresAt || $expiresAt->lessThanOrEqualTo(now())) {
                return ['success' => false, 'message' => 'Google has not confirmed a current expiry. Please retry verification.'];
            }
            $orderId = $result['orderId'] ?? null;
            if (!$orderId) {
                return ['success' => false, 'message' => 'Google has not confirmed the purchase order. Please retry verification.'];
            }
            $autoRenewing = $result['autoRenewing'] ?? false;

            return [
                'success' => true,
                'message' => 'Google Play receipt verified successfully.',
                'expires_at' => $expiresAt,
                'purchase_date' => $purchaseDate,
                'order_id' => $orderId,
                'google_product_id' => $googleProductId,
                'plan_slug' => $planSlug,
                'subscription_state' => $result['subscriptionState'],
                'billing_type' => $billingType,
                'auto_renewing' => $autoRenewing,
            ];
        } catch (\Exception $e) {
            Log::error('GooglePlayIapService: verification exception', [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'Failed to verify Google Play receipt. Please try again.'];
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
        ?string $purchaseToken = null,
        string $subscriptionState = 'SUBSCRIPTION_STATE_ACTIVE',
    ): Subscription {
        $household = $user->activeHousehold();

        if (!$household) {
            throw new \RuntimeException('User has no active household.');
        }

        $cfg = config('google_products.google_products', [])[$googleProductId] ?? null;
        if (!$cfg) {
            throw new \RuntimeException('Unknown Google product.');
        }
        $planSlug = $cfg['plan'];
        $billingType = $cfg['billing_period'];
        $plan = SubscriptionPlan::where('slug', $planSlug)->first();
        if (!$plan) {
            throw new \RuntimeException("Subscription plan not found: {$planSlug}");
        }

        $timezone = config('app.timezone', 'UTC');
        $periodStart = ($purchaseDate ?? now())->copy()->setTimezone($timezone);
        $periodEnd = $expiresAt->copy()->setTimezone($timezone);
        if ($periodEnd->lessThanOrEqualTo(now())) {
            throw new \RuntimeException('Google has not confirmed a current subscription expiry.');
        }

        $existingSubscription = Subscription::where('household_id', $household->id)->first();

        $subscription = DB::transaction(function () use ($user, $household, $plan, $billingType, $googleProductId, $orderId, $autoRenewing, $isRestored, $existingSubscription, $periodStart, $periodEnd, $purchaseToken, $subscriptionState) {
            $decision = GoogleSubscriptionSnapshot::resolve($subscriptionState, $periodEnd, now());
            $data = [
                'user_id' => $user->id,
                'household_id' => $household->id,
                'subscription_plan_id' => $plan->id,
                'status' => $decision['status'],
                'plan_status' => 'paid',
                'paid_plan' => $plan->slug,
                'billing_period' => $billingType,
                'provider' => 'google_play',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'expires_at' => $periodEnd,
                'cancelled_at' => null,
                'grace_period_expires_at' => $decision['status'] === 'grace_period' ? $periodEnd : null,
                'auto_renew' => $autoRenewing,
                'payment_method' => 'google_play',
                'google_product_id' => $googleProductId,
                'google_order_id' => $orderId,
                'google_purchase_token' => $purchaseToken,
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

        if (!$purchaseToken || ($fullPayload['packageName'] ?? null) !== $this->packageName) {
            Log::warning('GooglePlayIapService: RTDN token or package mismatch');
            return;
        }
        // Product IDs are shared by every purchaser. Only a stored purchase
        // token identifies the household affected by this notification.
        $subscription = Subscription::where('google_purchase_token', $purchaseToken)
            ->where('google_product_id', $subscriptionId)
            ->first();
        if (!$subscription) {
            Log::info('GooglePlayIapService: RTDN token has no local subscription');
            return;
        }
        // RTDN is a hint to re-query Google, never proof of another month of
        // access. Replays and duplicate deliveries therefore cannot extend it.
        if (!$this->refreshFromGoogle($subscription)) {
            throw new \RuntimeException('Google subscription refresh failed; retry RTDN.');
        }
    }

    /** Refresh only the stored token, protecting newer purchases from in-flight responses. */
    public function refreshFromGoogle(Subscription $subscription): bool
    {
        $token = $subscription->google_purchase_token;
        $productId = $subscription->google_product_id;
        $previousOrder = $subscription->google_order_id;
        $previousEnd = $subscription->expires_at?->getTimestamp();
        $previousStatus = $subscription->status;
        if (empty($token) || empty($productId) || empty($this->serviceAccountJson)) {
            return false;
        }
        try {
            $result = $this->_verifySubscription($this->_getAccessToken(), $token, $productId);
            if (!$result || empty($result['expiryTimeMillis'])) {
                return false;
            }
            $incomingEnd = \Carbon\Carbon::instance(GoogleSubscriptionSnapshot::milliseconds(
                (int) $result['expiryTimeMillis'], config('app.timezone', 'UTC')
            ));
            return DB::transaction(function () use ($subscription, $token, $productId, $previousOrder, $previousEnd, $previousStatus, $result, $incomingEnd) {
                $current = Subscription::whereKey($subscription->id)->lockForUpdate()->first();
                if (!$current || $current->google_purchase_token !== $token
                    || $current->google_product_id !== $productId
                    || $current->google_order_id !== $previousOrder
                    || $current->expires_at?->getTimestamp() !== $previousEnd
                    || $current->status !== $previousStatus) {
                    // A purchase/refresh completed while the network call was in flight.
                    return false;
                }
                $decision = GoogleSubscriptionSnapshot::resolve(
                    $result['subscriptionState'], $incomingEnd, now(),
                    $current->expires_at, $current->status,
                    !empty($result['orderId']) && $result['orderId'] === $current->google_order_id,
                );
                $end = \Carbon\Carbon::instance($decision['expires_at'])->setTimezone(config('app.timezone', 'UTC'));
                $current->update([
                    'status' => $decision['status'],
                    'current_period_end' => $end,
                    'expires_at' => $end,
                    'grace_period_expires_at' => $decision['status'] === 'grace_period' ? $end : null,
                    'google_order_id' => $result['orderId'] ?? $current->google_order_id,
                    'latest_transaction_id' => $result['orderId'] ?? $current->latest_transaction_id,
                    'auto_renew' => (bool) $result['autoRenewing'],
                    'metadata' => array_merge($current->metadata ?? [], ['auto_renewing' => (bool) $result['autoRenewing']]),
                    'last_verified_at' => now(),
                ]);
                Log::info('GooglePlayIapService: refreshed subscription', [
                    'subscription_id' => $current->id,
                    'status' => $current->status,
                    'google_expires_at' => $incomingEnd->toIso8601String(),
                    'saved_expires_at' => $current->expires_at?->toIso8601String(),
                ]);
                return true;
            });
        } catch (\Exception $e) {
            Log::warning('GooglePlayIapService: refresh deferred', ['error' => $e->getMessage()]);
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

        $response = Http::withToken($accessToken)->timeout(10)->get($url);

        if ($response->successful()) {
            $data = $response->json();

            // Normalize v2 response to match the legacy v1 format expected
            // by the rest of the code (paymentState, orderId, expiryTimeMillis, etc.)
            $subscriptionState = $data['subscriptionState'] ?? 'SUBSCRIPTION_STATE_PENDING';

            $paymentState = match ($subscriptionState) {
                'SUBSCRIPTION_STATE_ACTIVE',
                'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
                'SUBSCRIPTION_STATE_CANCELED' => 1,

                'SUBSCRIPTION_STATE_PENDING' => 0,

                default => 0,
            };

            $matches = array_values(array_filter($data['lineItems'] ?? [],
                fn ($item) => ($item['productId'] ?? null) === $subscriptionId));
            if (count($matches) !== 1) {
                Log::warning('GooglePlayIapService: receipt product mismatch or ambiguous line items');
                return null;
            }
            $lineItem = $matches[0];
            $expiryTime = $lineItem['expiryTime'] ?? null;
            $startTime = $data['startTime'] ?? null;
            $latestOrderId = $lineItem['latestSuccessfulOrderId'] ?? $data['latestOrderId'] ?? null;
            $autoRenewing = $lineItem['autoRenewingPlan']['autoRenewEnabled'] ?? false;

            return [
                'paymentState' => $paymentState,
                'orderId' => $latestOrderId,
                'expiryTimeMillis' => $expiryTime ? \Carbon\Carbon::instance(GoogleSubscriptionSnapshot::timestamp($expiryTime, config('app.timezone', 'UTC')))->valueOf() : null,
                'startTimeMillis' => $startTime ? \Carbon\Carbon::instance(GoogleSubscriptionSnapshot::timestamp($startTime, config('app.timezone', 'UTC')))->valueOf() : null,
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
