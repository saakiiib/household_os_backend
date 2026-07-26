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

        if (SubscriptionGuard::isExpired($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired. Please subscribe to access this feature.',
                'code' => 'SUBSCRIPTION_EXPIRED',
            ], 403);
        }

        return $next($request);
    }
}
