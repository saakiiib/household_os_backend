<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class ConfigController extends Controller
{
    public function index()
    {
        $logo = Setting::get('logo');
        $logoUrl = $logo ? asset($logo) : null;

        return response()->json([
            'google_web_client_id' => config('services.google.client_id'),
            'google_ios_client_id' => config('services.google.ios_client_id'),
            'apple_client_id' => config('services.apple.client_id'),
            'company_name' => Setting::get('company_name', 'Household OS'),
            'logo_url' => $logoUrl,
            'app_store_url' => $this->storeUrl('app_store_url', (string) config('app_update.ios.store_url', '')),
            'play_store_url' => $this->storeUrl('play_store_url', (string) config('app_update.android.store_url', '')),
        ]);
    }

    private function storeUrl(string $key, string $fallback): ?string
    {
        $value = trim((string) (Setting::get($key) ?? ''));
        if ($value !== '' && !str_contains($value, 'XXXXXX') && !str_contains($value, '000000000') && !str_contains($value, 'com.householdos.app')) {
            return $value;
        }

        $fallback = trim($fallback);
        if ($fallback !== '' && str_starts_with($fallback, 'https://')) {
            return $fallback;
        }

        return $value !== '' ? $value : null;
    }
}
