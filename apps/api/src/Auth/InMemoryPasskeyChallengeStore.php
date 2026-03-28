<?php

namespace App\Auth;

use Psr\Cache\CacheItemPoolInterface;

final class InMemoryPasskeyChallengeStore
{
    public function __construct(private readonly CacheItemPoolInterface $cachePool)
    {
    }

    public function issueChallenge(string $email, string $flow = 'login', int $ttlSeconds = 120): string
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedFlow = trim($flow);

        if ('' === $normalizedEmail || '' === $normalizedFlow) {
            throw new \InvalidArgumentException('email_and_flow_required');
        }

        $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = time() + max(30, $ttlSeconds);

        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($challenge));
        $cacheItem->set([
            'email' => $normalizedEmail,
            'flow' => $normalizedFlow,
            'expires_at' => $expiresAt,
        ]);
        $cacheItem->expiresAt((new \DateTimeImmutable())->setTimestamp($expiresAt));
        $this->cachePool->save($cacheItem);

        return $challenge;
    }

    public function consumeChallenge(string $email, string $challenge, string $flow = 'login'): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedChallenge = trim($challenge);
        $normalizedFlow = trim($flow);

        if ('' === $normalizedEmail || '' === $normalizedChallenge || '' === $normalizedFlow) {
            return false;
        }

        $cacheKey = $this->buildCacheKey($normalizedChallenge);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            return false;
        }

        /** @var mixed $entry */
        $entry = $cacheItem->get();
        $this->cachePool->deleteItem($cacheKey);

        if (!is_array($entry)) {
            return false;
        }

        return ($entry['email'] ?? null) === $normalizedEmail
            && ($entry['flow'] ?? null) === $normalizedFlow
            && is_int($entry['expires_at'] ?? null)
            && $entry['expires_at'] > time();
    }

    private function buildCacheKey(string $challenge): string
    {
        return 'auth.passkey_challenge.'.hash('sha256', $challenge);
    }
}
