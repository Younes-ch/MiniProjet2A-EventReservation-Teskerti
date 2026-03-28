<?php

namespace App\Auth;

use Psr\Cache\CacheItemPoolInterface;

final class InMemoryPasskeyChallengeStore
{
    public function __construct(private readonly CacheItemPoolInterface $cachePool)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function issueChallenge(string $email, string $flow = 'login', int $ttlSeconds = 120, array $context = []): string
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
            'context' => $context,
        ]);
        $cacheItem->expiresAt((new \DateTimeImmutable())->setTimestamp($expiresAt));
        $this->cachePool->save($cacheItem);

        return $challenge;
    }

    public function consumeChallenge(string $email, string $challenge, string $flow = 'login'): bool
    {
        return null !== $this->consumeChallengeWithContext($email, $challenge, $flow);
    }

    /**
     * @return array{email: string, flow: string, expires_at: int, context: array<string, mixed>}|null
     */
    public function consumeChallengeWithContext(string $email, string $challenge, string $flow = 'login'): ?array
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedChallenge = trim($challenge);
        $normalizedFlow = trim($flow);

        if ('' === $normalizedEmail || '' === $normalizedChallenge || '' === $normalizedFlow) {
            return null;
        }

        $cacheKey = $this->buildCacheKey($normalizedChallenge);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            return null;
        }

        /** @var mixed $entry */
        $entry = $cacheItem->get();
        $this->cachePool->deleteItem($cacheKey);

        if (!is_array($entry)) {
            return null;
        }

        $expiresAt = $entry['expires_at'] ?? null;
        $context = $entry['context'] ?? [];

        if (
            ($entry['email'] ?? null) !== $normalizedEmail
            || ($entry['flow'] ?? null) !== $normalizedFlow
            || !is_int($expiresAt)
            || $expiresAt <= time()
            || !is_array($context)
        ) {
            return null;
        }

        return [
            'email' => $normalizedEmail,
            'flow' => $normalizedFlow,
            'expires_at' => $expiresAt,
            'context' => $context,
        ];
    }

    private function buildCacheKey(string $challenge): string
    {
        return 'auth.passkey_challenge.'.hash('sha256', $challenge);
    }
}
