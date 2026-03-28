<?php

namespace App\Auth;

use Psr\Cache\CacheItemPoolInterface;

final class InMemoryRefreshTokenRevocationStore
{
    public function __construct(private readonly CacheItemPoolInterface $cachePool)
    {
    }

    public function revoke(string $jti, int $expiresAt): void
    {
        if ('' === trim($jti) || $expiresAt <= time()) {
            return;
        }

        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($jti));
        $cacheItem->set(true);
        $cacheItem->expiresAt((new \DateTimeImmutable())->setTimestamp($expiresAt));
        $this->cachePool->save($cacheItem);
    }

    public function isRevoked(string $jti): bool
    {
        if ('' === trim($jti)) {
            return false;
        }

        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($jti));

        return $cacheItem->isHit() && true === $cacheItem->get();
    }

    private function buildCacheKey(string $jti): string
    {
        return 'auth.refresh_revoked.'.hash('sha256', $jti);
    }
}