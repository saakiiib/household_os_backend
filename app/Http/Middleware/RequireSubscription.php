<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Per-feature limits are enforced by EntitlementService in each
        // controller. This middleware only blocks revoked subscriptions
        // (e.g. refund / abuse). Expired subscriptions fall back to free
        // plan limits and are NOT blocked here.
        $household = $user->activeHousehold();
        if ($household && $household->subscription && $household->subscription->status === 'revoked') {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has been revoked. Please contact support.',
                'code' => 'SUBSCRIPTION_REVOKED',
            ], 403);
        }

        return $next($request);
    }
}
