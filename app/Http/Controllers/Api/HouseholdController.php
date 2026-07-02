<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HouseholdController extends Controller
{
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
                'member_count' => $memberCount,
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

        if (!$membership->isAdminOrCoAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins and co-admins can update household settings.',
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
}
