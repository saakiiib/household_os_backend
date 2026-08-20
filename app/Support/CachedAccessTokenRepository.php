<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository as Cache;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class CachedAccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(
        private AccessTokenRepositoryInterface $repository,
        private Cache $cache,
    ) {}

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        return $this->repository->getNewToken($clientEntity, $scopes, $userIdentifier);
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $this->repository->persistNewAccessToken($accessTokenEntity);
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->repository->revokeAccessToken($tokenId);
        $this->cache->forget("passport_token_revoked_{$tokenId}");
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return $this->cache->remember("passport_token_revoked_{$tokenId}", 300, function () use ($tokenId) {
            return $this->repository->isAccessTokenRevoked($tokenId);
        });
    }
}
