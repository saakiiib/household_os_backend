<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\PayPalService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PayPalService $paypal,
    ) {}

    private ?StripeService $stripe = null;

    private function getStripe(): ?StripeService
    {
        if ($this->stripe === null) {
            try {
                $this->stripe = new StripeService();
            } catch (\Throwable $e) {
                \Log::warning('StripeService unavailable: ' . $e->getMessage());
                $this->stripe = false;
            }
        }
        return $this->stripe ?: null;
    }

    /**
     * Create a checkout session (Stripe) or order (PayPal).
     * Subscription is per-household.
     */
    public function checkout(Request $request): JsonResponse
    {
        \Log::info('PaymentController@checkout called', [
            'payment_method' => $request->input('payment_method'),
            'payment_type' => $request->input('payment_type'),
            'plan_id' => $request->input('plan_id'),
            'user_id' => $request->user()?->id,
        ]);

        $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'payment_method' => 'required|in:stripe,paypal',
            'payment_type' => 'required|in:monthly,annual',
        ]);

        $user = $request->user();
        $household = $user->activeHousehold();

        if (!$household) {
            \Log::warning('PaymentController@checkout: user has no active household', ['user_id' => $user->id]);
            return response()->json([
                'success' => false,
                'message' => 'You must be a member of a household to subscribe.',
            ], 400);
        }

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        try {
            if ($request->payment_method === 'stripe') {
                $stripe = $this->getStripe();
                if (!$stripe) {
                    \Log::error('PaymentController@checkout: Stripe is not available');
                    return response()->json([
                        'success' => false,
                        'message' => 'Stripe payment is currently unavailable.',
                    ], 503);
                }
                $result = $stripe->createCheckoutSession($user, $plan, $request->payment_type);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'provider' => 'stripe',
                        'session_id' => $result['session_id'],
                        'url' => $result['url'],
                    ],
                ]);
            }

            if ($request->payment_method === 'paypal') {
                \Log::info('PaymentController@checkout: creating PayPal order', [
                    'plan' => $plan->name,
                    'amount' => $request->payment_type === 'annual' ? $plan->annual_price : $plan->monthly_price,
                ]);
                $result = $this->paypal->createOrder($user, $plan, $request->payment_type);
                \Log::info('PaymentController@checkout: PayPal order created', [
                    'order_id' => $result['order_id'],
                ]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'provider' => 'paypal',
                        'order_id' => $result['order_id'],
                        'approve_url' => $result['approve_url'],
                    ],
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('PaymentController@checkout: exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid payment method',
        ], 400);
    }

    /**
     * Capture PayPal order after user approval.
     */
    public function paypalCapture(Request $request): JsonResponse
    {
        \Log::info('PaymentController@paypalCapture called', [
            'order_id' => $request->input('order_id'),
            'user_id' => $request->user()?->id,
        ]);

        $request->validate([
            'order_id' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) {
            \Log::warning('PaymentController@paypalCapture: user not authenticated');
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated. Please log in and try again.',
            ]);
        }

        try {
            $captureData = $this->paypal->captureOrder($request->order_id);
            $this->paypal->activateFromCapture($user, $request->order_id, $captureData);

            \Log::info('PaymentController@paypalCapture: success', ['order_id' => $request->order_id]);

            return response()->json([
                'success' => true,
                'message' => 'Payment captured and subscription activated.',
            ]);
        } catch (\Exception $e) {
            \Log::error('PaymentController@paypalCapture: exception', [
                'order_id' => $request->input('order_id'),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Confirm Stripe checkout session after user returns from payment.
     */
    public function stripeConfirm(Request $request): JsonResponse
    {
        \Log::info('PaymentController@stripeConfirm called', [
            'session_id' => $request->input('session_id'),
            'user_id' => $request->user()?->id,
        ]);

        $request->validate([
            'session_id' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) {
            \Log::warning('PaymentController@stripeConfirm: user not authenticated');
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated. Please log in and try again.',
            ]);
        }

        $stripe = $this->getStripe();
        if (!$stripe) {
            \Log::error('PaymentController@stripeConfirm: Stripe is not available');
            return response()->json([
                'success' => false,
                'message' => 'Stripe is not available.',
            ]);
        }

        try {
            $stripe->activateFromConfirm($request->user(), $request->session_id);

            \Log::info('PaymentController@stripeConfirm: success', ['session_id' => $request->session_id]);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed and subscription activated.',
            ]);
        } catch (\Exception $e) {
            \Log::error('PaymentController@stripeConfirm: exception', [
                'session_id' => $request->input('session_id'),
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Stripe webhook handler (public, no auth).
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (!$sigHeader) {
            return response()->json(['error' => 'Missing signature'], 400);
        }

        if (empty(config('services.stripe.webhook_secret'))) {
            \Log::error('PaymentController@stripeWebhook: STRIPE_WEBHOOK_SECRET not configured');
            return response()->json(['error' => 'Webhook secret not configured'], 503);
        }

        try {
            $stripe = $this->getStripe();
            if (!$stripe) {
                return response()->json(['error' => 'Stripe not available'], 503);
            }
            $stripe->handleWebhook($payload, $sigHeader);
            return response()->json(['received' => true]);
        } catch (\Exception $e) {
            \Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook error'], 400);
        }
    }

    /**
     * PayPal webhook handler (public, no auth).
     */
    public function paypalWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        try {
            $this->paypal->handleWebhook($payload);
            return response()->json(['received' => true]);
        } catch (\Exception $e) {
            \Log::error('PayPal webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook error'], 400);
        }
    }
}
