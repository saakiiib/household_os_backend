<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Checkout\Session as CheckoutSession;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create or retrieve Stripe customer for user.
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        $subscription = $user->subscription;

        if ($subscription && $subscription->stripe_customer_id) {
            return Customer::retrieve($subscription->stripe_customer_id);
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        return $customer;
    }

    /**
     * Create a Stripe Checkout Session for subscription.
     */
    public function createCheckoutSession(
        User $user,
        SubscriptionPlan $plan,
        string $paymentType // 'monthly' or 'annual'
    ): array {
        $customer = $this->getOrCreateCustomer($user);

        // Get user's active household
        $household = $user->activeHousehold();
        if (!$household) {
            throw new \Exception('User must be a member of a household to subscribe.');
        }

        $priceAmount = $paymentType === 'annual'
            ? (int) ($plan->annual_price * 100)
            : (int) ($plan->monthly_price * 100);

        $interval = $paymentType === 'annual' ? 'year' : 'month';

        $session = CheckoutSession::create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'gbp',
                        'product_data' => [
                            'name' => $plan->name . ' (' . ucfirst($paymentType) . ')',
                            'description' => $plan->description,
                        ],
                        'unit_amount' => $priceAmount,
                        'recurring' => [
                            'interval' => $interval,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'subscription',
            'success_url' => config('app.frontend_url', 'http://192.168.0.103:8000') . '/api/subscription/stripe/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url', 'http://192.168.0.103:8000') . '/api/subscription/stripe/cancel',
            'metadata' => [
                'user_id' => $user->id,
                'household_id' => $household->id,
                'plan_id' => $plan->id,
                'payment_type' => $paymentType,
            ],
        ]);

        // Store pending payment record
        $householdSubscription = $household->subscription;

        $paymentData = [
            'household_id' => $household->id,
            'subscription_plan_id' => $plan->id,
            'amount' => $priceAmount / 100,
            'currency' => 'gbp',
            'payment_method' => 'stripe',
            'gateway' => 'stripe',
            'gateway_payment_id' => $session->id,
            'status' => 'pending',
            'metadata' => [
                'payment_type' => $paymentType,
                'plan_name' => $plan->name,
                'stripe_customer_id' => $customer->id,
            ],
        ];

        if ($householdSubscription) {
            $paymentData['subscription_id'] = $householdSubscription->id;
        }

        \Log::info('Stripe createCheckoutSession: storing pending payment', ['session_id' => $session->id]);
        $user->payments()->create($paymentData);

        return [
            'session_id' => $session->id,
            'url' => $session->url,
        ];
    }

    /**
     * Retrieve a Stripe Checkout Session and verify payment.
     */
    public function confirmCheckoutSession(string $sessionId): array
    {
        \Log::info('Stripe confirmCheckoutSession: retrieving session', ['session_id' => $sessionId]);

        $session = CheckoutSession::retrieve($sessionId);

        \Log::info('Stripe confirmCheckoutSession: session retrieved', [
            'session_id' => $sessionId,
            'status' => $session->status,
            'payment_status' => $session->payment_status,
        ]);

        return [
            'session' => $session,
            'paid' => $session->payment_status === 'paid' || $session->status === 'complete',
        ];
    }

    /**
     * Activate subscription after Stripe checkout confirmation (no webhook).
     */
    public function activateFromConfirm(User $user, string $sessionId): void
    {
        $result = $this->confirmCheckoutSession($sessionId);
        $session = $result['session'];

        if (!$result['paid']) {
            \Log::warning('Stripe confirm: payment not paid', [
                'session_id' => $sessionId,
                'status' => $session->status,
                'payment_status' => $session->payment_status,
            ]);
            throw new \Exception('Payment not yet confirmed by Stripe. Please wait a moment and try again.');
        }

        $metadata = $session->metadata;
        $householdId = $metadata->household_id ?? null;
        $planId = $metadata->plan_id ?? null;
        $paymentType = $metadata->payment_type ?? 'monthly';

        if (!$householdId || !$planId) {
            \Log::error('Stripe confirm: missing metadata', ['session_id' => $sessionId]);
            return;
        }

        // Update payment record
        $payment = $user->payments()
            ->where('gateway_payment_id', $sessionId)
            ->where('status', 'pending')
            ->first();

        // Find or create subscription
        $subscription = Subscription::where('household_id', $householdId)->first();

        $now = now();
        $periodEnd = $paymentType === 'annual' ? $now->copy()->addYear() : $now->copy()->addMonth();
        $expiresAt = $periodEnd->copy()->addDays(Subscription::GRACE_PERIOD_DAYS);

        $data = [
            'subscription_plan_id' => $planId,
            'status' => 'active',
            'payment_method' => 'stripe',
            'stripe_subscription_id' => $session->subscription ?? null,
            'stripe_customer_id' => $session->customer ?? null,
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'expires_at' => $expiresAt,
            'trial_started_at' => null,
            'trial_ends_at' => null,
            'cancelled_at' => null,
        ];

        if ($subscription) {
            $subscription->update($data);
        } else {
            $data['user_id'] = $user->id;
            $data['household_id'] = $householdId;
            $subscription = Subscription::create($data);
        }

        if ($payment) {
            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'succeeded',
            ]);
        } else {
            $this->recordPayment(
                $user, $subscription, $planId,
                $session->amount_total / 100, 'succeeded',
                $sessionId
            );
        }

        \Log::info('Stripe confirm: subscription activated', [
            'household_id' => $householdId,
            'plan_id' => $planId,
            'payment_type' => $paymentType,
        ]);
    }

    /**
     * Handle Stripe webhook event.
     *
     * @param string $payload Raw request body (exactly as Stripe signed it).
     *                       Never re-encode an array — the signature is over
     *                       the raw bytes, so a re-encoded body fails verification.
     */
    public function handleWebhook(string $payload, string $sigHeader): void
    {
        $endpointSecret = config('services.stripe.webhook_secret');

        if (empty($endpointSecret)) {
            \Log::error('Stripe webhook: STRIPE_WEBHOOK_SECRET is not configured');
            return;
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $endpointSecret
            );
        } catch (\Exception $e) {
            \Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return;
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($event->data->object);
                break;

            case 'invoice.paid':
                $this->handleInvoicePaid($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;
        }
    }

    private function handleCheckoutCompleted(CheckoutSession $session): void
    {
        $userId = $session->metadata->user_id ?? null;
        $householdId = $session->metadata->household_id ?? null;
        if (!$userId || !$householdId) return;

        $user = User::find($userId);
        if (!$user) return;

        $planId = $session->metadata->plan_id ?? null;
        $paymentType = $session->metadata->payment_type ?? 'monthly';

        // Find existing subscription for this household, or create new
        $subscription = Subscription::where('household_id', $householdId)->first();

        $now = now();
        $periodEnd = $paymentType === 'annual' ? $now->copy()->addYear() : $now->copy()->addMonth();
        $expiresAt = $periodEnd->copy()->addDays(\App\Models\Subscription::GRACE_PERIOD_DAYS);

        $data = [
            'subscription_plan_id' => $planId,
            'status' => 'active',
            'payment_method' => 'stripe',
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id' => $session->customer,
            'current_period_start' => $now,
            'current_period_end' => $periodEnd,
            'expires_at' => $expiresAt,
            'trial_started_at' => null,
            'trial_ends_at' => null,
            'cancelled_at' => null,
        ];

        if ($subscription) {
            $subscription->update($data);
        } else {
            $data['user_id'] = $user->id;
            $data['household_id'] = $householdId;
            $subscription = Subscription::create($data);
        }

        // Record payment linked to household
        $this->recordPayment($user, $subscription, $planId, $session->amount_total / 100, 'succeeded', $session->payment_intent);
    }

    private function handleInvoicePaid($invoice): void
    {
        // Recurring payment succeeded - extend subscription period
        $stripeSubId = $invoice->subscription;
        if (!$stripeSubId) return;

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$subscription) return;

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now()->timestamp($invoice->period_start),
            'current_period_end' => now()->timestamp($invoice->period_end),
        ]);

        $this->recordPayment(
            $subscription->user,
            $subscription,
            $subscription->subscription_plan_id,
            $invoice->amount_paid / 100,
            'succeeded',
            $invoice->payment_intent
        );
    }

    private function handleInvoicePaymentFailed($invoice): void
    {
        $stripeSubId = $invoice->subscription;
        if (!$stripeSubId) return;

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubId)->first();
        if (!$subscription) return;

        $subscription->update(['status' => 'past_due']);

        $this->recordPayment(
            $subscription->user,
            $subscription,
            $subscription->subscription_plan_id,
            $invoice->amount_due / 100,
            'failed',
            $invoice->payment_intent,
            'Payment failed: ' . ($invoice->last_finalization_error->message ?? 'Unknown error')
        );
    }

    private function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        if (!$subscription) return;

        $subscription->update([
            'status' => 'expired',
            'cancelled_at' => now(),
        ]);
    }

    private function recordPayment(
        User $user,
        Subscription $subscription,
        int $planId,
        float $amount,
        string $status,
        ?string $gatewayPaymentId,
        ?string $failureReason = null
    ): void {
        $user->payments()->create([
            'household_id' => $subscription->household_id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $planId,
            'amount' => $amount,
            'currency' => 'gbp',
            'payment_method' => 'stripe',
            'gateway' => 'stripe',
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => $status,
            'failure_reason' => $failureReason,
        ]);
    }
}
