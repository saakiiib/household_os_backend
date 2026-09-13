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
            'app_store_url' => Setting::get('app_store_url'),
            'play_store_url' => Setting::get('play_store_url'),
        ]);
    }
}
