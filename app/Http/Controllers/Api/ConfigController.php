<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class ConfigController extends Controller
{
    public function index()
    {
        return response()->json([
            'google_web_client_id' => config('services.google.client_id'),
            'google_ios_client_id' => config('services.google.ios_client_id'),
            'apple_client_id' => config('services.apple.client_id'),
        ]);
    }
}
