<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Http\Request;

/**
 * Pins the household a web session is paying for.
 *
 * Every web billing page resolves the household through here instead of
 * re-picking it, so the plans page, the Stripe session and the success
 * page always agree on the same household.
 */
trait ResolvesWebHousehold
{
    private const WEB_HOUSEHOLD_SESSION_KEY = 'web_household_id';

    /**
     * Resolve the session household. The pinned id wins; the user's
     * active household is the fallback (and gets pinned). Null when the
     * user has no household yet.
     */
    protected function webHousehold(Request $request): ?Household
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        $pinnedId = $request->session()->get(self::WEB_HOUSEHOLD_SESSION_KEY);
        if ($pinnedId) {
            $pinned = Household::whereKey($pinnedId)->first();
            if ($pinned && $this->isActiveWebMember((int) $user->id, (int) $pinned->id)) {
                return $pinned;
            }
            $request->session()->forget(self::WEB_HOUSEHOLD_SESSION_KEY);
        }

        $active = $user->activeHousehold();
        if ($active) {
            $request->session()->put(self::WEB_HOUSEHOLD_SESSION_KEY, $active->id);
        }

        return $active;
    }

    protected function pinWebHousehold(Request $request, int $householdId): void
    {
        $request->session()->put(self::WEB_HOUSEHOLD_SESSION_KEY, $householdId);
    }

    private function isActiveWebMember(int $userId, int $householdId): bool
    {
        return HouseholdMember::where('user_id', $userId)
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->exists();
    }
}
