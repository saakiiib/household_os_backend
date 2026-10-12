<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesWebHousehold;
use App\Models\SocialIdentity;
use App\Services\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    use ResolvesWebHousehold;

    public function show(Request $request)
    {
        $user = $request->user();
        $household = $this->webHousehold($request);
        $subscription = $household?->subscription;
        $usage = $household ? (new EntitlementService())->summary($household) : null;
        $linkedProviders = SocialIdentity::where('user_id', $user->id)->pluck('provider')->all();

        return view('web.account', compact('user', 'household', 'subscription', 'usage', 'linkedProviders'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $request->user()->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? null,
        ]);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|max:128|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }
}
