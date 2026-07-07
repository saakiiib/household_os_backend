<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseholdMember;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;

class ProfileController extends Controller
{
    /**
     * PUT /api/profile
     * Update profile: first_name, last_name, phone, avatar.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'phone'      => 'nullable|string|max:50',
        ]);

        $user->update($validated);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar file
            if ($user->avatar) {
                $oldPath = public_path($user->avatar);
                if (File::exists($oldPath)) {
                    File::delete($oldPath);
                }
            }

            $file = $request->file('avatar');
            $filename = 'avatar_' . $user->id . '_' . time() . '.webp';
            $destPath = public_path('uploads/avatars/');

            if (!File::isDirectory($destPath)) {
                File::makeDirectory($destPath, 0755, true);
            }

            Image::make($file)
                ->resize(800, null, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                })
                ->encode('webp', 80)
                ->save($destPath . $filename);

            $avatarPath = '/uploads/avatars/' . $filename;
            $user->update(['avatar' => $avatarPath]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id'         => $user->id,
                'email'      => $user->email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'phone'      => $user->phone,
                'avatar'     => $user->avatar,
                'name'       => $user->name,
            ]
        ]);
    }

    /**
     * PUT /api/profile/password
     * Change password: current_password, password, password_confirmation.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|max:128|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->update(['password' => $request->password]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * DELETE /api/profile
     * Delete account and all related data.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'confirm_text' => 'required|in:I want to delete my account',
        ]);

        $user = Auth::user();

        // Remove user from all households (set status to removed)
        HouseholdMember::where('user_id', $user->id)->update(['status' => 'removed']);

        // Cancel all pending invitations sent by this user
        Invitation::where('invited_by_user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        // Delete avatar file
        if ($user->avatar) {
            $avatarPath = public_path($user->avatar);
            if (File::exists($avatarPath)) {
                File::delete($avatarPath);
            }
        }

        // Revoke all tokens
        $user->tokens()->each(function ($token) {
            $token->delete();
        });

        // Delete user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
