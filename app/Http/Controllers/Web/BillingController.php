<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesWebHousehold;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    use ResolvesWebHousehold;

    public function plans(Request $request)
    {
        // One pinned household for the whole payment flow: the plans
        // page, the Stripe session and the success page always agree.
        $household = $this->webHousehold($request);
        if (!$household) {
            return redirect()->route('household.create');
        }

        $plans = SubscriptionPlan::where('is_active', true)
            ->where('monthly_price', '>', 0)
            ->orderBy('sort_order')
            ->get();

        $subscription = $household->subscription;

        return view('web.plans', [
            'plans' => $plans,
            'household' => $household,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Create a Stripe Checkout Session and redirect the subscriber to Stripe.
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'billing_period' => 'required|in:monthly,annual',
        ]);

        $user = $request->user();
        $household = $this->webHousehold($request);
        if (!$household) {
            return redirect()->route('household.create');
        }

        // The Stripe session is built from the user's active household,
        // so it must be the same household shown on the plans page.
        // Never guess when an account holds more than one membership.
        $activeHousehold = $user->activeHousehold();
        if (!$activeHousehold || $activeHousehold->id !== $household->id) {
            Log::error('Web subscribe household mismatch', ['user_id' => $user->id, 'web_household_id' => $household->id, 'active_household_id' => $activeHousehold?->id]);

            return back()->withErrors(['plan' => 'Your household could not be confirmed. Please contact support before paying.']);
        }

        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($validated['plan_id']);

        try {
            $stripe = new StripeService();
            $result = $stripe->createCheckoutSession(
                $user,
                $plan,
                $validated['billing_period'],
                route('billing.success') . '?session_id={CHECKOUT_SESSION_ID}',
                route('billing.cancel')
            );
        } catch (\Exception $e) {
            Log::error('Web subscribe failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['plan' => 'Payment processing failed. Please try again.']);
        }

        return redirect()->away($result['url']);
    }

    /**
     * Stripe return URL. Activates the subscription server-side (the same
     * call the mobile app makes from its WebView) so the page itself can
     * confirm success. The Stripe webhook remains as backup.
     */
    public function success(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        $user = $request->user();
        $household = $this->webHousehold($request);

        $viewData = [
            'email' => $user->email,
            'householdName' => $household?->name,
            'appStoreUrl' => \App\Models\Setting::get('app_store_url', 'https://apps.apple.com/app/household-os/id000000000'),
            'playStoreUrl' => \App\Models\Setting::get('play_store_url', 'https://play.google.com/store/apps/details?id=com.mentosoftware.householdos'),
        ];

        try {
            (new StripeService())->activateFromConfirm($user, $request->session_id);
        } catch (\Exception $e) {
            Log::warning('Web billing success activation deferred', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return view('web.success', $viewData + ['pending' => true]);
        }

        return view('web.success', $viewData + ['pending' => false]);
    }

    public function cancel()
    {
        return view('web.cancel');
    }
}
