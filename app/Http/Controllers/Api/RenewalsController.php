<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Renewal;
use App\Models\RenewalHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RenewalsController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Format a renewal for API responses.
     */
    private function formatRenewal(Renewal $renewal, bool $withHistory = false): array
    {
        $data = [
            'id'               => $renewal->id,
            'household_id'     => $renewal->household_id,
            'title'            => $renewal->title,
            'category'         => $renewal->category,
            'renewal_date'     => $renewal->renewal_date->toDateString(),
            'days_remaining'   => $renewal->days_remaining,
            'urgency'          => $renewal->urgency,
            'cost'             => $renewal->cost,
            'currency'         => $renewal->currency,
            'frequency'        => $renewal->frequency,
            'notes'            => $renewal->notes,
            'status'           => $renewal->status,
            'responsible_user' => $renewal->responsibleUser
                ? ['id' => $renewal->responsibleUser->id, 'name' => $renewal->responsibleUser->name]
                : null,
            'reminders' => [
                'sent_90d'  => $renewal->reminder_sent_90d,
                'sent_30d'  => $renewal->reminder_sent_30d,
                'sent_7d'   => $renewal->reminder_sent_7d,
                'sent_due'  => $renewal->reminder_sent_due,
            ],
            'created_at' => $renewal->created_at,
            'updated_at' => $renewal->updated_at,
        ];

        if ($withHistory) {
            $data['history'] = $renewal->history->map(fn($h) => [
                'id'            => $h->id,
                'renewed_by'    => $h->renewedBy ? ['id' => $h->renewedBy->id, 'name' => $h->renewedBy->name] : null,
                'previous_date' => $h->previous_date->toDateString(),
                'new_date'      => $h->new_date->toDateString(),
                'cost'          => $h->cost,
                'notes'         => $h->notes,
                'created_at'    => $h->created_at,
            ]);
        }

        return $data;
    }

    /**
     * Guard: check caller is an active member of the household.
     */
    private function getMembership(int $householdId): ?HouseholdMember
    {
        return HouseholdMember::where('household_id', $householdId)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/households/{household_id}/renewals
     * List renewals with optional filters: status, category, upcoming (days).
     * Requires: active household member.
     */
    public function index(Request $request, $household_id)
    {
        $query = Renewal::with('responsibleUser')
            ->where('household_id', $household_id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // ?upcoming=90 → renewals due within the next N days
        if ($request->has('upcoming')) {
            $days = (int) $request->upcoming;
            $query->where('renewal_date', '<=', now()->addDays($days))
                  ->where('renewal_date', '>=', now())
                  ->where('status', 'active');
        }

        $renewals = $query->orderBy('renewal_date')->get();

        return response()->json([
            'success' => true,
            'data'    => $renewals->map(fn($r) => $this->formatRenewal($r)),
        ]);
    }

    /**
     * POST /api/households/{household_id}/renewals
     * Create a new renewal.
     * Requires: active household member.
     */
    public function store(Request $request, $household_id)
    {
        $validator = Validator::make($request->all(), [
            'title'               => 'required|string|max:255',
            'category'            => 'required|in:insurance,passport,subscription,warranty,contract,medical,other',
            'renewal_date'        => 'required|date',
            'cost'                => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'frequency'           => 'required|in:annual,bi-annual,quarterly,monthly,one-time',
            'notes'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Validate responsible user is a household member
        $isMember = HouseholdMember::where('household_id', $household_id)
            ->where('user_id', $request->responsible_user_id)
            ->where('status', 'active')
            ->exists();

        if (!$isMember) {
            return response()->json([
                'success' => false,
                'message' => 'Responsible user is not an active member of this household.',
            ], 422);
        }

        $renewal = Renewal::create([
            'household_id'        => $household_id,
            'created_by_user_id'  => Auth::id(),
            'title'               => $request->title,
            'category'            => $request->category,
            'renewal_date'        => $request->renewal_date,
            'cost'                => $request->cost,
            'currency'            => $request->currency ?? 'USD',
            'responsible_user_id' => $request->responsible_user_id,
            'frequency'           => $request->frequency,
            'notes'               => $request->notes,
            'status'              => 'active',
        ]);

        $renewal->load('responsibleUser');

        return response()->json([
            'success' => true,
            'message' => 'Renewal created successfully',
            'data'    => $this->formatRenewal($renewal),
        ], 201);
    }

    /**
     * GET /api/renewals/{renewal_id}
     * Get a single renewal with full history.
     * Requires: active household member.
     */
    public function show(Request $request, $renewal_id)
    {
        $renewal = Renewal::with(['responsibleUser', 'history.renewedBy'])->findOrFail($renewal_id);

        if (!$this->getMembership($renewal->household_id)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatRenewal($renewal, true),
        ]);
    }

    /**
     * PATCH /api/renewals/{renewal_id}
     * Update a renewal's details.
     * Requires: admin/co-admin or creator.
     */
    public function update(Request $request, $renewal_id)
    {
        $renewal    = Renewal::findOrFail($renewal_id);
        $membership = $this->getMembership($renewal->household_id);

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if (!$membership->isAdminOrCoAdmin() && $renewal->created_by_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins, co-admins, or the renewal creator can edit this renewal.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'               => 'sometimes|string|max:255',
            'category'            => 'sometimes|in:insurance,passport,subscription,warranty,contract,medical,other',
            'renewal_date'        => 'sometimes|date',
            'cost'                => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'responsible_user_id' => 'sometimes|integer|exists:users,id',
            'frequency'           => 'sometimes|in:annual,bi-annual,quarterly,monthly,one-time',
            'status'              => 'sometimes|in:active,completed,cancelled',
            'notes'               => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $renewal->update($request->only([
            'title', 'category', 'renewal_date', 'cost', 'currency',
            'responsible_user_id', 'frequency', 'status', 'notes',
        ]));

        $renewal->load('responsibleUser');

        return response()->json([
            'success' => true,
            'message' => 'Renewal updated successfully',
            'data'    => $this->formatRenewal($renewal),
        ]);
    }

    /**
     * DELETE /api/renewals/{renewal_id}
     * Delete a renewal. Admin/co-admin or creator only.
     */
    public function destroy(Request $request, $renewal_id)
    {
        $renewal    = Renewal::findOrFail($renewal_id);
        $membership = $this->getMembership($renewal->household_id);

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if (!$membership->isAdminOrCoAdmin() && $renewal->created_by_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins, co-admins, or the renewal creator can delete this renewal.',
            ], 403);
        }

        $renewal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Renewal deleted successfully',
        ]);
    }

    /**
     * POST /api/renewals/{renewal_id}/complete
     * Mark a renewal as completed:
     *   - Logs a RenewalHistory entry (previous date → new date)
     *   - Resets reminder flags
     *   - For non-one-time renewals: updates renewal_date to new_renewal_date and keeps status=active
     *   - For one-time renewals: sets status=completed
     * Requires: active household member.
     */
    public function complete(Request $request, $renewal_id)
    {
        $renewal    = Renewal::findOrFail($renewal_id);
        $membership = $this->getMembership($renewal->household_id);

        if (!$membership) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        if ($renewal->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'This renewal has already been completed.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'new_renewal_date' => 'required_if:frequency,annual,bi-annual,quarterly,monthly|nullable|date|after:renewal_date',
            'cost_paid'        => 'required|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $previousDate = $renewal->renewal_date->toDateString();
        $newDate      = $request->new_renewal_date
                        ?? $renewal->nextRenewalDate()?->toDateString()
                        ?? $previousDate;

        // Log history
        $historyEntry = RenewalHistory::create([
            'renewal_id'          => $renewal->id,
            'renewed_by_user_id'  => Auth::id(),
            'previous_date'       => $previousDate,
            'new_date'            => $newDate,
            'cost'                => $request->cost_paid,
            'notes'               => $request->notes,
        ]);

        // Update renewal
        $isOneTime = $renewal->frequency === 'one-time';
        $renewal->update([
            'renewal_date'        => $isOneTime ? $previousDate : $newDate,
            'status'              => $isOneTime ? 'completed' : 'active',
            'reminder_sent_90d'   => false,
            'reminder_sent_30d'   => false,
            'reminder_sent_7d'    => false,
            'reminder_sent_due'   => false,
        ]);

        $renewal->load('responsibleUser');

        return response()->json([
            'success' => true,
            'message' => 'Renewal completed successfully',
            'data'    => [
                'id'     => $renewal->id,
                'status' => $renewal->status,
                'renewal_date' => $renewal->renewal_date->toDateString(),
                'renewal_history_entry' => [
                    'id'            => $historyEntry->id,
                    'previous_date' => $previousDate,
                    'new_date'      => $newDate,
                    'cost'          => $historyEntry->cost,
                ],
            ],
        ]);
    }

    /**
     * GET /api/households/{household_id}/renewals/upcoming
     * Quick endpoint: renewals due within the next 90 days, sorted by urgency.
     * Requires: active household member.
     */
    public function upcoming(Request $request, $household_id)
    {
        $days = (int) $request->get('days', 90);

        $renewals = Renewal::with('responsibleUser')
            ->where('household_id', $household_id)
            ->where('status', 'active')
            ->where('renewal_date', '>=', now())
            ->where('renewal_date', '<=', now()->addDays($days))
            ->orderBy('renewal_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $renewals->map(fn($r) => $this->formatRenewal($r)),
        ]);
    }
}
