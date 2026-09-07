<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\Payment;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IapController extends Controller
{
    /**
     * IAP Management Dashboard — overview of Apple/Google IAP activity.
     */
    public function index()
    {
        // Recent IAP transactions
        $recentTransactions = SubscriptionTransaction::with('subscription.household.user')
            ->where('environment', 'apple_app_store')
            ->orWhere('environment', 'google_play')
            ->latest()
            ->limit(20)
            ->get();

        // Failed IAP payments (no successful subscription activation)
        $failedPayments = Payment::where('gateway', 'apple_iap')
            ->orWhere('gateway', 'google_play')
            ->where('status', 'failed')
            ->latest()
            ->limit(20)
            ->get();

        // Active IAP subscriptions
        $activeIapSubscriptions = Subscription::where('payment_method', 'apple_iap')
            ->orWhere('payment_method', 'google_play')
            ->where('status', 'active')
            ->with('household.user', 'plan')
            ->get();

        // Count by provider
        $appleCount = Subscription::where('payment_method', 'apple_iap')->count();
        $googleCount = Subscription::where('payment_method', 'google_play')->count();

        return view('admin.pages.iap', compact(
            'recentTransactions',
            'failedPayments',
            'activeIapSubscriptions',
            'appleCount',
            'googleCount'
        ));
    }

    /**
     * Show Apple IAP transaction details.
     */
    public function appleShow(SubscriptionTransaction $transaction)
    {
        $transaction->load('subscription.household.user', 'subscription.plan', 'payment');

        return view('admin.pages.iap-apple-show', compact('transaction'));
    }

    /**
     * Show Google IAP transaction details.
     */
    public function googleShow(SubscriptionTransaction $transaction)
    {
        $transaction->load('subscription.household.user', 'subscription.plan', 'payment');

        return view('admin.pages.iap-google-show', compact('transaction'));
    }

    /**
     * Re-verify a specific Apple IAP transaction.
     */
    public function reverifyApple(Request $request, SubscriptionTransaction $transaction)
    {
        $request->validate([
            'original_transaction_id' => 'required|string',
        ]);

        try {
            $service = app(\App\Services\AppleIapService::class);
            $user = $transaction->subscription->user;
            $result = $service->verifyAndActivate(
                $user,
                $request->original_transaction_id,
                $transaction->subscription->household->app_account_token,
                null
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin reverifyApple failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Re-verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Re-verify a specific Google IAP transaction.
     */
    public function reverifyGoogle(Request $request, SubscriptionTransaction $transaction)
    {
        $request->validate([
            'receipt_data' => 'required|string',
            'product_id' => 'required|string',
        ]);

        try {
            $service = app(\App\Services\GooglePlayIapService::class);
            $result = $service->verifyReceipt(
                receiptData: $request->receipt_data,
                googleProductId: $request->product_id,
                planSlug: $transaction->subscription->plan->slug,
                billingType: $transaction->subscription->billing_period,
                transactionId: $transaction->original_transaction_id,
                isRestored: true,
                user: $transaction->subscription->user,
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Admin reverifyGoogle failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Re-verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}