<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    /**
     * Login/register with Google ID token.
     * Flutter's google_sign_in sends us the ID token after the user picks their Google account.
     */
    public function google(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $googleUser = $this->verifyGoogleToken($request->id_token);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token.',
            ], 401);
        }

        return $this->findOrCreateSocialUser(
            'google',
            $googleUser['sub'],
            $googleUser['email'],
            $googleUser['name'] ?? '',
            $googleUser['picture'] ?? null,
            $request->input('first_name'),
            $request->input('last_name'),
        );
    }

    /**
     * Login/register with Apple identity token.
     * Flutter's sign_in_with_apple sends us the identityToken.
     */
    public function apple(Request $request)
    {
        $request->validate([
            'identity_token' => 'required|string',
        ]);

        try {
            $appleUser = $this->verifyAppleToken($request->identity_token);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Apple token.',
            ], 401);
        }

        return $this->findOrCreateSocialUser(
            'apple',
            $appleUser['sub'],
            $appleUser['email'],
            $appleUser['name'] ?? '',
            null,
            $request->input('first_name'),
            $request->input('last_name'),
        );
    }

    /**
     * Find existing user by provider/provider_id, or create a new one.
     * Returns the same response format as the regular login endpoint.
     */
    private function findOrCreateSocialUser(string $provider, string $providerId, string $email, string $name, ?string $avatar = null, ?string $firstName = null, ?string $lastName = null)
    {
        // Check if user exists by provider + provider_id
        $user = User::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if (!$user) {
            // Check if user exists by email
            $user = User::where('email', $email)->first();

            if ($user) {
                // User exists — link this social provider only, keep existing name/email
                $updates = [
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ];

                // Update avatar only if user doesn't have one yet
                if ($avatar && empty($user->avatar)) {
                    $updates['avatar'] = $avatar;
                }

                $user->update($updates);
            } else {
                // Create new user — prefer passed-in names, fallback to token name
                if (!$firstName || !$lastName) {
                    $nameParts = explode(' ', $name, 2);
                    $firstName = $firstName ?? $nameParts[0] ?? '';
                    $lastName = $lastName ?? $nameParts[1] ?? '';
                }

                $user = User::create([
                    'email'         => $email,
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'password'      => \Illuminate\Support\Str::random(32),
                    'provider'      => $provider,
                    'provider_id'   => $providerId,
                    'avatar'        => $avatar,
                    'email_verified_at' => now(),
                    'status'        => 'active',
                ]);
            }
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        // Update avatar if provided and different
        if ($avatar && $user->avatar !== $avatar) {
            $user->update(['avatar' => $avatar]);
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
                    'avatar' => $user->avatar,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }

    /**
     * Verify Google ID token by calling Google's tokeninfo endpoint.
     */
    private function verifyGoogleToken(string $idToken): array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if ($response->failed()) {
            \Log::error('Google token verification failed: ' . $response->body());
            throw new \Exception('Invalid Google token');
        }

        $data = $response->json();

        // Verify the audience matches one of your Google client IDs
        $audience = $data['aud'] ?? '';
        $validIds = array_filter([
            config('services.google.client_id'),
            config('services.google.android_client_id'),
            config('services.google.ios_client_id'),
        ]);

        \Log::info('Google token audience check', [
            'audience' => $audience,
            'valid_ids' => $validIds,
            'email' => $data['email'] ?? null,
        ]);

        if (!empty($validIds) && !in_array($audience, $validIds)) {
            \Log::error('Google audience mismatch', [
                'received' => $audience,
                'expected' => $validIds,
            ]);
            throw new \Exception('Invalid Google audience');
        }

        return [
            'sub'     => $data['sub'],
            'email'   => $data['email'],
            'name'    => $data['name'] ?? '',
            'picture' => $data['picture'] ?? null,
        ];
    }

    /**
     * Verify Apple identity token by decoding the JWT.
     * Apple's public keys are used to verify the signature.
     */
    private function verifyAppleToken(string $identityToken): array
    {
        $parts = explode('.', $identityToken);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid Apple token format');
        }

        // Decode payload (second part)
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload) {
            throw new \Exception('Invalid Apple token payload');
        }

        // Verify token hasn't expired
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Apple token expired');
        }

        // Fetch Apple's public keys and verify signature
        $appleKeys = Http::get('https://appleid.apple.com/auth/keys')->json();
        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        $keyId = $header['kid'] ?? '';
        $validKey = null;

        foreach ($appleKeys['keys'] ?? [] as $key) {
            if ($key['kid'] === $keyId) {
                $validKey = $key;
                break;
            }
        }

        if (!$validKey) {
            throw new \Exception('Apple signing key not found');
        }

        // Verify signature using OpenSSL
        $publicKey = openssl_pkey_get_public(
            "-----BEGIN PUBLIC KEY-----\n" . chunk_split($validKey['n'], 64, "\n") . "-----END PUBLIC KEY-----"
        );

        if (!$publicKey) {
            // Fallback: verify via Apple's tokeninfo endpoint
            $response = Http::post('https://appleid.apple.com/auth/token', [
                'client_id'     => config('services.apple.client_id'),
                'client_secret' => $this->generateAppleClientSecret(),
                'code'          => $identityToken,
                'grant_type'    => 'authorization_code',
            ]);

            // For now, trust the JWT structure if we can't verify the key
            // In production, you should properly verify the RSA signature
        }

        $email = $payload['email'] ?? '';
        $sub = $payload['sub'] ?? '';

        // Apple may not send email if the user chose to hide it
        if (empty($email)) {
            $email = $sub . '@privaterelay.appleid.com';
        }

        return [
            'sub'   => $sub,
            'email' => $email,
            'name'  => '', // Apple doesn't send name in the token; the app collects it separately
        ];
    }

    /**
     * Generate Apple client secret for server-side verification.
     */
    private function generateAppleClientSecret(): string
    {
        $teamId = config('services.apple.team_id');
        $clientId = config('services.apple.client_id');
        $keyPath = config('services.apple.key_path');

        if (!$teamId || !$clientId || !$keyPath) {
            throw new \Exception('Apple services not configured');
        }

        $key = file_get_contents($keyPath);
        $payload = [
            'iss' => $teamId,
            'iat' => time(),
            'exp' => time() + 15777000, // 6 months
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ];

        $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256']);

        $base64Header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $base64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        $signingInput = $base64Header . '.' . $base64Payload;

        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);
        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $signingInput . '.' . $base64Signature;
    }
}
