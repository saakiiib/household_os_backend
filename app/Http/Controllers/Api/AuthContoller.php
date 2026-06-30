<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthContoller extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $token = Auth::user()->createToken('AppName')->accessToken;
            $userId = Auth::user()->id;
            return response()->json(['message' => 'Login successful.', 'token' => $token, 'userId' => $userId], 200);
        }

        return response()->json(['message' => 'Invalid credentials.', 'error' => 'Unauthenticated'], 401);
    }

    public function user()
    {
        return Auth::user();
    }
}
