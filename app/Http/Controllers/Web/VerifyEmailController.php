<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VerifyEmailController extends Controller
{
    public function notice()
    {
        if ($this->user()->hasVerifiedEmail()) {
            return redirect()->route('billing.plans');
        }

        return view('web.verify');
    }

    /**
     * Verify the 6-digit code. Mirrors the mobile API rules: code must
     * exist, be unexpired (15 min) and match.
     */
    public function verify(Request $request)
    {
        $user = $this->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('billing.plans');
        }

        $request->validate(['code' => 'required|string|size:6']);

        if (!$user->email_verification_code) {
            return back()->withErrors(['code' => 'No verification code found. Please request a new one.']);
        }

        if ($user->email_verification_expires_at && $user->email_verification_expires_at->isPast()) {
            return back()->withErrors(['code' => 'Verification code has expired. Please request a new one.']);
        }

        if ($user->email_verification_code !== $request->code) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        $user->markEmailAsVerified();
        $user->update([
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ]);

        return redirect()->route('billing.plans');
    }

    public function resend(Request $request)
    {
        $user = $this->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('billing.plans');
        }

        try {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->update([
                'email_verification_code' => $code,
                'email_verification_expires_at' => now()->addMinutes(15),
            ]);
            Notification::send($user, new VerifyEmail($code));
        } catch (\Exception $e) {
            Log::error('Web verification resend failed for user ' . $user->id . ': ' . $e->getMessage());

            return back()->withErrors(['code' => 'Failed to send email. Please try again later.']);
        }

        return back()->with('resent', true);
    }

    private function user()
    {
        return auth()->user()->fresh();
    }
}
