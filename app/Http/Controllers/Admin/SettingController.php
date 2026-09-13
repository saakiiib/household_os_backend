<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::allAsArray();

        return view('admin.pages.settings', [
            'active' => 'settings',
            'pageTitle' => 'Platform Settings',
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->except(['logo', 'favicon']);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $existingLogo = Setting::get('logo');
            if ($existingLogo && Storage::disk('public')->exists($existingLogo)) {
                Storage::disk('public')->delete($existingLogo);
            }
            $data['logo'] = $request->file('logo')->store('uploads/settings', 'public');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $existingFavicon = Setting::get('favicon');
            if ($existingFavicon && Storage::disk('public')->exists($existingFavicon)) {
                Storage::disk('public')->delete($existingFavicon);
            }
            $data['favicon'] = $request->file('favicon')->store('uploads/settings', 'public');
        }

        Setting::setMany($data);

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,svg,webp,ico',
            'type' => 'required|string|in:logo,favicon',
        ]);

        $existing = Setting::get($request->type);
        if ($existing && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }

        $path = $request->file('file')->store('uploads/settings', 'public');
        Setting::set($request->type, $path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
