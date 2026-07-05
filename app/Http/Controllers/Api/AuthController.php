<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
            'message' => $emailSent
                ? 'Registration successful. Please check your email for verification code.'
                : 'Registration successful but email could not be sent. Please use resend verification.',
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

        return response()->json([
            'success' => true,
            'message' => $emailSent
                ? 'Verification code sent.'
                : 'Failed to send email. Please try again later.',
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

            $subject = 'Reset Your Password - Household OS';
            $body = "Hi {$user->first_name},\n\n";
            $body .= "Your password reset code is: {$code}\n\n";
            $body .= "This code expires in 60 minutes.\n\n";
            $body .= "If you did not request this, ignore this email.\n\n";
            $body .= "Thanks,\nHousehold OS Team";

            try {
                Mail::raw($body, function ($message) use ($user, $subject) {
                    $message->to($user->email)
                            ->subject($subject)
                            ->from(config('mail.from.address'), config('mail.from.name'));
                });
            } catch (\Exception $e) {
                \Log::error('Password reset email failed for user ' . $user->id . ': ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account with that email exists, reset instructions have been sent.',
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
            ]
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
     * Generate 6-digit code, store it, and send via email.
     */
    private function sendVerificationCode(User $user): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'email_verification_code' => $code,
            'email_verification_expires_at' => now()->addMinutes(15),
        ]);

        $subject = 'Your Verification Code - Household OS';
        $body = "Hi {$user->first_name},\n\n";
        $body .= "Your verification code is: {$code}\n\n";
        $body .= "This code expires in 15 minutes.\n\n";
        $body .= "If you did not create an account, no action is needed.\n\n";
        $body .= "Thanks,\nHousehold OS Team";

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));
        });
    }
}
