<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    /**
     * Login/register with a Google ID token issued to HouseholdOS.
     */
    public function google(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string|max:10000',
        ]);

        try {
            $googleUser = $this->verifyGoogleToken($request->string('id_token')->toString());
        } catch (\Throwable $e) {
            Log::warning('Google social login rejected', [
                'reason' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid Google sign-in. Please try again.',
            ], 401);
        }

        return $this->findOrCreateSocialUser(
            provider: 'google',
            providerId: $googleUser['sub'],
            email: $googleUser['email'],
            firstName: $googleUser['given_name'] ?? '',
            lastName: $googleUser['family_name'] ?? '',
            fallbackName: $googleUser['name'] ?? '',
            avatar: $googleUser['picture'] ?? null,
            emailVerified: true,
        );
    }

    /**
     * Login/register with an Apple identity token issued to HouseholdOS.
     * Apple only supplies fullName to the native client on the first consent,
     * so first/last name are accepted as optional, tightly validated fields.
     */
    public function apple(Request $request)
    {
        $validated = $request->validate([
            'identity_token' => 'required|string|max:12000',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
        ]);

        try {
            $appleUser = $this->verifyAppleToken($validated['identity_token']);
        } catch (\Throwable $e) {
            Log::warning('Apple social login rejected', [
                'reason' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid Apple sign-in. Please try again.',
            ], 401);
        }

        return $this->findOrCreateSocialUser(
            provider: 'apple',
            providerId: $appleUser['sub'],
            email: $appleUser['email'],
            firstName: $this->cleanDisplayName($validated['first_name'] ?? ''),
            lastName: $this->cleanDisplayName($validated['last_name'] ?? ''),
            fallbackName: '',
            avatar: null,
            emailVerified: true,
        );
    }

    /**
     * Resolve a social identity safely.
     *
     * IMPORTANT: the current schema stores one social provider per user. We do
     * not silently overwrite an existing different provider merely because an
     * email address matches; that can create ambiguous identity linking. A
     * verified social identity may be attached to an old password-only account,
     * but switching Google <-> Apple on an already-linked account requires an
     * explicit account-linking feature in the future.
     */
    private function findOrCreateSocialUser(
        string $provider,
        string $providerId,
        string $email,
        string $firstName = '',
        string $lastName = '',
        string $fallbackName = '',
        ?string $avatar = null,
        bool $emailVerified = false,
    ) {
        $email = strtolower(trim($email));
        $providerId = trim($providerId);
        $firstName = $this->cleanDisplayName($firstName);
        $lastName = $this->cleanDisplayName($lastName);
        $fallbackName = $this->cleanDisplayName($fallbackName);

        if ($providerId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'The identity provider did not return a usable account.',
            ], 401);
        }

        if (!$emailVerified) {
            return response()->json([
                'success' => false,
                'message' => 'The identity provider has not verified this email address.',
            ], 401);
        }

        if ($firstName === '' && $fallbackName !== '') {
            $parts = preg_split('/\s+/', $fallbackName, 2) ?: [];
            $firstName = $parts[0] ?? '';
            $lastName = $lastName !== '' ? $lastName : ($parts[1] ?? '');
        }

        // 1) Stable provider subject is the primary social identity key.
        $user = User::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        // 2) A verified email may attach this provider to a legacy password-only
        // account, but never overwrite a different existing social provider.
        if (!$user) {
            $emailUser = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if ($emailUser) {
                $existingProvider = strtolower(trim((string) $emailUser->provider));
                $existingProviderId = trim((string) $emailUser->provider_id);

                if ($existingProvider !== '' && $existingProvider !== $provider) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An account with this email already uses a different sign-in method. Please use your original sign-in method.',
                    ], 409);
                }

                if ($existingProvider === $provider && $existingProviderId !== '' && $existingProviderId !== $providerId) {
                    Log::warning('Social login subject mismatch for existing email', [
                        'provider' => $provider,
                        'user_id' => $emailUser->id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'This sign-in could not be linked to the existing account. Please use your original sign-in method.',
                    ], 409);
                }

                $emailUser->update([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'email_verified_at' => $emailUser->email_verified_at ?: now(),
                ]);
                $user = $emailUser->fresh();
            }
        }

        // 3) Otherwise create a brand-new user.
        if (!$user) {
            $user = User::create([
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'password' => \Illuminate\Support\Str::random(64),
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $avatar,
                'email_verified_at' => now(),
                'status' => 'active',
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.',
            ], 403);
        }

        // Fill only missing profile fields; never overwrite a name the user set.
        $profileUpdates = [];
        if (empty($user->first_name) && $firstName !== '') {
            $profileUpdates['first_name'] = $firstName;
        }
        if (empty($user->last_name) && $lastName !== '') {
            $profileUpdates['last_name'] = $lastName;
        }
        if (empty($user->email_verified_at)) {
            $profileUpdates['email_verified_at'] = now();
        }
        if (!empty($profileUpdates)) {
            $user->update($profileUpdates);
        }

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
            ],
        ], 200);
    }

    /**
     * Verify Google ID token using Google's tokeninfo validation service and
     * then fail closed on HouseholdOS audience + verified email.
     */
    private function verifyGoogleToken(string $idToken): array
    {
        $validIds = array_values(array_unique(array_filter([
            config('services.google.client_id'),
            config('services.google.android_client_id'),
            config('services.google.ios_client_id'),
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        if (empty($validIds)) {
            throw new \RuntimeException('Google OAuth client IDs are not configured');
        }

        $response = Http::timeout(6)
            ->retry(1, 150)
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Google rejected the ID token');
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid Google token response');
        }

        $audience = (string) ($data['aud'] ?? '');
        $issuer = (string) ($data['iss'] ?? '');
        $subject = trim((string) ($data['sub'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $emailVerified = filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!in_array($audience, $validIds, true)) {
            throw new \RuntimeException('Google token audience mismatch');
        }
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new \RuntimeException('Google token issuer mismatch');
        }
        if ($subject === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Google token missing required identity claims');
        }
        if (!$emailVerified) {
            throw new \RuntimeException('Google email is not verified');
        }

        return [
            'sub' => $subject,
            'email' => $email,
            'email_verified' => true,
            'name' => (string) ($data['name'] ?? ''),
            'given_name' => (string) ($data['given_name'] ?? ''),
            'family_name' => (string) ($data['family_name'] ?? ''),
            'picture' => isset($data['picture']) ? (string) $data['picture'] : null,
        ];
    }

    /**
     * Cryptographically verify Apple's identity JWT using Apple's current JWKS.
     * firebase/php-jwt is already present in this application's composer.lock.
     */
    private function verifyAppleToken(string $identityToken): array
    {
        $allowedAudiences = array_values(array_unique(array_filter([
            config('services.apple.client_id'),
            config('services.apple.bundle_id'),
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        if (empty($allowedAudiences)) {
            throw new \RuntimeException('Apple client ID/bundle ID is not configured');
        }

        $jwks = $this->appleJwks();

        try {
            $claims = (array) JWT::decode($identityToken, JWK::parseKeySet($jwks, 'RS256'));
        } catch (\Throwable $first) {
            // Apple's signing keys rotate. If a cached keyset misses the new kid,
            // refresh once and retry before rejecting the login.
            Cache::forget('signin_apple_jwks');
            $jwks = $this->appleJwks();
            $claims = (array) JWT::decode($identityToken, JWK::parseKeySet($jwks, 'RS256'));
        }

        $issuer = (string) ($claims['iss'] ?? '');
        $aud = $claims['aud'] ?? '';
        $audiences = is_array($aud) ? array_map('strval', $aud) : [(string) $aud];
        $subject = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $issuedAt = isset($claims['iat']) ? (int) $claims['iat'] : 0;

        if ($issuer !== 'https://appleid.apple.com') {
            throw new \RuntimeException('Apple token issuer mismatch');
        }
        if (empty(array_intersect($audiences, $allowedAudiences))) {
            throw new \RuntimeException('Apple token audience mismatch');
        }
        if ($subject === '') {
            throw new \RuntimeException('Apple token missing subject');
        }
        if ($issuedAt > time() + 120) {
            throw new \RuntimeException('Apple token issued in the future');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Apple token missing a usable email');
        }
        if (!$emailVerified) {
            throw new \RuntimeException('Apple email is not verified');
        }

        return [
            'sub' => $subject,
            'email' => $email,
        ];
    }

    /** @return array<string,mixed> */
    private function appleJwks(): array
    {
        return Cache::remember('signin_apple_jwks', now()->addHour(), function () {
            $response = Http::timeout(6)
                ->retry(1, 150)
                ->get('https://appleid.apple.com/auth/keys');

            if ($response->failed()) {
                throw new \RuntimeException('Unable to load Apple signing keys');
            }

            $jwks = $response->json();
            if (!is_array($jwks) || empty($jwks['keys']) || !is_array($jwks['keys'])) {
                throw new \RuntimeException('Invalid Apple signing key response');
            }

            return $jwks;
        });
    }

    private function cleanDisplayName(?string $value): string
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        return mb_substr($value, 0, 100);
    }
}
