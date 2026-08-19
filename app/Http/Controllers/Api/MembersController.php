<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invitation;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateMemberRoleRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Notifications\InvitationMail;

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

        // Get active members
        $members = HouseholdMember::with('user')
            ->where('household_id', $household_id)
            ->where('status', 'active')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
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

        // Get pending members (waiting for approval)
        $pendingMembers = HouseholdMember::with('user')
            ->where('household_id', $household_id)
            ->where('status', 'pending')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
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

        // Get pending invitations
        $invitations = Invitation::where('household_id', $household_id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'user_id' => null,
                    'user' => [
                        'id' => null,
                        'email' => $invitation->invited_email,
                        'first_name' => null,
                        'last_name' => null,
                        'name' => $invitation->invited_email,
                        'avatar' => null,
                    ],
                    'role' => $invitation->role,
                    'status' => 'invited',
                    'joined_at' => null,
                    'invitation_token' => $invitation->token,
                    'invitation_expires_at' => $invitation->expires_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members->merge($pendingMembers)->merge($invitations),
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

        $invitedEmail = $request->input('invited_email', $request->input('email'));

        $validator = Validator::make($request->all() + ['invited_email' => $invitedEmail], [
            'invited_email' => 'required|email|max:255',
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
            ->where('invited_email', $invitedEmail)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'success' => false,
                'message' => 'An invitation is already pending for this email.',
            ], 409);
        }

        // Check if user is already an active member
        $invitedUser = User::where('email', $invitedEmail)->first();
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

            // Enforce single household: invitee must not belong to another household
            $otherHousehold = HouseholdMember::where('user_id', $invitedUser->id)
                ->where('status', 'active')
                ->first();

            if ($otherHousehold) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user already belongs to another household. They must leave their current household before joining yours.',
                ], 409);
            }
        }

        // Generate short 6-digit code (same as verification codes)
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Always assign 'member' role - only creator is admin
        $invitation = Invitation::create([
            'household_id' => $household_id,
            'invited_by_user_id' => Auth::id(),
            'invited_email' => $invitedEmail,
            'token' => $code,
            'role' => 'member',
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        // Send invitation email
        $emailSent = true;
        try {
            $inviter = Auth::user();
            $inviterName = $inviter->name ?? $inviter->email;
            Notification::route('mail', $invitedEmail)
                ->notify(new InvitationMail(
                    $household->name,
                    $invitation->token,
                    $invitation->role,
                    $inviterName
                ));
        } catch (\Exception $e) {
            \Log::error('Invitation email failed for ' . $invitedEmail . ': ' . $e->getMessage());
            $emailSent = false;
        }

        // Send in-app/push notification to invited user if they have an account
        if ($invitedUser) {
            try {
                $inviter = Auth::user();
                app(NotificationService::class)->sendToUser(
                    $invitedUser->id,
                    'Household Invitation',
                    ($inviter->name ?? $inviter->email) . ' invited you to join ' . $household->name,
                    'invitation',
                    ['type' => 'invitation', 'id' => $invitation->id, 'household_id' => $household->id, 'invitation_token' => $invitation->token],
                    'high'
                );
            } catch (\Throwable $e) {
                \Log::error('Failed to send invitation notification: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent
                ? 'Invitation sent successfully'
                : 'Invitation created but email could not be sent. Please share the invite code manually.',
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

        // Only the invited email's owner can accept
        if (strtolower($user->email) !== strtolower($invitation->invited_email)) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation is not for your email address.',
            ], 403);
        }

        // Check household is still active
        if ($invitation->household->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This household is no longer active.',
            ], 410);
        }

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

        // Enforce single household: user must not belong to another household
        $otherHousehold = HouseholdMember::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($otherHousehold) {
            return response()->json([
                'success' => false,
                'message' => 'You already belong to another household. Leave your current household first before accepting this invitation.',
            ], 409);
        }

        // Create membership with pending status (requires admin approval)
        HouseholdMember::updateOrCreate(
            ['household_id' => $invitation->household_id, 'user_id' => $user->id],
            [
                'role' => $invitation->role,
                'status' => 'pending',
                'joined_at' => now(),
            ]
        );

        // Update invitation status
        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->id,
        ]);

        // Notify household admins about the new join request
        try {
            $adminIds = HouseholdMember::where('household_id', $invitation->household_id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();

            if (!empty($adminIds)) {
                app(NotificationService::class)->sendToUsers(
                    $adminIds,
                    'New Join Request',
                    ($user->name ?? $user->email) . ' accepted the invitation to join ' . $invitation->household->name,
                    'join_request',
                    ['type' => 'household', 'id' => $invitation->household_id, 'household_id' => $invitation->household_id, 'user_id' => $user->id],
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
                'household_id' => $invitation->household_id,
                'household_name' => $invitation->household->name,
                'role' => $invitation->role,
                'membership_status' => 'pending',
            ]
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/members/{member_id}
     * Change the role of a household member.
     * Requires: admin role only.
     */
    public function updateRole(UpdateMemberRoleRequest $request, $household_id, $member_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can change member roles.',
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

        // Prevent demoting the last admin
        if ($targetMember->isAdmin() && $request->role !== 'admin') {
            $adminCount = HouseholdMember::where('household_id', $household_id)
                ->where('status', 'active')
                ->where('role', 'admin')
                ->count();

            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot demote the last admin.',
                ], 409);
            }
        }

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

        // Notify the removed member
        try {
            $household = Household::find($household_id);
            app(NotificationService::class)->sendToUser(
                $targetMember->user_id,
                'Removed from Household',
                'You have been removed from ' . ($household->name ?? 'your household'),
                'member_removed',
                ['type' => 'household', 'id' => $household_id, 'household_id' => $household_id],
                'high'
            );
        } catch (\Throwable $e) {
            \Log::error('Failed to send member removed notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Member removed from household successfully',
        ]);
    }

    /**
     * DELETE /api/households/{household_id}/invitations/{invitation_id}
     * Cancel a pending invitation.
     * Requires: admin or co-admin role.
     */
    public function cancelInvitation(Request $request, $household_id, $invitation_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can cancel invitations.',
            ], 403);
        }

        $invitation = Invitation::where('id', $invitation_id)
            ->where('household_id', $household_id)
            ->where('status', 'pending')
            ->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found or already processed.',
            ], 404);
        }

        $invitation->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Invitation cancelled successfully',
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/members/{member_id}/approve
     * Approve a pending member.
     * Requires: admin role only.
     */
    public function approveMember(Request $request, $household_id, $member_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can approve members.',
            ], 403);
        }

        $targetMember = HouseholdMember::where('id', $member_id)
            ->where('household_id', $household_id)
            ->where('status', 'pending')
            ->first();

        if (!$targetMember) {
            return response()->json([
                'success' => false,
                'message' => 'Pending member not found.',
            ], 404);
        }

        $targetMember->update([
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Send approval notification to the member
        try {
            app(NotificationService::class)->sendToUser(
                $targetMember->user_id,
                'Welcome to the Family!',
                'Your request to join ' . $household->name . ' has been approved.',
                'member_approved',
                ['type' => 'household', 'id' => $household->id, 'household_id' => $household->id, 'household_name' => $household->name],
                'normal'
            );
        } catch (\Throwable $e) {
            \Log::error('Failed to send approval notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Member approved successfully',
            'data' => [
                'id' => $targetMember->id,
                'user_id' => $targetMember->user_id,
                'role' => $targetMember->role,
                'status' => $targetMember->status,
            ]
        ]);
    }

    /**
     * PATCH /api/households/{household_id}/members/{member_id}/reject
     * Reject a pending member and delete their invitation.
     * Requires: admin role only.
     */
    public function rejectMember(Request $request, $household_id, $member_id)
    {
        $membership = $request->get('_household_member');

        if (!$membership->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only admins can reject members.',
            ], 403);
        }

        $targetMember = HouseholdMember::where('id', $member_id)
            ->where('household_id', $household_id)
            ->where('status', 'pending')
            ->first();

        if (!$targetMember) {
            return response()->json([
                'success' => false,
                'message' => 'Pending member not found.',
            ], 404);
        }

        // Get the user ID before deleting
        $userId = $targetMember->user_id;

        // Delete the pending membership
        $targetMember->delete();

        // Delete the associated invitation so user can be invited by other households
        Invitation::where('household_id', $household_id)
            ->where('invited_email', User::find($userId)->email ?? '')
            ->where('status', 'accepted')
            ->delete();

        // Send rejection notification to the member
        try {
            app(NotificationService::class)->sendToUser(
                $userId,
                'Join Request Not Approved',
                'Your request to join ' . $household->name . ' was not approved.',
                'member_rejected',
                ['type' => 'household', 'id' => $household->id, 'household_id' => $household->id, 'household_name' => $household->name],
                'normal'
            );
        } catch (\Throwable $e) {
            \Log::error('Failed to send rejection notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Member rejected. Invitation has been voided.',
        ]);
    }
}
