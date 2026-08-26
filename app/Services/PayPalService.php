<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Get PayPal access token.
     */
    private function getAccessToken(): string
    {
        Log::info('PayPal getAccessToken: requesting token', [
            'base_url' => $this->baseUrl,
            'client_id' => substr($this->clientId ?? '', 0, 10) . '...',
            'client_secret_length' => strlen($this->clientSecret ?? ''),
        ]);

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        Log::info('PayPal getAccessToken: response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
        ]);

        if ($response->failed()) {
            $body = $response->json();
            $error = $body['error'] ?? $body['message'] ?? $response->body();
            Log::error('PayPal token failed', [
                'status' => $response->status(),
                'error' => $error,
                'response_body' => $response->body(),
                'client_id_set' => !empty($this->clientId),
                'client_secret_set' => !empty($this->clientSecret),
                'base_url' => $this->baseUrl,
            ]);
            throw new \Exception('Failed to get PayPal access token: ' . $error);
        }

        $token = $response->json('access_token');
        Log::info('PayPal getAccessToken: success', ['token_length' => strlen($token ?? '')]);
        return $token;
    }

    /**
     * Create PayPal order for subscription.
     */
    public function createOrder(
        User $user,
        SubscriptionPlan $plan,
        string $paymentType // 'monthly' or 'annual'
    ): array {
        Log::info('PayPal createOrder: starting', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'payment_type' => $paymentType,
        ]);

        $accessToken = $this->getAccessToken();

        // Get user's active household
        $household = $user->activeHousehold();
        if (!$household) {
            Log::error('PayPal createOrder: user has no active household', ['user_id' => $user->id]);
            throw new \Exception('User must be a member of a household to subscribe.');
        }

        $amount = $paymentType === 'annual' ? $plan->annual_price : $plan->monthly_price;
        $description = $plan->name . ' - ' . ucfirst($paymentType) . ' Subscription';

        $orderPayload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'HS-' . $household->id . '-' . time(),
                    'description' => $description,
                    'amount' => [
                        'currency_code' => 'GBP',
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => 'HouseholdOS',
                'locale' => 'en-GB',
                'landing_page' => 'BILLING',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => config('app.frontend_url', 'http://localhost:3000') . '/subscription/paypal-capture',
                'cancel_url' => config('app.frontend_url', 'http://localhost:3000') . '/subscription/cancel',
            ],
        ];

        Log::info('PayPal createOrder: sending request', [
            'amount' => $amount,
            'description' => $description,
            'household_id' => $household->id,
        ]);

        $response = Http::withToken($accessToken)
            ->post("{$this->baseUrl}/v2/checkout/orders", $orderPayload);

        Log::info('PayPal createOrder: response received', [
            'status' => $response->status(),
            'successful' => $response->successful(),
        ]);

        if ($response->failed()) {
            Log::error('PayPal create order failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to create PayPal order: ' . ($response->json('message') ?? $response->body()));
        }

        $data = $response->json();
        $orderId = $data['id'];

        // Find approval URL
        $approveUrl = null;
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approveUrl = $link['href'];
                break;
            }
        }

        // Store pending order linked to household
        $householdSubscription = $household->subscription;

        $paymentData = [
            'household_id' => $household->id,
            'subscription_plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => 'gbp',
            'payment_method' => 'paypal',
            'gateway' => 'paypal',
            'gateway_payment_id' => $orderId,
            'status' => 'pending',
            'metadata' => [
                'payment_type' => $paymentType,
                'plan_name' => $plan->name,
            ],
        ];

        if ($householdSubscription) {
            $paymentData['subscription_id'] = $householdSubscription->id;
        }

        Log::info('PayPal createOrder: storing payment', ['payment_data' => array_keys($paymentData)]);

        $user->payments()->create($paymentData);

        return [
            'order_id' => $orderId,
            'approve_url' => $approveUrl,
        ];
    }

    /**
     * Capture a PayPal order after user approval.
     */
    public function captureOrder(string $orderId): array
    {
        Log::info('PayPal captureOrder: starting', ['order_id' => $orderId]);

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->send('POST', "{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

        Log::info('PayPal captureOrder: response', [
            'order_id' => $orderId,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            Log::error('PayPal capture order failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->json(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to capture PayPal order: ' . ($response->json('message') ?? $response->body()));
        }

        $data = $response->json();
        Log::info('PayPal captureOrder: success', [
            'order_id' => $orderId,
            'capture_status' => $data['status'] ?? 'unknown',
        ]);

        return $data;
    }

    /**
     * Activate subscription after successful PayPal capture.
     */
    public function activateFromCapture(User $user, string $orderId, array $captureData): void
    {
        Log::info('PayPal activateFromCapture: starting', [
            'order_id' => $orderId,
            'user_id' => $user->id,
            'capture_status' => $captureData['status'] ?? 'unknown',
        ]);

        // PayPal capture must be COMPLETED (or the order APPROVED) before we
        // entitle the household. Activating on any other status would grant
        // premium for unpaid orders (overview.md bug #5).
        $orderStatus = $captureData['status'] ?? null;
        $captureStatus = null;
        foreach (($captureData['purchase_units'] ?? []) as $pu) {
            foreach (($pu['payments']['captures'] ?? []) as $cap) {
                $captureStatus = $cap['status'] ?? null;
            }
        }
        $accepted = in_array($orderStatus, ['COMPLETED', 'APPROVED'], true)
            || $captureStatus === 'COMPLETED';
        if (!$accepted) {
            Log::warning('PayPal activateFromCapture: capture not completed', [
                'order_id' => $orderId,
                'order_status' => $orderStatus,
                'capture_status' => $captureStatus,
            ]);
            throw new \Exception('PayPal payment has not completed (status: ' . ($orderStatus ?? 'unknown') . ').');
        }

        $payment = $user->payments()
            ->where('gateway_payment_id', $orderId)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            Log::warning('PayPal payment not found for order', ['order_id' => $orderId]);
            throw new \Exception('Payment record not found for this order.');
        }

        $paymentType = $payment->metadata['payment_type'] ?? 'monthly';
        $plan = SubscriptionPlan::find($payment->subscription_plan_id);
        if (!$plan) {
            Log::error('PayPal subscription plan not found', ['plan_id' => $payment->subscription_plan_id]);
            throw new \Exception('Subscription plan not found.');
        }
        $householdId = $payment->household_id;

        // Find or create subscription for this household
        $subscription = Subscription::where('household_id', $householdId)->first();

        $now = now();
        $periodEnd = $paymentType === 'annual' ? $now->copy()->addYear() : $now->copy()->addMonth();
        $expiresAt = $periodEnd->copy()->addDays(Subscription::GRACE_PERIOD_DAYS);

        $subData = [
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'payment_method' => 'paypal',
            'paypal_subscription_id' => $orderId,
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'expires_at' => $expiresAt,
            'trial_started_at' => null,
            'trial_ends_at' => null,
            'cancelled_at' => null,
        ];

        if ($subscription) {
            $subscription->update($subData);
        } else {
            $subData['user_id'] = $user->id;
            $subData['household_id'] = $householdId;
            $subscription = Subscription::create($subData);
        }

        // Update payment record
        $payment->update([
            'subscription_id' => $subscription->id,
            'status' => 'succeeded',
        ]);

        Log::info('PayPal activateFromCapture: success', [
            'order_id' => $orderId,
            'subscription_id' => $subscription->id,
        ]);
    }

    /**
     * Handle PayPal webhook event.
     */
    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['event_type'] ?? '';

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handlePaymentCaptureCompleted($payload['resource'] ?? []);
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->handlePaymentCaptureFailed($payload['resource'] ?? []);
                break;
        }
    }

    private function handlePaymentCaptureCompleted(array $resource): void
    {
        $customId = $resource['custom_id'] ?? null;
        // PayPal webhooks may not have all the context we need
        // Primary activation happens via captureOrder() redirect flow
        Log::info('PayPal payment captured', ['resource_id' => $resource['id'] ?? 'unknown']);
    }

    private function handlePaymentCaptureFailed(array $resource): void
    {
        Log::warning('PayPal payment failed/refunded', ['resource' => $resource]);
    }
}
