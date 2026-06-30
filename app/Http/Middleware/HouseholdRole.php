<?php

namespace App\Http\Middleware;

use App\Models\HouseholdMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HouseholdRole
{
    /**
     * Handle an incoming request.
     *
     * Checks that the authenticated user is an active member of the given
     * household and, if $requiredRoles is provided, that their role matches
     * one of those roles.
     *
     * Usage in routes:
     *   ->middleware('household.role')          // any active member
     *   ->middleware('household.role:admin')     // admin only
     *   ->middleware('household.role:admin,co-admin')  // admin or co-admin
     *
     * The household_id is expected as a route parameter named {household_id}.
     *
     * @param Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next, string ...$requiredRoles): Response
    {
        $householdId = $request->route('household_id');

        if (!$householdId) {
            return response()->json(['success' => false, 'message' => 'Household not specified.'], 400);
        }

        $membership = HouseholdMember::where('household_id', $householdId)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active member of this household.',
            ], 403);
        }

        // If specific roles are required, check them
        if (!empty($requiredRoles) && !in_array($membership->role, $requiredRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have the required role to perform this action.',
            ], 403);
        }

        // Attach membership to request for use in controllers
        $request->merge(['_household_member' => $membership]);

        return $next($request);
    }
}
