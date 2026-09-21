<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private ?StripeService $stripe = null;

    private function getStripe(): ?StripeService
    {
        if ($this->stripe === null) {
            try {
                $this->stripe = new StripeService();
            } catch (\Throwable $e) {
                \Log::warning('StripeService unavailable', ['error' => $e->getMessage()]);
                $this->stripe = false;
            }
        }
        return $this->stripe ?: null;
    }

    /**
     * Create a Stripe checkout session.
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
            'payment_method' => 'required|in:stripe',
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
        } catch (\Exception $e) {
            \Log::error('PaymentController@checkout: exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Confirm Stripe checkout session after user returns from payment.
     */
    public function stripeConfirm(Request $request): JsonResponse
    {
        \Log::info('PaymentController@stripeConfirm called', [
            'session_suffix' => substr((string) $request->input('session_id'), -6),
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

            \Log::info('PaymentController@stripeConfirm: success', ['session_suffix' => substr((string) $request->session_id, -6)]);

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed and subscription activated.',
            ]);
        } catch (\Exception $e) {
            \Log::error('PaymentController@stripeConfirm: exception', [
                'session_suffix' => substr((string) $request->input('session_id'), -6),
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed. Please try again.',
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
            \Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook error'], 400);
        }
    }
}
