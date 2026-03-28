<?php

namespace App\Auth;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @phpstan-type PasskeyCredential array{
 *     email: string,
 *     credential_id: string,
 *     label?: string
 * }
 */
final class InMemoryPasskeyCredentialStore
{
    /**
     * @var array<string, list<PasskeyCredential>>
     */
    private array $seedCredentialsByEmail = [];

    /**
     * @param list<PasskeyCredential> $seedCredentials
     */
    public function __construct(
        array $seedCredentials,
        private readonly CacheItemPoolInterface $cachePool,
    )
    {
        foreach ($seedCredentials as $credential) {
            $email = strtolower(trim((string) ($credential['email'] ?? '')));
            $credentialId = trim((string) ($credential['credential_id'] ?? ''));

            if ('' === $email || '' === $credentialId) {
                continue;
            }

            $this->seedCredentialsByEmail[$email][] = [
                'email' => $email,
                'credential_id' => $credentialId,
                'label' => (string) ($credential['label'] ?? ''),
            ];
        }
    }

    /**
     * @return list<array{id: string, type: string}>
     */
    public function findAllowedCredentialsByEmail(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $credentials = $this->loadCredentialsByEmail($normalizedEmail);

        return array_map(
            static fn (array $credential): array => [
                'id' => (string) $credential['credential_id'],
                'type' => 'public-key',
            ],
            $credentials,
        );
    }

    public function hasCredential(string $email, string $credentialId): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedCredentialId = trim($credentialId);

        if ('' === $normalizedEmail || '' === $normalizedCredentialId) {
            return false;
        }

        foreach ($this->loadCredentialsByEmail($normalizedEmail) as $credential) {
            if ($normalizedCredentialId === $credential['credential_id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id: string, type: string, label: string}>
     */
    public function listCredentialsByEmail(string $email): array
    {
        $normalizedEmail = strtolower(trim($email));
        $credentials = $this->loadCredentialsByEmail($normalizedEmail);

        return array_values(array_map(
            static fn (array $credential): array => [
                'id' => (string) ($credential['credential_id'] ?? ''),
                'type' => 'public-key',
                'label' => (string) ($credential['label'] ?? ''),
            ],
            $credentials,
        ));
    }

    public function renameCredential(string $email, string $credentialId, string $label): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedCredentialId = trim($credentialId);
        $normalizedLabel = trim($label);

        if ('' === $normalizedEmail || '' === $normalizedCredentialId || '' === $normalizedLabel) {
            return false;
        }

        $credentials = $this->loadCredentialsByEmail($normalizedEmail);
        $updated = false;

        foreach ($credentials as $index => $credential) {
            if ($normalizedCredentialId === ($credential['credential_id'] ?? null)) {
                $credentials[$index]['label'] = $normalizedLabel;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            return false;
        }

        $this->saveCredentialsByEmail($normalizedEmail, $credentials);

        return true;
    }

    public function revokeCredential(string $email, string $credentialId): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedCredentialId = trim($credentialId);

        if ('' === $normalizedEmail || '' === $normalizedCredentialId) {
            return false;
        }

        $credentials = $this->loadCredentialsByEmail($normalizedEmail);

        $filtered = array_values(array_filter(
            $credentials,
            static fn (array $credential): bool => ($credential['credential_id'] ?? null) !== $normalizedCredentialId,
        ));

        if (count($filtered) === count($credentials)) {
            return false;
        }

        $this->saveCredentialsByEmail($normalizedEmail, $filtered);

        return true;
    }

    public function addCredential(string $email, string $credentialId, string $label = ''): void
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedCredentialId = trim($credentialId);

        if ('' === $normalizedEmail || '' === $normalizedCredentialId) {
            return;
        }

        if ($this->hasCredential($normalizedEmail, $normalizedCredentialId)) {
            return;
        }

        $credentials = $this->loadCredentialsByEmail($normalizedEmail);
        $credentials[] = [
            'email' => $normalizedEmail,
            'credential_id' => $normalizedCredentialId,
            'label' => trim($label),
        ];

        $this->saveCredentialsByEmail($normalizedEmail, $credentials);
    }

    /**
     * @return list<PasskeyCredential>
     */
    private function loadCredentialsByEmail(string $normalizedEmail): array
    {
        if ('' === $normalizedEmail) {
            return [];
        }

        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($normalizedEmail));

        if ($cacheItem->isHit()) {
            /** @var mixed $cachedCredentials */
            $cachedCredentials = $cacheItem->get();
            if (is_array($cachedCredentials)) {
                /** @var list<PasskeyCredential> $normalized */
                $normalized = array_values(array_filter(
                    $cachedCredentials,
                    static fn (mixed $credential): bool => is_array($credential)
                        && is_string($credential['email'] ?? null)
                        && is_string($credential['credential_id'] ?? null),
                ));

                return $normalized;
            }
        }

        $seedCredentials = $this->seedCredentialsByEmail[$normalizedEmail] ?? [];
        $this->saveCredentialsByEmail($normalizedEmail, $seedCredentials);

        return $seedCredentials;
    }

    /**
     * @param list<PasskeyCredential> $credentials
     */
    private function saveCredentialsByEmail(string $normalizedEmail, array $credentials): void
    {
        $cacheItem = $this->cachePool->getItem($this->buildCacheKey($normalizedEmail));
        $cacheItem->set($credentials);
        $this->cachePool->save($cacheItem);
    }

    private function buildCacheKey(string $normalizedEmail): string
    {
        return 'auth.passkey_credentials.'.hash('sha256', $normalizedEmail);
    }
}
