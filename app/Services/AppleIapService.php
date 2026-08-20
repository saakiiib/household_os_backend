<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppleIapService
{
    // Apple verifyReceipt endpoints
    private const VERIFY_URL_PRODUCTION = 'https://buy.itunes.apple.com/verifyReceipt';
    private const VERIFY_URL_SANDBOX = 'https://sandbox.itunes.apple.com/verifyReceipt';

    // Shared secret from App Store Connect > App > In-App Purchases > App-Specific Shared Secret
    private string $sharedSecret;

    public function __construct()
    {
        $this->sharedSecret = config('services.apple.shared_secret', '');
    }

    /**
     * Verify an Apple receipt and activate/extend the subscription.
     *
     * @return array{success: bool, message: string, subscription?: Subscription}
     */
    public function verifyReceipt(
        string $receiptData,
        string $appleProductId,
        string $planSlug,
        string $billingType,
        string $transactionId,
        bool $isRestored = false,
        ?User $user = null,
    ): array {
        if (empty($this->sharedSecret)) {
            Log::error('AppleIapService: shared_secret not configured');
            return ['success' => false, 'message' => 'Apple IAP is not configured on the server.'];
        }

        // Try production first, fall back to sandbox
        $result = $this->_verifyWithApple($receiptData, self::VERIFY_URL_PRODUCTION);

        // If production says 21007, try sandbox
        if (isset($result['status']) && $result['status'] == 21007) {
            $result = $this->_verifyWithApple($receiptData, self::VERIFY_URL_SANDBOX);
        }

        if (!isset($result['status']) || $result['status'] !== 0) {
            $status = $result['status'] ?? 'unknown';
            Log::error('AppleIapService: verification failed', ['status' => $status, 'result' => $result]);
            return ['success' => false, 'message' => 'Apple receipt verification failed (status: ' . $status . ').'];
        }

        // Find the latest receipt info for this product
        $latestReceiptInfo = $result['latest_receipt_info'] ?? [];
        if (empty($latestReceiptInfo)) {
            return ['success' => false, 'message' => 'No receipt information found.'];
        }

        // Find the matching receipt entry
        $receiptEntry = null;
        foreach ($latestReceiptInfo as $entry) {
            if (isset($entry['product_id']) && $entry['product_id'] === $appleProductId) {
                $receiptEntry = $entry;
                break;
            }
        }

        // If no exact match, use the latest entry
        if (!$receiptEntry && !empty($latestReceiptInfo)) {
            $receiptEntry = end($latestReceiptInfo);
        }

        if (!$receiptEntry) {
            return ['success' => false, 'message' => 'Could not find matching receipt for product.'];
        }

        // Parse expiry date
        $expiresAt = isset($receiptEntry['expires_date_ms'])
            ? \Carbon\Carbon::createFromTimestampMs((int) $receiptEntry['expires_date_ms'])
            : null;

        $purchaseDate = isset($receiptEntry['purchase_date_ms'])
            ? \Carbon\Carbon::createFromTimestampMs((int) $receiptEntry['purchase_date_ms'])
            : null;

        $originalTransactionId = $receiptEntry['original_transaction_id'] ?? $transactionId;

        // Determine billing type from product ID
        $detectedBillingType = str_contains($appleProductId, '.annual.') ? 'annual' : 'monthly';

        return [
            'success' => true,
            'message' => 'Receipt verified successfully.',
            'expires_at' => $expiresAt,
            'purchase_date' => $purchaseDate,
            'original_transaction_id' => $originalTransactionId,
            'apple_product_id' => $appleProductId,
            'billing_type' => $detectedBillingType,
        ];
    }

    /**
     * Activate or extend a subscription after successful receipt verification.
     */
    public function activateSubscription(
        User $user,
        string $planSlug,
        string $billingType,
        string $appleProductId,
        string $originalTransactionId,
        \Carbon\Carbon $expiresAt,
        ?\Carbon\Carbon $purchaseDate = null,
    ): Subscription {
        $household = $user->activeHousehold();

        if (!$household) {
            throw new \RuntimeException('User has no active household.');
        }

        $plan = SubscriptionPlan::where('slug', $planSlug)->first();
        if (!$plan) {
            throw new \RuntimeException("Subscription plan not found: {$planSlug}");
        }

        // Calculate period start and end
        $periodStart = $purchaseDate ?? now();
        $periodEnd = $expiresAt;

        // Find existing subscription for this household
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
            'payment_method' => 'apple',
            'apple_product_id' => $appleProductId,
            'apple_original_transaction_id' => $originalTransactionId,
        ];

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
            'payment_method' => 'apple',
            'gateway' => 'apple_iap',
            'gateway_payment_id' => $originalTransactionId,
            'status' => 'completed',
            'metadata' => [
                'apple_product_id' => $appleProductId,
                'original_transaction_id' => $originalTransactionId,
            ],
        ]);

        Log::info('AppleIapService: subscription activated', [
            'user_id' => $user->id,
            'household_id' => $household->id,
            'plan' => $planSlug,
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return $subscription;
    }

    /**
     * Handle Apple Server Notification (App Store Server Notifications v2).
     */
    public function handleServerNotification(array $payload): void
    {
        $notificationType = $payload['notificationType'] ?? $payload['type'] ?? null;
        $subtype = $payload['subtype'] ?? null;
        $data = $payload['data'] ?? $payload;

        // Extract transaction info
        $transactionInfo = $data['transactionInfo'] ?? $data;
        $originalTransactionId = $transactionInfo['originalTransactionId']
            ?? $transactionInfo['original_transaction_id']
            ?? null;
        $product_id = $transactionInfo['productIdentifier']
            ?? $transactionInfo['product_id']
            ?? null;

        Log::info('AppleIapService: server notification', [
            'type' => $notificationType,
            'subtype' => $subtype,
            'original_transaction_id' => $originalTransactionId,
            'product_id' => $product_id,
        ]);

        if (!$originalTransactionId) {
            Log::warning('AppleIapService: no original_transaction_id in notification');
            return;
        }

        // Find subscription by original transaction ID
        $subscription = Subscription::where('apple_original_transaction_id', $originalTransactionId)->first();

        if (!$subscription) {
            Log::warning('AppleIapService: subscription not found for transaction', [
                'original_transaction_id' => $originalTransactionId,
            ]);
            return;
        }

        switch ($notificationType) {
            case 'SUBSCRIBED':
            case 'DID_RENEW':
                // Subscription renewed or first subscribed
                $this->_handleRenewal($subscription, $transactionInfo);
                break;

            case 'EXPIRED':
                // Subscription expired
                $subscription->update(['status' => 'expired']);
                Log::info('AppleIapService: subscription expired', ['id' => $subscription->id]);
                break;

            case 'DID_FAIL_TO_RENEW':
                // Billing retry period
                $subscription->moveToGracePeriod();
                Log::info('AppleIapService: subscription billing failed, moved to grace period', ['id' => $subscription->id]);
                break;

            case 'GRACE_PERIOD_EXPIRED':
                // Grace period expired
                $subscription->markExpired();
                Log::info('AppleIapService: grace period expired', ['id' => $subscription->id]);
                break;

            case 'REVOKE':
                // Family sharing revocation
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                Log::info('AppleIapService: subscription revoked', ['id' => $subscription->id]);
                break;

            case 'DID_CHANGE_RENEWAL_STATUS':
                // Auto-renewal status changed
                $autoRenew = $transactionInfo['autoRenewStatus'] ?? null;
                if ($autoRenew !== null) {
                    $metadata = $subscription->metadata ?? [];
                    $metadata['auto_renew'] = $autoRenew == 1;
                    $subscription->update(['metadata' => $metadata]);
                }
                break;

            default:
                Log::info('AppleIapService: unhandled notification type', ['type' => $notificationType]);
                break;
        }
    }

    /**
     * Handle renewal by re-verifying the latest receipt.
     */
    private function _handleRenewal(Subscription $subscription, array $transactionInfo): void
    {
        $expiresAt = isset($transactionInfo['expiresDate'])
            ? \Carbon\Carbon::parse($transactionInfo['expiresDate'])
            : (isset($transactionInfo['expires_date_ms'])
                ? \Carbon\Carbon::createFromTimestampMs((int) $transactionInfo['expires_date_ms'])
                : null);

        if ($expiresAt) {
            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $expiresAt,
                'expires_at' => $expiresAt,
                'cancelled_at' => null,
            ]);
            Log::info('AppleIapService: subscription renewed', [
                'id' => $subscription->id,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        }
    }

    /**
     * Send receipt data to Apple for verification.
     */
    private function _verifyWithApple(string $receiptData, string $url): array
    {
        try {
            $response = Http::timeout(30)->post($url, [
                'receipt-data' => $receiptData,
                'shared-secret' => $this->sharedSecret,
                'exclude-old-transactions' => true,
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('AppleIapService: HTTP verification failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return ['status' => -1];
        }
    }
}
