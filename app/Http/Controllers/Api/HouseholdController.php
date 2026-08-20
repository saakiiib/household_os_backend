<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invitation;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HouseholdController extends Controller
{
    /**
     * GET /api/households
     * List all households the authenticated user belongs to.
     */
    public function index(Request $request)
    {
        $households = $request->user()->households()->get();

        return response()->json([
            'success' => true,
            'data' => $households->map(fn($h) => [
                'id'            => $h->id,
                'name'          => $h->name,
                'description'   => $h->description,
                'invite_code'   => $h->invite_code,
                'privacy_level' => $h->privacy_level,
                'status'        => $h->status,
                'created_by_user_id' => $h->created_by_user_id,
                'member_count'  => $h->householdMembers()->where('status', 'active')->count(),
                'created_at'    => $h->created_at,
                'user_role'     => $h->pivot->role ?? null,
                'membership_status' => $h->pivot->status ?? null,
            ]),
        ]);
    }

    /**
     * POST /api/households
     * Create a new household.
     * Requires: authenticated user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'privacy_level' => 'nullable|in:private,public',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Enforce single household per user
        $existingActive = HouseholdMember::where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return response()->json([
                'success' => false,
                'message' => 'You already belong to a household. Leave your current household first before creating a new one.',
            ], 409);
        }

        $household = Household::create([
            'name' => $request->name,
            'created_by_user_id' => Auth::id(),
            'description' => $request->description,
            'privacy_level' => $request->privacy_level ?? 'private',
            'status' => 'active',
        ]);

        // Automatically make the creator an admin
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => Auth::id(),
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Create 3-month free trial on premium plan for this household
        $premiumPlan = SubscriptionPlan::where('slug', 'household_premium')->first();
        if ($premiumPlan) {
            $now = now();
            $trialEnd = $now->copy()->addMonths(3);
            Subscription::create([
                'user_id' => Auth::id(),
                'household_id' => $household->id,
                'subscription_plan_id' => $premiumPlan->id,
                'status' => 'trial',
                'trial_started_at' => $now,
                'trial_ends_at' => $trialEnd,
                'current_period_start' => $now,
                'current_period_end' => $trialEnd,
                'expires_at' => $trialEnd->copy()->addDays(3),
            ]);
        }

        // Seed default categories for this household
        app(CategoriesController::class)->seed($household->id);

        return response()->json([
            'success' => true,
            'message' => 'Household created successfully',
            'data' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'invite_code' => $household->invite_code,
                'privacy_level' => $household->privacy_level,
                'status' => $household->status,
                'created_by_user_id' => Auth::id(),
                'member_count' => 1,
                'user_role' => 'admin',
                'created_at' => $household->created_at,
            ]
        ], 201);
    }

    /**
     * GET /api/households/{id}
     * Get household details.
     * Requires: authenticated user who is an active member.
     */
    public function show($id)
    {
        $household = Household::findOrFail($id);

        // Check if user is an active member
        $membership = HouseholdMember::where('household_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active member of this household.',
            ], 403);
        }

        // Get member count
        $memberCount = HouseholdMember::where('household_id', $id)
            ->where('status', 'active')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'profile_picture' => $household->profile_picture,
                'invite_code' => $household->invite_code,
                'privacy_level' => $household->privacy_level,
                'status' => $household->status,
                'created_by_user_id' => $household->created_by_user_id,
                'member_count' => $memberCount,
                'user_role' => $membership->role,
                'created_at' => $household->created_at,
                'updated_at' => $household->updated_at,
            ]
        ]);
    }

    /**
     * PATCH /api/households/{id}
     * Update household name, description, profile_picture.
     * Requires: admin or co-admin role.
     */
    public function update(Request $request, $id)
    {
        $household = Household::findOrFail($id);

        // Check if user is admin or co-admin
        $membership = HouseholdMember::where('household_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active member of this household.',
            ], 403);
        }

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can update household settings.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'profile_picture' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $household->update($request->only(['name', 'description', 'profile_picture']));

        return response()->json([
            'success' => true,
            'message' => 'Household updated successfully',
            'data' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'profile_picture' => $household->profile_picture,
                'updated_at' => $household->updated_at,
            ]
        ]);
    }

    /**
     * DELETE /api/households/{id}
     * Delete a household (admin only).
     * Requires: admin role.
     */
    public function destroy($id)
    {
        $household = Household::findOrFail($id);

        // Check if user is admin
        $membership = HouseholdMember::where('household_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active member of this household.',
            ], 403);
        }

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can delete a household.',
            ], 403);
        }

        // Soft delete by setting status to archived, or hard delete
        // For safety, let's archive first
        $household->update(['status' => 'archived']);

        return response()->json([
            'success' => true,
            'message' => 'Household archived successfully',
        ]);
    }

    /**
     * POST /api/households/{id}/regenerate-invite
     * Regenerate the invite code for a household.
     * Requires: admin role.
     */
    public function regenerateInvite($id)
    {
        $household = Household::findOrFail($id);

        // Check if user is admin
        $membership = HouseholdMember::where('household_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active member of this household.',
            ], 403);
        }

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can regenerate invite codes.',
            ], 403);
        }

        // Generate new unique invite code
        do {
            $newCode = strtoupper(Str::random(8));
        } while (Household::where('invite_code', $newCode)->exists());

        $household->update(['invite_code' => $newCode]);

        return response()->json([
            'success' => true,
            'message' => 'Invite code regenerated successfully',
            'data' => [
                'invite_code' => $household->invite_code,
            ]
        ]);
    }

    /**
     * POST /api/households/join
     * Join a household using an 8-character invite code.
     */
    public function joinByCode(Request $request)
    {
        $request->validate([
            'invite_code' => 'required|string|size:8',
        ]);

        $household = Household::where('invite_code', strtoupper($request->invite_code))
            ->where('status', 'active')
            ->first();

        if (!$household) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invite code.',
            ], 404);
        }

        $userId = Auth::id();

        // Enforce single household per user
        $existingActive = HouseholdMember::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return response()->json([
                'success' => false,
                'message' => 'You already belong to a household. Leave your current household first before joining another.',
            ], 409);
        }

        $existing = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a member of this household.',
            ], 409);
        }

        // Create membership with pending status (requires admin approval)
        HouseholdMember::updateOrCreate(
            ['household_id' => $household->id, 'user_id' => $userId],
            [
                'role' => 'member',
                'status' => 'pending',
                'joined_at' => now(),
            ]
        );

        // Notify household admins about the new join request (mirrors acceptInvitation)
        try {
            $user = Auth::user();
            $adminIds = HouseholdMember::where('household_id', $household->id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();

            if (!empty($adminIds)) {
                $userName = $user->name ?? $user->email;
                $userEmail = $user->email;
                $notificationMessage = $userName . ' (' . $userEmail . ') requested to join ' . $household->name;

                app(\App\Services\NotificationService::class)->sendToUsers(
                    $adminIds,
                    'New Join Request',
                    $notificationMessage,
                    'join_request',
                    [
                        'module' => 'household',
                        'action_type' => 'household',
                        'action_id' => $household->id,
                        'type' => 'household',
                        'id' => $household->id,
                        'household_id' => $household->id,
                        'user_id' => $user->id,
                        'user_email' => $userEmail,
                        'user_name' => $userName,
                    ],
                    'high'
                );
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to send join request notification to admins: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Join request submitted. Waiting for approval from household admin.',
            'data' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'invite_code' => $household->invite_code,
                'privacy_level' => $household->privacy_level,
                'status' => $household->status,
                'created_by_user_id' => $household->created_by_user_id,
                'member_count' => $household->householdMembers()->where('status', 'active')->count(),
                'user_role' => 'member',
                'membership_status' => 'pending',
                'created_at' => $household->created_at,
            ]
        ], 201);
    }

    /**
     * POST /api/households/{id}/abandon
     * Admin abandons (deletes) the household and all its data.
     * Requires: admin role. Confirmation token required.
     */
    public function abandonHousehold(Request $request, $id)
    {
        $request->validate([
            'confirm' => 'required|in:abandon',
        ]);

        $household = Household::findOrFail($id);

        $membership = HouseholdMember::where('household_id', $id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active member of this household.',
            ], 403);
        }

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can abandon a household.',
            ], 403);
        }

        // Delete all invitations for this household
        Invitation::where('household_id', $id)->delete();

        // Delete all household memberships
        HouseholdMember::where('household_id', $id)->delete();

        // Delete the household itself
        $household->delete();

        return response()->json([
            'success' => true,
            'message' => 'Household abandoned and deleted successfully.',
        ]);
    }

    /**
     * Leave a household (regular members only).
     * Does NOT delete the household — just removes the membership.
     */
    public function leave(Request $request)
    {
        $request->validate([
            'household_id' => 'required|integer|exists:households,id',
        ]);

        $user = $request->user();
        $householdId = $request->household_id;

        $membership = HouseholdMember::where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of this household.',
            ], 404);
        }

        if ($membership->role === 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Household owners cannot leave. Transfer ownership first.',
            ], 403);
        }

        $membership->delete();

        // Notify remaining admins that a member left
        try {
            $adminIds = HouseholdMember::where('household_id', $householdId)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();

            if (!empty($adminIds)) {
                $household = Household::find($householdId);
                app(\App\Services\NotificationService::class)->sendToUsers(
                    $adminIds,
                    'Member Left',
                    ($user->name ?? $user->email) . ' has left ' . ($household->name ?? 'the household'),
                    'member_left',
                    [
                        'module' => 'household',
                        'action_type' => 'household',
                        'action_id' => $householdId,
                        'type' => 'household',
                        'id' => $householdId,
                        'operation' => 'leave',
                        'household_id' => $householdId,
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'user_name' => $user->name ?? $user->email,
                    ],
                    'normal'
                );
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to send member left notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'You have left the household.',
        ]);
    }
}
