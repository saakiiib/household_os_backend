<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request)
    {

        $user = User::create([
            'email'      => $request->email,
            'password'   => $request->password, // automatically hashed by User model casts
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'phone'      => $request->phone,
            'status'     => 'active',
        ]);

        // Optionally create a household and make the registering user its admin
        $household = null;
        if ($request->filled('household_name')) {
            $household = Household::create([
                'name'              => $request->household_name,
                'created_by_user_id' => $user->id,
                'status'            => 'active',
            ]);

            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id'      => $user->id,
                'role'         => 'admin',
                'status'       => 'active',
                'joined_at'    => now(),
            ]);
        }

        $token = $user->createToken('HouseholdOS')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => [
                    'id'         => $user->id,
                    'email'      => $user->email,
                    'first_name' => $user->first_name,
                    'last_name'  => $user->last_name,
                    'phone'      => $user->phone,
                    'name'       => $user->name,
                    'created_at' => $user->created_at,
                ],
                'household'  => $household ? ['id' => $household->id, 'name' => $household->name] : null,
                'token'      => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request)
    {

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive.'
                ], 403);
            }

            $token = $user->createToken('HouseholdOS')->accessToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'name' => $user->name,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    /**
     * Get authenticated user details.
     */
    public function user(Request $request)
    {
        $user = Auth::user();

        $households = $user->households->map(function ($household) {
            return [
                'id'   => $household->id,
                'name' => $household->name,
                'role' => $household->pivot->role,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id'         => $user->id,
                'email'      => $user->email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'avatar'     => $user->avatar,
                'households' => $households,
            ]
        ], 200);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $token = Auth::user()->token();
        $token->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }
}
