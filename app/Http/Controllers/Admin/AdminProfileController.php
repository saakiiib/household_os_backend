<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;

class AdminProfileController extends Controller
{
    public function index()
    {
        $admin = Auth::user();
        return spa('admin.profile.index', compact('admin'));
    }

    public function update(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:users,email,' . $admin->id,
            'password' => 'nullable|string|min:6|confirmed',
            'image'    => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = [
            'name'  => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('image')) {
            if ($admin->image && file_exists(public_path($admin->image))) {
                @unlink(public_path($admin->image));
            }

            $randomName = mt_rand(10000000, 99999999) . '.webp';
            $destinationPath = public_path('images/admins/');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            Image::make($request->file('image'))
                ->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->encode('webp', 50)
                ->save($destinationPath . $randomName)
                ->destroy();

            $data['image'] = '/images/admins/' . $randomName;
        }

        $admin->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.'
        ]);
    }
}