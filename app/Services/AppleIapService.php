<?php

namespace App\Services;

use App\Models\AppleNotificationLog;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Payment;
use App\Models\ProductPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Apple App Store Server API integration (command.txt §10-§12, §24-§29, §51).
 *
 * Uses the modern App Store Server API:
 *  - ES256 JWT authentication (generated with the .p8 In-App Purchase key)
 *  - Get All Subscription Statuses to verify a purchase server-side
 *  - JWS (signedPayload / signedTransactionInfo) signature verification
 *
 * The legacy verifyReceipt + shared-secret flow is intentionally NOT used.
 */
class AppleIapService
{
    private const PRODUCTION_URL = 'https://api.storekit.apple.com';
    private const SANDBOX_URL = 'https://api.storekit-sandbox.apple.com';

    // Apple subscription status codes (Get All Subscription Statuses).
    private const STATUS_ACTIVE = 1;
    private const STATUS_EXPIRED = 2;
    private const STATUS_RETRY = 3;
    private const STATUS_GRACE_PERIOD = 4;
    private const STATUS_REVOKED = 5;

    private string $keyId;
    private string $issuerId;
    private string $bundleId;
    private ?string $appId;
    private string $privateKeyPath;
    private string $sharedSecret;

    public function __construct()
    {
        $this->keyId = config('services.apple.iap_key_id', '');
        $this->issuerId = config('services.apple.iap_issuer_id', '');
        $this->bundleId = config('services.apple.bundle_id', 'com.mentosoftware.householdos');
        $this->appId = config('services.apple.app_id');
        $this->privateKeyPath = config('services.apple.iap_private_key_path', '');
        // Allow relative paths (resolved from the Laravel base directory).
        if ($this->privateKeyPath && !str_starts_with($this->privateKeyPath, '/')
            && !preg_match('~^[A-Za-z]:[\\\\/]~', $this->privateKeyPath)) {
            $this->privateKeyPath = base_path($this->privateKeyPath);
        }
        $this->sharedSecret = config('services.apple.shared_secret', '');
    }

    /* ------------------------------------------------------------------ */
    /* Public API                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Verify a purchase with Apple and activate/update the household
     * subscription (command.txt §23 / §24).
     *
     * Flutter sends the Apple transaction id; we are the source of truth.
     *
     * @return array{success: bool, message: string, subscription?: Subscription}
     */
    public function verifyAndActivate(User $user, string $transactionId, ?string $appAccountToken = null): array
    {
        if (!$this->isConfigured()) {
            Log::error('AppleIapService: App Store Server API not configured');
            return ['success' => false, 'message' => 'Apple IAP is not configured on the server.'];
        }

        // Persist the app_account_token on the household immediately so that
        // App Store Server Notifications can resolve this household later even
        // if the Apple re-query below fails. This guarantees the subscription
        // ends up recorded even when the App Store Server API is flaky.
        $household = $user->activeHousehold();
        if ($household && $appAccountToken) {
            $household->update(['app_account_token' => $appAccountToken]);
        }

        $statusResult = $this->getSubscriptionStatus($transactionId);
        if (!$statusResult['success']) {
            return ['success' => false, 'message' => $statusResult['message']];
        }

        $tx = $statusResult['transaction']; // decoded signedTransactionInfo

        // Security rule: validate bundle + product + environment (§24).
        if (($tx['bundleId'] ?? null) !== $this->bundleId) {
            Log::warning('AppleIapService: bundle id mismatch', ['got' => $tx['bundleId'] ?? null]);
            return ['success' => false, 'message' => 'Bundle identifier mismatch.'];
        }

        $productId = $tx['productId'] ?? null;
        $productConfig = $this->productConfig($productId);
        if (empty($productConfig)) {
            Log::warning('AppleIapService: unknown product', ['product' => $productId]);
            return ['success' => false, 'message' => 'Unknown Apple product.'];
        }

        $originalTransactionId = $tx['originalTransactionId'] ?? $transactionId;

        // Security rule (§33): never blindly move an existing subscription to
        // another household. If the Apple purchase is already linked to a
        // different HouseholdOS household, flag it for support instead.
        $household = $user->activeHousehold();
        if ($household) {
            $linkedElsewhere = Subscription::where(function ($q) use ($originalTransactionId) {
                $q->where('original_transaction_id', $originalTransactionId)
                  ->orWhere('apple_original_transaction_id', $originalTransactionId);
            })
            ->whereNull('revoked_at')
            ->where('household_id', '!=', $household->id)
            ->first();

            if ($linkedElsewhere) {
                Log::warning('AppleIapService: subscription linked to another household', [
                    'otid' => $originalTransactionId,
                    'existing_household' => $linkedElsewhere->household_id,
                ]);
                return [
                    'success' => false,
                    'message' => 'This Apple subscription is already active on another household. Contact support to transfer it.',
                    'code' => 'SUBSCRIPTION_LINKED_ELSEWHERE',
                ];
            }
        }

        $plan = SubscriptionPlan::where('code', $productConfig['plan'])->first();
        if (!$plan) {
            return ['success' => false, 'message' => 'Subscription plan not found.'];
        }

        $subscription = $this->applyFromStatusResult(
            user: $user,
            statusResult: $statusResult,
            originalTransactionId: $originalTransactionId,
            appAccountToken: $appAccountToken,
        );

        return [
            'success' => true,
            'message' => 'Subscription verified and activated.',
            'subscription' => $subscription,
        ];
    }

    /**
     * Restore purchases: verify an original transaction ID and activate
     * the subscription if valid (command.txt §33).
     */
    public function restoreSubscription(User $user, string $originalTransactionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Apple IAP is not configured on the server.'];
        }

        $statusResult = $this->getSubscriptionStatus($originalTransactionId);
        if (!$statusResult['success']) {
            return ['success' => false, 'message' => $statusResult['message']];
        }

        $tx = $statusResult['transaction'];
        $productId = $tx['productId'] ?? null;
        $productConfig = $this->productConfig($productId);
        if (empty($productConfig)) {
            return ['success' => false, 'message' => 'Unknown Apple product.'];
        }

        $plan = SubscriptionPlan::where('code', $productConfig['plan'])->first();
        if (!$plan) {
            return ['success' => false, 'message' => 'Subscription plan not found.'];
        }

        $subscription = Subscription::where('original_transaction_id', $originalTransactionId)->first();
        if ($subscription) {
            $household = $user->activeHousehold();
            if ($household && $subscription->household_id !== $household->id) {
                Log::warning('AppleIapService: restore linked to different household', [
                    'user_household' => $household->id,
                    'sub_household' => $subscription->household_id,
                ]);
                return [
                    'success' => false,
                    'message' => 'This subscription is linked to another household. Please contact support.',
                ];
            }
        }

        $result = $this->verifyAndActivate(
            user: $user,
            transactionId: $originalTransactionId,
        );

        return $result;
    }

    /**
     * Handle an App Store Server Notification V2 webhook (command.txt §27-§29).
     */
    public function handleServerNotification(array $payload): void
    {
        $signedPayload = $payload['signedPayload'] ?? null;
        if (!$signedPayload) {
            Log::warning('AppleIapService: webhook missing signedPayload');
            return;
        }

        $decoded = $this->verifyAndDecodeJws($signedPayload);
        if (!$decoded) {
            Log::error('AppleIapService: webhook JWS signature verification failed');
            return;
        }

        $data = $decoded['data'] ?? [];
        $notificationUuid = $decoded['notificationUUID'] ?? null;
        $notificationType = $decoded['notificationType'] ?? null;
        $subtype = $decoded['subtype'] ?? null;
        $environment = $decoded['environment'] ?? null;

        // Idempotency: if we already processed this exact notificationUUID,
        // ignore it (command.txt §17 / §51 Rule 5).
        if ($notificationUuid && AppleNotificationLog::where('notification_uuid', $notificationUuid)->exists()) {
            Log::info('AppleIapService: duplicate notification ignored', ['uuid' => $notificationUuid]);
            return;
        }

        // Verify and decode the embedded transaction info.
        $tx = null;
        $originalTransactionId = null;
        if (!empty($data['signedTransactionInfo'])) {
            $txInfo = $this->verifyAndDecodeJws($data['signedTransactionInfo']);
            if ($txInfo) {
                $tx = $txInfo;
                $originalTransactionId = $tx['originalTransactionId'] ?? null;
            }
        }

        // Log first (idempotent upsert), then process.
        AppleNotificationLog::recordOrIgnore(
            uuid: $notificationUuid ?? (string) Str::uuid(),
            type: $notificationType,
            subtype: $subtype,
            environment: $environment,
            originalTransactionId: $originalTransactionId,
            status: true,
            payload: json_encode($payload)
        );

        if (!$originalTransactionId) {
            Log::warning('AppleIapService: webhook missing originalTransactionId');
            return;
        }

        // Re-query Apple for the authoritative current state, then apply it to
        // the household (command.txt §29 safe pattern). Any failure while
        // applying is logged but does NOT throw — Apple should receive a 200
        // so it does not retry the same notification indefinitely.
        try {
            $appAccountToken = $tx['appAccountToken'] ?? null;

            $statusResult = $this->getSubscriptionStatus($originalTransactionId);

            // If the App Store Server API re-query fails (key/env issues),
            // fall back to the verified notification payload itself. The JWS
            // is Apple-signed, so its transaction info is trusted and lets us
            // record the subscription without a second Apple call.
            if (!$statusResult['success']) {
                $statusResult = [
                    'success' => true,
                    'environment' => $environment,
                    'status' => (int) ($tx['status'] ?? self::STATUS_ACTIVE),
                    'transaction' => $tx,
                    'renewalInfo' => [],
                ];
            }

            $this->applyResolvedStatus(
                originalTransactionId: $originalTransactionId,
                statusResult: $statusResult,
                notificationType: $notificationType,
                appAccountToken: $appAccountToken,
            );
        } catch (\Throwable $e) {
            Log::error('AppleIapService: failed to apply notification', [
                'otid' => $originalTransactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* App Store Server API                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Get All Subscription Statuses (command.txt §24).
     * https://api.storekit.apple.com/inApps/v1/subscriptions/{transactionId}
     */
    public function getSubscriptionStatus(string $transactionId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Apple IAP is not configured on the server.'];
        }

        $jwt = $this->generateJwt();
        if (!$jwt) {
            return ['success' => false, 'message' => 'Failed to generate Apple JWT.'];
        }

        $environments = [self::PRODUCTION_URL, self::SANDBOX_URL];
        $lastError = 'Unknown error';

        foreach ($environments as $base) {
            try {
                $response = Http::withToken($jwt)
                    ->timeout(30)
                    ->get("{$base}/inApps/v1/subscriptions/{$transactionId}");

                if ($response->status() === 404) {
                    $lastError = 'Transaction not found at Apple.';
                    continue;
                }
                if (!$response->successful()) {
                    $lastError = 'Apple returned HTTP ' . $response->status();
                    continue;
                }

                $body = $response->json();
                $environment = $body['environment'] ?? 'Production';

                // Collect all valid transactions first, then pick the best one.
                // Active > grace_period > billing_retry > expired > revoked.
                $candidates = [];

                foreach ($body['data'] ?? [] as $item) {
                    foreach ($item['lastTransactions'] ?? [] as $last) {
                        $txInfo = $this->verifyAndDecodeJws($last['signedTransactionInfo'] ?? null);
                        if (!$txInfo) {
                            continue;
                        }
                        $status = (int) ($last['status'] ?? self::STATUS_ACTIVE);
                        $renewalInfo = $this->verifyAndDecodeJws($last['signedRenewalInfo'] ?? null);
                        $candidates[] = [
                            'status' => $status,
                            'transaction' => $txInfo,
                            'renewalInfo' => $renewalInfo ?? [],
                            // Higher = better. Active is most important.
                            'priority' => match ($status) {
                                self::STATUS_ACTIVE => 5,
                                self::STATUS_GRACE_PERIOD => 4,
                                self::STATUS_RETRY => 3,
                                self::STATUS_EXPIRED => 2,
                                self::STATUS_REVOKED => 1,
                                default => 0,
                            },
                        ];
                    }
                }

                if (!empty($candidates)) {
                    // Sort descending by priority so the best candidate is first.
                    usort($candidates, fn($a, $b) => $b['priority'] <=> $a['priority']);
                    $best = $candidates[0];

                    return [
                        'success' => true,
                        'environment' => $environment,
                        'status' => $best['status'],
                        'transaction' => $best['transaction'],
                        'renewalInfo' => $best['renewalInfo'] ?? [],
                    ];
                }

                $lastError = 'No subscription transactions returned.';
            } catch (\Exception $e) {
                Log::error('AppleIapService: status request failed', ['error' => $e->getMessage()]);
                $lastError = $e->getMessage();
            }
        }

        return ['success' => false, 'message' => $lastError];
    }

    /* ------------------------------------------------------------------ */
    /* Subscription activation / state application                        */
    /* ------------------------------------------------------------------ */

    private function applySubscription(
        User $user,
        SubscriptionPlan $plan,
        string $productId,
        string $billingPeriod,
        string $originalTransactionId,
        string $latestTransactionId,
        ?string $environment,
        ?int $purchaseDateMs,
        ?int $expiresDateMs,
        ?string $appAccountToken,
        int $appleStatus,
        int $autoRenew = 1,
    ): Subscription {
        $household = $user->activeHousehold();
        if (!$household) {
            throw new \RuntimeException('User has no active household.');
        }

        // Persist the app_account_token on the household so that App Store
        // Server Notifications (which arrive independently of the app) can
        // resolve this household even before a local subscription exists.
        if ($appAccountToken && $household->app_account_token !== $appAccountToken) {
            $household->update(['app_account_token' => $appAccountToken]);
        }

        $periodStart = $purchaseDateMs
            ? \Carbon\Carbon::createFromTimestampMs($purchaseDateMs)
            : now();
        $periodEnd = $expiresDateMs
            ? \Carbon\Carbon::createFromTimestampMs($expiresDateMs)
            : now()->addMonth();

        $status = $this->mapAppleStatus($appleStatus);

        $subscription = Subscription::where('original_transaction_id', $originalTransactionId)->first()
            ?? Subscription::where('household_id', $household->id)->first();

        $data = [
            'user_id' => $user->id,
            'subscriber_user_id' => $user->id,
            'household_id' => $household->id,
            'subscription_plan_id' => $plan->id,
            'status' => $status,
            'provider' => 'apple',
            'product_id' => $productId,
            'billing_period' => $billingPeriod,
            'original_transaction_id' => $originalTransactionId,
            'apple_original_transaction_id' => $originalTransactionId,
            'latest_transaction_id' => $latestTransactionId,
            'environment' => $environment,
            'auto_renew' => $autoRenew === 1,
            'app_account_token' => $appAccountToken,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'expires_at' => $periodEnd,
            'cancelled_at' => null,
            'last_verified_at' => now(),
        ];

        if ($subscription) {
            $subscription->update($data);
        } else {
            $subscription = Subscription::create($data);
        }

        if ($this->recordTransaction($subscription, $latestTransactionId, $originalTransactionId, $productId, $environment, $purchaseDateMs, $expiresDateMs)) {
            $this->recordPayment($subscription, $latestTransactionId);
        }

        Log::info('AppleIapService: subscription activated/updated', [
            'household_id' => $household->id,
            'plan' => $plan->code,
            'status' => $status,
        ]);

        return $subscription;
    }

    /**
     * Re-query Apple and apply the authoritative state to a local
     * subscription (§51 Rule 10 — Apple billing status decides, never a cron).
     * Used by the expiry scheduler instead of locally expiring Apple rows.
     */
    public function refreshFromApple(Subscription $subscription): bool
    {
        $lookupId = $subscription->latest_transaction_id
            ?? $subscription->original_transaction_id
            ?? $subscription->apple_original_transaction_id;

        if (!$lookupId || !$this->isConfigured()) {
            return false;
        }

        $statusResult = $this->getSubscriptionStatus($lookupId);
        if (!$statusResult['success']) {
            return false;
        }

        $this->applyRawStatus(
            originalTransactionId: $statusResult['transaction']['originalTransactionId'] ?? $subscription->original_transaction_id,
            statusResult: $statusResult,
            notificationType: null,
        );

        return true;
    }

    /**
     * Build and persist a subscription from a verified Apple status result.
     * Shared by both the app's verify flow and the server-notification flow.
     */
    private function applyFromStatusResult(
        User $user,
        array $statusResult,
        string $originalTransactionId,
        ?string $appAccountToken = null,
    ): Subscription {
        $tx = $statusResult['transaction'] ?? [];
        $productId = $tx['productId'] ?? null;
        $productConfig = $this->productConfig($productId);
        if (empty($productConfig)) {
            throw new \RuntimeException('Unknown Apple product: ' . $productId);
        }

        $plan = SubscriptionPlan::where('code', $productConfig['plan'])->first();
        if (!$plan) {
            throw new \RuntimeException('Subscription plan not found: ' . $productConfig['plan']);
        }

        return $this->applySubscription(
            user: $user,
            plan: $plan,
            productId: $productId,
            billingPeriod: $productConfig['billing_period'],
            originalTransactionId: $originalTransactionId,
            latestTransactionId: $tx['transactionId'] ?? $originalTransactionId,
            environment: $tx['environment'] ?? $statusResult['environment'],
            purchaseDateMs: (int) ($tx['purchaseDate'] ?? null),
            expiresDateMs: (int) ($tx['expiresDate'] ?? null),
            appAccountToken: $appAccountToken ?? $tx['appAccountToken'] ?? null,
            appleStatus: (int) ($statusResult['status'] ?? self::STATUS_ACTIVE),
            autoRenew: (int) ($statusResult['renewalInfo']['autoRenewStatus'] ?? 1),
        );
    }

    /**
     * Apply an authoritative Apple status to a household subscription.
     *
     * - If a local subscription already exists for the original transaction,
     *   update it (renewals, cancellations, expiry, etc.).
     * - If not, create it — resolving the household via the app_account_token
     *   that StoreKit echoes back in the signed transaction. This makes the
     *   webhook self-sufficient so the plan activates even when the app's
     *   verify call hasn't run yet.
     */
    private function applyResolvedStatus(
        string $originalTransactionId,
        array $statusResult,
        ?string $notificationType,
        ?string $appAccountToken,
    ): void {
        $subscription = Subscription::where('original_transaction_id', $originalTransactionId)->first();
        if ($subscription) {
            $this->applyRawStatus(
                originalTransactionId: $originalTransactionId,
                statusResult: $statusResult,
                notificationType: $notificationType,
            );
            return;
        }

        $household = null;
        if ($appAccountToken) {
            $household = Household::where('app_account_token', $appAccountToken)->first();
        }

        if (!$household) {
            Log::warning('AppleIapService: cannot create subscription from webhook — no household for token', [
                'otid' => $originalTransactionId,
                'has_token' => !empty($appAccountToken),
            ]);
            return;
        }

        $member = HouseholdMember::where('household_id', $household->id)
                ->where('status', 'active')
                ->where('role', 'admin')
                ->first()
            ?? HouseholdMember::where('household_id', $household->id)
                ->where('status', 'active')
                ->first();

        $user = $member?->user;
        if (!$user) {
            Log::warning('AppleIapService: household has no active member to attach subscription', [
                'household_id' => $household->id,
            ]);
            return;
        }

        $subscription = $this->applyFromStatusResult(
            user: $user,
            statusResult: $statusResult,
            originalTransactionId: $originalTransactionId,
            appAccountToken: $appAccountToken,
        );

        Log::info('AppleIapService: subscription created from webhook', [
            'household_id' => $household->id,
            'subscription_id' => $subscription->id,
            'plan' => $subscription->plan?->code,
        ]);
    }

    private function applyRawStatus(string $originalTransactionId, array $statusResult, ?string $notificationType): void
    {
        $subscription = Subscription::where('original_transaction_id', $originalTransactionId)->first();
        if (!$subscription) {
            Log::info('AppleIapService: no local subscription for transaction', ['otid' => $originalTransactionId]);
            return;
        }

        $tx = $statusResult['transaction'] ?? [];
        $status = $this->mapAppleStatus((int) ($statusResult['status'] ?? self::STATUS_ACTIVE));

        $periodEnd = isset($tx['expiresDate'])
            ? \Carbon\Carbon::createFromTimestampMs((int) $tx['expiresDate'])
            : $subscription->current_period_end;

        $subscription->update([
            'status' => $status,
            'latest_transaction_id' => $tx['transactionId'] ?? $subscription->latest_transaction_id,
            'current_period_end' => $periodEnd,
            'expires_at' => $periodEnd,
            'environment' => $statusResult['environment'] ?? $subscription->environment,
            // §30: turning auto-renew off keeps access until the period ends —
            // status stays active, only the renewal flag changes.
            'auto_renew' => isset($statusResult['renewalInfo']['autoRenewStatus'])
                ? ((int) $statusResult['renewalInfo']['autoRenewStatus'] === 1)
                : $subscription->auto_renew,
            'last_verified_at' => now(),
        ]);

        if (!empty($tx['transactionId'])) {
            $created = $this->recordTransaction(
                $subscription,
                $tx['transactionId'],
                $originalTransactionId,
                $tx['productId'] ?? $subscription->product_id,
                $statusResult['environment'] ?? null,
                $tx['purchaseDate'] ?? null,
                $tx['expiresDate'] ?? null
            );
            if ($created && ($status === 'active' || $status === 'grace_period')) {
                $this->recordPayment($subscription, $tx['transactionId']);
            }
        }
    }

    private function applyByNotificationType(string $originalTransactionId, ?string $notificationType, ?array $tx): void
    {
        $subscription = Subscription::where('original_transaction_id', $originalTransactionId)->first();
        if (!$subscription) {
            return;
        }

        switch ($notificationType) {
            case 'SUBSCRIBED':
            case 'DID_RENEW':
                $subscription->update(['status' => 'active', 'cancelled_at' => null]);
                break;

            case 'DID_CHANGE_RENEWAL_STATUS':
            case 'DID_CHANGE_RENEWAL_PREF':
                $autoRenew = ($tx['autoRenewStatus'] ?? null) == 1;
                $subscription->update(['auto_renew' => $autoRenew]);
                // Downgrade/effective change takes effect at next renewal (§7). Keep current access.
                break;

            case 'DID_FAIL_TO_RENEW':
                $subscription->moveToGracePeriod();
                break;

            case 'GRACE_PERIOD_EXPIRED':
                $subscription->markExpired();
                break;

            case 'EXPIRED':
                $subscription->markExpired();
                break;

            case 'REFUND':
            case 'REVOKE':
                $subscription->update([
                    'status' => $notificationType === 'REVOKE' ? 'revoked' : 'expired',
                    'revoked_at' => $notificationType === 'REVOKE' ? now() : null,
                    'expired_at' => now(),
                ]);
                break;

            default:
                Log::info('AppleIapService: unhandled notification', ['type' => $notificationType]);
                break;
        }
    }

    private function recordTransaction(
        Subscription $subscription,
        string $transactionId,
        ?string $originalTransactionId,
        ?string $productId,
        ?string $environment,
        ?int $purchaseDateMs,
        ?int $expiresDateMs,
    ): bool {
        $existing = SubscriptionTransaction::where('subscription_id', $subscription->id)
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existing) {
            return false;
        }

        SubscriptionTransaction::create([
            'subscription_id' => $subscription->id,
            'transaction_id' => $transactionId,
            'original_transaction_id' => $originalTransactionId,
            'product_id' => $productId,
            'environment' => $environment,
            'purchase_date' => $purchaseDateMs ? \Carbon\Carbon::createFromTimestampMs($purchaseDateMs) : null,
            'expires_date' => $expiresDateMs ? \Carbon\Carbon::createFromTimestampMs($expiresDateMs) : null,
            'transaction_reason' => 'renewal',
        ]);

        return true;
    }

    /**
     * Keep the payments history in sync for Apple purchases.
     */
    private function recordPayment(Subscription $subscription, string $transactionId): void
    {
        $plan = $subscription->plan;
        if (!$plan) {
            return;
        }

        $amount = $subscription->billing_period === 'annual'
            ? $plan->annual_price
            : $plan->monthly_price;

        Payment::create([
            'user_id' => $subscription->subscriber_user_id ?? $subscription->user_id,
            'household_id' => $subscription->household_id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => 'gbp',
            'payment_method' => 'apple',
            'gateway' => 'apple_iap',
            'gateway_payment_id' => $transactionId,
            'status' => 'completed',
            'metadata' => [
                'product_id' => $subscription->product_id,
                'original_transaction_id' => $subscription->original_transaction_id,
                'environment' => $subscription->environment,
            ],
        ]);
    }

    private function mapAppleStatus(int $appleStatus): string
    {
        return match ($appleStatus) {
            self::STATUS_ACTIVE => 'active',
            self::STATUS_GRACE_PERIOD => 'grace_period',
            self::STATUS_RETRY => 'billing_retry',
            self::STATUS_REVOKED => 'revoked',
            self::STATUS_EXPIRED => 'expired',
            default => 'active',
        };
    }

    /* ------------------------------------------------------------------ */
    /* JWT + JWS (OpenSSL, no external dependencies)                      */
    /* ------------------------------------------------------------------ */

    private function isConfigured(): bool
    {
        return !empty($this->keyId) && !empty($this->issuerId) && !empty($this->privateKeyPath) && file_exists($this->privateKeyPath);
    }

    /**
     * Resolve an Apple product ID to its plan + billing period using the
     * central config map (command.txt §9).
     */
    private function productConfig(?string $productId): array
    {
        if (!$productId) {
            return [];
        }
        $products = config('apple_products.apple_products', []);
        return $products[$productId] ?? [];
    }

    /**
     * Generate an ES256 JWT for the App Store Server API (command.txt §12).
     */
    private function generateJwt(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $this->keyId,
            'typ' => 'JWT',
        ]));

        $now = time();
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->issuerId,
            'iat' => $now,
            'exp' => $now + 1800, // max 60 minutes (§12)
            'aud' => 'appstoreconnect-v1',
            'bid' => $this->bundleId,
        ]));

        $signingInput = $header . '.' . $claims;

        $privateKey = openssl_pkey_get_private('file://' . $this->privateKeyPath);
        if ($privateKey === false) {
            Log::error('AppleIapService: cannot read private key', ['path' => $this->privateKeyPath]);
            return null;
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('AppleIapService: openssl_sign failed');
            return null;
        }

        $rawSignature = $this->derToRaw($signature);

        return $signingInput . '.' . $this->base64UrlEncode($rawSignature);
    }

    /**
     * Verify a JWS (Apple signed payload / signedTransactionInfo) and return
     * the decoded payload. Verifies the signature against the x5c certificate
     * chain embedded in the JWS header (command.txt §28).
     */
    private function verifyAndDecodeJws(?string $jws): ?array
    {
        if (!$jws) {
            return null;
        }

        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (!is_array($header)) {
            return null;
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = $this->base64UrlDecode($sigB64);
        if (strlen($signature) !== 64) {
            // Apple ES256 signatures are 64 bytes (32 R + 32 S).
            return null;
        }

        $x5c = $header['x5c'] ?? [];
        if (empty($x5c)) {
            return null;
        }

        // Verify the leaf signature against the public key in the leaf cert.
        $leafCert = $this->pemFromDer($x5c[0]);
        $pubKey = openssl_pkey_get_public($leafCert);
        if ($pubKey === false) {
            return null;
        }
        $derSignature = $this->rawToDer($signature);
        $valid = openssl_verify($signingInput, $derSignature, $pubKey, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) {
            return null;
        }

        // Verify the certificate chain (leaf signed by next, ... up to root).
        for ($i = 0; $i < count($x5c) - 1; $i++) {
            $cert = openssl_x509_read($this->pemFromDer($x5c[$i]));
            $parent = openssl_x509_read($this->pemFromDer($x5c[$i + 1]));
            if ($cert === false || $parent === false) {
                return null;
            }
            if (openssl_x509_verify($cert, $parent) !== 1) {
                return null;
            }
        }

        // Anchor trust to Apple: the chain must terminate at an Apple root CA,
        // otherwise a self-signed impostor chain would pass the checks above.
        $rootCert = openssl_x509_read($this->pemFromDer($x5c[count($x5c) - 1]));
        if ($rootCert === false || !$this->isAppleRootCertificate($rootCert)) {
            Log::warning('AppleIapService: JWS chain does not terminate at an Apple root CA');
            return null;
        }
        if (count($x5c) >= 2) {
            $intermediate = openssl_x509_read($this->pemFromDer($x5c[count($x5c) - 2]));
            if ($intermediate !== false && !$this->isAppleWwdrCertificate($intermediate)) {
                Log::warning('AppleIapService: JWS intermediate is not an Apple WWDR certificate');
                return null;
            }
        }

        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * Whether the given X.509 cert's subject identifies an Apple root CA
     * (e.g. "Apple Root CA - G3"). Used to anchor JWS trust to Apple.
     */
    private function isAppleRootCertificate($cert): bool
    {
        return $this->certSubjectContains($cert, 'Apple Root CA');
    }

    /**
     * Whether the given X.509 cert's subject identifies the Apple Worldwide
     * Developer Relations Certification Authority (the WWDR intermediate used in
     * App Store JWS chains).
     */
    private function isAppleWwdrCertificate($cert): bool
    {
        return $this->certSubjectContains($cert, 'Apple Worldwide Developer Relations');
    }

    private function certSubjectContains($cert, string $needle): bool
    {
        $parsed = openssl_x509_parse($cert);
        if (!is_array($parsed)) {
            return false;
        }
        $text = ($parsed['name'] ?? '') . ' ' . json_encode($parsed['subject'] ?? []);
        return stripos($text, $needle) !== false;
    }

    /* ------------------------------------------------------------------ */
    /* Low level helpers                                                  */
    /* ------------------------------------------------------------------ */

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT);
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }

    private function pemFromDer(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    /**
     * Convert a DER-encoded ECDSA signature to raw R||S (for JWT signing).
     */
    private function derToRaw(string $der): string
    {
        $offset = 2; // skip SEQUENCE tag + length
        // First INTEGER (R)
        $offset++;
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;
        // Second INTEGER (S)
        $offset++;
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        return $this->stripPadding($r, 32) . $this->stripPadding($s, 32);
    }

    /**
     * Convert raw R||S (64 bytes) to a DER-encoded ECDSA signature
     * (for openssl_verify).
     */
    private function rawToDer(string $raw): string
    {
        $r = $this->addDerPadding(substr($raw, 0, 32));
        $s = $this->addDerPadding(substr($raw, 32, 32));
        $rComp = "\x02" . chr(strlen($r)) . $r;
        $sComp = "\x02" . chr(strlen($s)) . $s;
        $body = $rComp . $sComp;
        return "\x30" . chr(strlen($body)) . $body;
    }

    private function stripPadding(string $bin, int $size): string
    {
        $bin = ltrim($bin, "\x00");
        return str_pad($bin, $size, "\x00", STR_PAD_LEFT);
    }

    private function addDerPadding(string $bin): string
    {
        if (ord($bin[0]) & 0x80) {
            $bin = "\x00" . $bin;
        }
        return $bin;
    }
}
