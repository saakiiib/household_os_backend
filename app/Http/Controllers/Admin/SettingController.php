<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
            if ($existingLogo && file_exists(public_path($existingLogo))) {
                File::delete(public_path($existingLogo));
            }
            $data['logo'] = $this->uploadFile($request->file('logo'), 'logo');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $existingFavicon = Setting::get('favicon');
            if ($existingFavicon && file_exists(public_path($existingFavicon))) {
                File::delete(public_path($existingFavicon));
            }
            $data['favicon'] = $this->uploadFile($request->file('favicon'), 'favicon');
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
        if ($existing && file_exists(public_path($existing))) {
            File::delete(public_path($existing));
        }

        $path = $this->uploadFile($request->file('file'), $request->type);
        Setting::set($request->type, $path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset($path),
        ]);
    }

    private function uploadFile($file, string $prefix): string
    {
        $dir = public_path('uploads/settings');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $prefix . '_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        return 'uploads/settings/' . $filename;
    }
}
