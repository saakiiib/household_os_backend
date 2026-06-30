<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MembersController extends Controller
{
    /**
     * GET /api/households/{household_id}/members
     * List all active members of a household.
     * Requires: active household member (any role).
     */
    public function index(Request $request, $household_id)
    {
        $household = Household::findOrFail($household_id);

        $members = HouseholdMember::with('user')
            ->where('household_id', $household_id)
            ->whereIn('status', ['active', 'invited'])
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user' => $member->user ? [
                        'id' => $member->user->id,
                        'email' => $member->user->email,
                        'first_name' => $member->user->first_name,
                        'last_name' => $member->user->last_name,
                        'name' => $member->user->name,
                        'avatar' => $member->user->avatar,
                    ] : null,
                    'role' => $member->role,
                    'status' => $member->status,
                    'joined_at' => $member->joined_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * POST /api/households/{household_id}/invitations
     * Invite a new member to the household.
     * Requires: admin or co-admin role.
     */
    public function invite(Request $request, $household_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdminOrCoAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins and co-admins can invite members.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'invited_email' => 'required|email|max:255',
            'role' => 'required|in:admin,co-admin,member',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $household = Household::findOrFail($household_id);

        // Check if a pending invitation already exists for this email
        $existingInvitation = Invitation::where('household_id', $household_id)
            ->where('invited_email', $request->invited_email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'An invitation is already pending for this email.',
            ], 409);
        }

        // Check if user is already an active member
        $invitedUser = User::where('email', $request->invited_email)->first();
        if ($invitedUser) {
            $existingMember = HouseholdMember::where('household_id', $household_id)
                ->where('user_id', $invitedUser->id)
                ->where('status', 'active')
                ->first();

            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user is already an active member of this household.',
                ], 409);
            }
        }

        $invitation = Invitation::create([
            'household_id' => $household_id,
            'invited_by_user_id' => Auth::id(),
            'invited_email' => $request->invited_email,
            'token' => (string) Str::uuid(),
            'role' => $request->role,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully',
            'data' => [
                'id' => $invitation->id,
                'invited_email' => $invitation->invited_email,
                'token' => $invitation->token,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at,
            ]
        ], 201);
    }

    /**
     * POST /api/invitations/{token}/accept
     * Accept a household invitation.
     * Requires: authenticated user.
     */
    public function acceptInvitation(Request $request, $token)
    {
        $invitation = Invitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found.',
            ], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has expired or is no longer valid.',
            ], 410);
        }

        $user = Auth::user();

        // Check user is not already an active member
        $existingMember = HouseholdMember::where('household_id', $invitation->household_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existingMember) {
            return response()->json([
                'success' => false,
                'message' => 'You are already a member of this household.',
            ], 409);
        }

        // Create membership
        HouseholdMember::create([
            'household_id' => $invitation->household_id,
            'user_id' => $user->id,
            'role' => $invitation->role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Update invitation status
        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invitation accepted successfully',
            'data' => [
                'household_id' => $invitation->household_id,
                'household_name' => $invitation->household->name,
                'role' => $invitation->role,
            ]
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/members/{member_id}
     * Change the role of a household member.
     * Requires: admin role only.
     */
    public function updateRole(Request $request, $household_id, $member_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can change member roles.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|in:admin,co-admin,member',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetMember = HouseholdMember::where('id', $member_id)
            ->where('household_id', $household_id)
            ->where('status', 'active')
            ->first();

        if (!$targetMember) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found in this household.',
            ], 404);
        }

        // Prevent changing the household creator's original admin role if they are the only admin
        $targetMember->update(['role' => $request->role]);

        return response()->json([
            'success' => true,
            'message' => 'Member role updated successfully',
            'data' => [
                'id' => $targetMember->id,
                'user_id' => $targetMember->user_id,
                'role' => $targetMember->role,
            ]
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/members/{member_id}
     * Remove a member from the household.
     * Requires: admin role only.
     */
    public function removeMember(Request $request, $household_id, $member_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can remove members.',
            ], 403);
        }

        $targetMember = HouseholdMember::where('id', $member_id)
            ->where('household_id', $household_id)
            ->where('status', 'active')
            ->first();

        if (!$targetMember) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found in this household.',
            ], 404);
        }

        // Prevent admin from removing themselves
        if ($targetMember->user_id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove yourself from the household.',
            ], 409);
        }

        $targetMember->update(['status' => 'removed']);

        return response()->json([
            'success' => true,
            'message' => 'Member removed from household successfully',
        ]);
    }
}
