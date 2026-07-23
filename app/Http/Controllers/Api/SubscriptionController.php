<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return response()->json([
            'plans' => $plans,
        ]);
    }
}
