<?php

namespace App\Auth;

use Psr\Cache\CacheItemPoolInterface;

final class InMemoryPasskeyPolicyStore
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        private readonly bool $defaultRequireAfterPassword,
    ) {
    }

    /**
     * @param list<string> $roles
     */
    public function isPasskeyRequiredAfterPassword(string $email, array $roles): bool
    {
        $normalizedEmail = strtolower(trim($email));
        if ('' === $normalizedEmail || !in_array('ROLE_ADMIN', $roles, true)) {
            return false;
        }

        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($normalizedEmail));
        if ($cacheItem->isHit()) {
            $cachedValue = $cacheItem->get();

            return is_bool($cachedValue) ? $cachedValue : $this->defaultRequireAfterPassword;
        }

        return $this->defaultRequireAfterPassword;
    }

    /**
     * @param list<string> $roles
     */
    public function setPasskeyRequiredAfterPassword(string $email, array $roles, bool $required): void
    {
        $normalizedEmail = strtolower(trim($email));
        if ('' === $normalizedEmail || !in_array('ROLE_ADMIN', $roles, true)) {
            return;
        }

        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($normalizedEmail));
        $cacheItem->set($required);
        $this->cachePool->save($cacheItem);
    }

    private function buildCacheKey(string $normalizedEmail): string
    {
        return 'auth.passkey_policy.'.hash('sha256', $normalizedEmail);
    }
}
