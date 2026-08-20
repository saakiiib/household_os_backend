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
        // Use the FILE cache store explicitly. If CACHE_STORE were "database",
        // caching token checks here would add MORE MySQL queries per request
        // (cache read + token lookup + cache write) and multiply the DB load
        // that causes the "Operation not permitted" [2002] freeze.
        return Cache::store('file')->remember('passport_token_revoked:' . $tokenId, 300, function () use ($tokenId) {
            return parent::isAccessTokenRevoked($tokenId);
        });
    }

    public function revokeAccessToken(string $tokenId): void
    {
        parent::revokeAccessToken($tokenId);
        Cache::store('file')->forget('passport_token_revoked:' . $tokenId);
    }
}
