<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RegisterController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('billing.plans');
        }

        return view('web.register');
    }

    /**
     * Register a web subscriber. Creates the user only (same as the
     * mobile API registration). The household is created on the next
     * step, before any plan can be bought, so registration can never
     * attach payment to the wrong household.
     */
    public function store(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('billing.plans');
        }

        $validated = $request->validate([
            'email' => 'required|email:rfc,dns|unique:users,email|max:255',
            'password' => 'required|string|min:8|max:128|confirmed',
            'first_name' => 'required|string|min:2|max:100|alpha',
            'last_name' => 'required|string|min:2|max:100|alpha',
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'status' => 'active',
        ]);

        try {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->update([
                'email_verification_code' => $code,
                'email_verification_expires_at' => now()->addMinutes(15),
            ]);
            Notification::send($user, new VerifyEmail($code));
        } catch (\Exception $e) {
            Log::error('Web registration email failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
