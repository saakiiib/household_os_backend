<?php

namespace App\Passport;

use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Bridge\AccessTokenRepository as PassportAccessTokenRepository;

class CachedAccessTokenRepository extends PassportAccessTokenRepository
{
    /**
     * Check if access token is revoked, with 5-minute cache to reduce DB load & connection spikes.
     */
    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return Cache::remember('passport_token_revoked:' . $tokenId, 300, function () use ($tokenId) {
            return parent::isAccessTokenRevoked($tokenId);
        });
    }

    /**
     * Revoke access token and clear the revocation cache immediately.
     */
    public function revokeAccessToken(string $tokenId): void
    {
        parent::revokeAccessToken($tokenId);
        Cache::forget('passport_token_revoked:' . $tokenId);
    }
}
