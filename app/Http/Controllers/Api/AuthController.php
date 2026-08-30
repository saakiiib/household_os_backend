<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Notifications\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user and send verification code.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'email'      => $request->email,
            'password'   => $request->password,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'phone'      => $request->phone,
            'status'     => 'active',
        ]);

        // Send verification code
        $emailSent = true;
        try {
            $this->sendVerificationCode($user);
        } catch (\Exception $e) {
            \Log::error('Email failed for user ' . $user->id . ': ' . $e->getMessage());
            $emailSent = false;
        }

        if (!$emailSent) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again or use resend.',
            ], 500);
        }

        // Optionally create a household
        $household = null;
        if ($request->filled('household_name')) {
            $household = Household::create([
                'name'              => $request->household_name,
                'created_by_user_id' => $user->id,
                'status'            => 'active',
            ]);

            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id'      => $user->id,
                'role'         => 'admin',
                'status'       => 'active',
                'joined_at'    => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email for verification code.',
            'data' => [
                'user' => [
                    'id'         => $user->id,
                    'email'      => $user->email,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'phone'      => $user->phone,
                    'name'       => $user->name,
                ],
                'token'      => $user->createToken('HouseholdOS')->accessToken,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }

    /**
     * Verify email with 6-digit code.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        if (!$user->email_verification_code) {
            return response()->json([
                'success' => false,
                'message' => 'No verification code found. Please request a new one.',
            ], 400);
        }

        if ($user->email_verification_expires_at && $user->email_verification_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
            ], 400);
        }

        if ($user->email_verification_code !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 400);
        }

        $user->markEmailAsVerified();
        $user->update([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ]);

        // Create token now that email is verified
        $token = $user->createToken('HouseholdOS')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'user' => [
                    'id'         => $user->id,
                    'email'      => $user->email,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'name'       => $user->name,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'token'      => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    /**
     * Resend verification code.
     */
    public function resendVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account with that email exists, a verification code has been sent.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        $emailSent = true;
        try {
            $this->sendVerificationCode($user);
        } catch (\Exception $e) {
            \Log::error('Resend verification email failed for user ' . $user->id . ': ' . $e->getMessage());
            $emailSent = false;
        }

        if (!$emailSent) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email. Please try again later.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent.',
        ]);
    }

    /**
     * Login user. Blocks if email not verified.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive.'
                ], 403);
            }

            if (is_null($user->email_verified_at)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email before logging in.',
                    'data' => [
                        'email_verified_at' => null,
                    ]
                ], 403);
            }

            $token = $user->createToken('HouseholdOS')->accessToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'name' => $user->name,
                        'email_verified_at' => $user->email_verified_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    /**
     * Send password reset instructions.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            \DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token'       => $code,
                    'created_at'  => now(),
                ]
            );

            $emailSent = true;
            try {
                Notification::send($user, new PasswordResetMail($code));
            } catch (\Exception $e) {
                \Log::error('Password reset email failed for user ' . $user->id . ': ' . $e->getMessage());
                $emailSent = false;
            }

            if (!$emailSent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send reset email. Please try again later.',
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reset instructions have been sent to your email.',
        ]);
    }

    /**
     * Reset password using the token from email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string|size:6',
            'password' => 'required|string|min:8|max:128|confirmed',
        ]);

        $record = \DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || $record->token !== $request->token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            \DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => $request->password]);

        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login.',
        ]);
    }

    /**
     * Get authenticated user details.
     */
    public function user(Request $request)
    {
        $user = Auth::user();

        $households = $user->households->map(function ($household) {
            return [
                'id'   => $household->id,
                'name' => $household->name,
                'role' => $household->pivot->role,
            ];
        });

        // Get household subscription info (subscription is per-household, not per-user)
        $subscription = $user->householdSubscription();
        $subscriptionData = null;
        if ($subscription) {
            $subscription->load('plan');
            $subscriptionData = [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan?->name,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'days_remaining' => $subscription->daysRemaining(),
                'is_active' => $subscription->isActive(),
                'is_trial' => $subscription->isTrial(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'         => $user->id,
                'email'      => $user->email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'avatar'     => $user->avatar,
                'email_verified_at' => $user->email_verified_at,
                'households' => $households,
                'subscription' => $subscriptionData,
            ]
        ], 200);
    }

    /**
     * Get pending invitation(s) for the authenticated user.
     * Matches by verified email so invited users can see the prompt after signup/login.
     */
    public function pendingInvitations(Request $request)
    {
        $user = Auth::user();

        Log::info('pendingInvitations called', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'verified' => (bool) ($user?->email_verified_at),
        ]);

        if (!$user || !$user->email) {
            Log::info('pendingInvitations: no authenticated email');
            return response()->json([
                'success' => true,
                'data' => null,
            ], 200);
        }

        // Auto-mark invitations as accepted if user is already an active or pending member of that household
        $userMemberships = HouseholdMember::where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->get();

        $activeHouseholdIds = $userMemberships->where('status', 'active')->pluck('household_id')->all();
        $allMemberHouseholdIds = $userMemberships->pluck('household_id')->all();

        if (!empty($activeHouseholdIds)) {
            Invitation::whereIn('household_id', $activeHouseholdIds)
                ->where('invited_email', $user->email)
                ->where('status', 'pending')
                ->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'accepted_by_user_id' => $user->id,
                ]);
        }

        // NOTE: A user who already belongs to a household MUST still see pending
        // invitations to OTHER households (spec: Mary in Parents' Home still
        // receives John's invite). The conflict is resolved privately at accept
        // time, so we do not suppress the prompt here.

        $query = Invitation::with(['household', 'invitedBy'])
            ->where('invited_email', $user->email)
            ->where('status', 'pending')
            ->whereNotIn('household_id', $allMemberHouseholdIds)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id');

        Log::info('pendingInvitations query built', [
            'sql_email' => $user->email,
        ]);

        $invitation = $query->first();

        Log::info('pendingInvitations result', [
            'found' => (bool) $invitation,
            'invitation_id' => $invitation?->id,
            'household_id' => $invitation?->household_id,
            'invited_email' => $invitation?->invited_email,
            'status' => $invitation?->status,
        ]);

        if (!$invitation || $invitation->household?->status !== 'active') {
            return response()->json([
                'success' => true,
                'data' => null,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $invitation->id,
                'household_id' => $invitation->household_id,
                'household_name' => $invitation->household?->name,
                'invited_email' => $invitation->invited_email,
                'invited_by_user_id' => $invitation->invited_by_user_id,
                'invited_by_name' => $invitation->invitedBy?->name ?? $invitation->invitedBy?->email ?? 'Someone',
                'role' => $invitation->role,
                'status' => $invitation->status,
                'invitation_token' => $invitation->token,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
        ], 200);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $token = Auth::user()->token();
        $token->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * Preview invitation details by code (public, no auth required).
     * Returns household name, role, and whether account is needed.
     */
    public function previewInviteCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:8',
        ]);

        $code = strtoupper(trim($request->code));

        // Try 6-digit invitation code first
        if (strlen($code) == 6 && ctype_digit($code)) {
            $invitation = Invitation::with('household')
                ->where('token', $code)
                ->where('status', 'pending')
                ->first();

            if (!$invitation || !$invitation->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired invitation code.',
                ], 404);
            }

            $existingUser = User::where('email', $invitation->invited_email)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => 'invitation',
                    'household_name' => $invitation->household->name,
                    'role' => $invitation->role,
                    'invited_email' => $invitation->invited_email,
                    'account_exists' => $existingUser !== null,
                ],
            ]);
        }

        // Try 8-char household invite code
        $household = Household::where('invite_code', $code)
            ->where('status', 'active')
            ->first();

        if (!$household) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invite code.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'household',
                'household_name' => $household->name,
                'role' => 'member',
                'invited_email' => null,
                'account_exists' => false,
            ],
        ]);
    }

    /**
     * Join a household by invite code (public, no auth required).
     * Auto-creates account from invitation email data if account doesn't exist.
     * Returns auth token and user data.
     */
    public function joinByInviteCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:8',
            'password' => 'required|string|min:8|max:128',
        ], [
            'code.required' => 'Invite code is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        $code = strtoupper(trim($request->code));

        // Try 6-digit invitation code first
        if (strlen($code) == 6 && ctype_digit($code)) {
            return $this->joinByInvitationCode($request, $code);
        }

        // Try 8-char household invite code
        return $this->joinByHouseholdCode($request, $code);
    }

    private function joinByInvitationCode(Request $request, string $code)
    {
        $invitation = Invitation::with('household')
            ->where('token', $code)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || !$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation code.',
            ], 404);
        }

        // Check household is still active
        if ($invitation->household->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This household is no longer active.',
            ], 410);
        }

        $email = $invitation->invited_email;
        $user = User::where('email', $email)->first();
        $isNewUser = false;

        if ($user) {
            // Existing user — verify password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect password. Please try again.',
                ], 422);
            }

            // Check if user already belongs to a DIFFERENT household
            $existingMembership = HouseholdMember::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('household_id', '!=', $invitation->household_id)
                ->first();

            if ($existingMembership) {
                $otherHousehold = $existingMembership->household;
                $isCreator = $otherHousehold && $otherHousehold->created_by_user_id === $user->id;
                $isAdmin = $existingMembership->role === 'admin';
                $tempToken = $user->createToken('HouseholdOS')->accessToken;

                if ($isCreator || $isAdmin) {
                    $hasOtherMembers = HouseholdMember::where('household_id', $otherHousehold->id)
                        ->where('status', 'active')
                        ->where('user_id', '!=', $user->id)
                        ->exists();

                    return response()->json([
                        'success' => false,
                        'message' => 'You are an admin or creator of ' . ($otherHousehold->name ?? 'another household') . '. Before joining, you need to transfer ownership or close the household.',
                        'error_code' => 'CREATOR_OF_HOUSEHOLD',
                        'data' => [
                            'current_household_id' => $otherHousehold->id,
                            'current_household_name' => $otherHousehold->name,
                            'has_other_members' => $hasOtherMembers,
                            'token' => $tempToken,
                            'token_type' => 'Bearer',
                            'user' => [
                                'id' => $user->id,
                                'email' => $user->email,
                                'first_name' => $user->first_name,
                                'last_name' => $user->last_name,
                            ],
                        ],
                    ], 409);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'You are already a member of another household. Leave it first before joining.',
                    'error_code' => 'ALREADY_IN_HOUSEHOLD',
                    'data' => [
                        'current_household_id' => $otherHousehold->id,
                        'current_household_name' => $otherHousehold->name,
                        'current_role' => $existingMembership->role,
                        'leave_first' => true,
                        'token' => $tempToken,
                        'token_type' => 'Bearer',
                        'user' => [
                            'id' => $user->id,
                            'email' => $user->email,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                        ],
                    ],
                ], 409);
            }
        } else {
            // New user — require names
            if (empty($request->first_name) || empty($request->last_name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'First name and last name are required for new accounts.',
                ], 422);
            }

            $user = User::create([
                'email' => $email,
                'password' => $request->password,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $isNewUser = true;
        }

        // Check if already pending/active in THIS household
        $existingPending = HouseholdMember::where('household_id', $invitation->household_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingPending) {
            if ($existingPending->status === 'active') {
                // Already a member — just log them in
                $token = $user->createToken('HouseholdOS')->accessToken;
                return response()->json([
                    'success' => true,
                    'message' => 'You are already a member of this household.',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'email' => $user->email,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'name' => $user->name,
                        ],
                        'household' => [
                            'id' => $invitation->household->id,
                            'name' => $invitation->household->name,
                        ],
                        'membership_status' => 'active',
                        'token' => $token,
                        'token_type' => 'Bearer',
                        'is_new_user' => false,
                    ]
                ], 200);
            }
            // Reactivate the existing pending membership as active (direct accept)
            $existingPending->update([
                'role' => $invitation->role,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        } else {
            // Create an active membership — the recipient has accepted the invitation
            HouseholdMember::create([
                'household_id' => $invitation->household_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        // Update invitation
        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_by_user_id' => $user->id,
        ]);

        // Send notification to household admins about new join request
        $this->sendJoinRequestNotification($invitation->household, $user);

        $token = $user->createToken('HouseholdOS')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'You have joined the household.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->name,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'household' => [
                    'id' => $invitation->household->id,
                    'name' => $invitation->household->name,
                ],
                'membership_status' => 'active',
                'token' => $token,
                'token_type' => 'Bearer',
                'is_new_user' => $isNewUser,
            ]
        ], 201);
    }

    private function joinByHouseholdCode(Request $request, string $code)
    {
        $household = Household::where('invite_code', $code)
            ->where('status', 'active')
            ->first();

        if (!$household) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid invite code.',
            ], 404);
        }

        // For household codes, we need to create a temporary account
        // since there's no email to match against
        $email = strtolower(trim($request->first_name . '.' . $request->last_name . '@household.local'));

        // Check if user already exists
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            if (!Hash::check($request->password, $existingUser->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'An account with this name already exists. Please use a different name.',
                ], 409);
            }
            $user = $existingUser;
            $isNewUser = false;
        } else {
            $user = User::create([
                'email' => $email,
                'password' => $request->password,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $isNewUser = true;
        }

        // Check if user already belongs to a household
        $existingActive = HouseholdMember::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existingActive) {
            return response()->json([
                'success' => false,
                'message' => 'You already belong to a household. Leave your current household first.',
            ], 409);
        }

        // Check if already pending in this household
        $existingPending = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingPending) {
            if ($existingPending->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already a member of this household.',
                ], 409);
            }
            $existingPending->update([
                'role' => 'member',
                'status' => 'pending',
                'joined_at' => now(),
            ]);
        } else {
            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'role' => 'member',
                'status' => 'pending',
                'joined_at' => now(),
            ]);
        }

        // Send notification to household admins about new join request
        $this->sendJoinRequestNotification($household, $user);

        $token = $user->createToken('HouseholdOS')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Join request submitted. Waiting for approval from household admin.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'name' => $user->name,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'household' => [
                    'id' => $household->id,
                    'name' => $household->name,
                ],
                'membership_status' => 'pending',
                'token' => $token,
                'token_type' => 'Bearer',
                'is_new_user' => $isNewUser,
            ]
        ], 201);
    }

    private function sendJoinRequestNotification(Household $household, User $newUser): void
    {
        try {
            $adminMembers = HouseholdMember::where('household_id', $household->id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->pluck('user_id')
                ->toArray();

            if (empty($adminMembers)) return;

            $userName = $newUser->name ?? $newUser->email;
            $userEmail = $newUser->email;
            $notificationMessage = "{$userName} ({$userEmail}) has requested to join {$household->name}.";

            app(\App\Services\NotificationService::class)->sendToUsers(
                $adminMembers,
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
                    'user_id' => $newUser->id,
                    'user_email' => $userEmail,
                    'user_name' => $userName,
                ],
                'high'
            );
        } catch (\Throwable $e) {
            \Log::error('Failed to send join request notification: ' . $e->getMessage());
        }
    }

    /**
     * Generate 6-digit code, store it, and send via email.
     */
    private function sendVerificationCode(User $user): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
        ]);

        Notification::send($user, new VerifyEmail($code));
    }
}
