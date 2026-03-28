<?php

namespace App\Auth;

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
    private array $credentialsByEmail = [];

    /**
     * @param list<PasskeyCredential> $seedCredentials
     */
    public function __construct(array $seedCredentials)
    {
        foreach ($seedCredentials as $credential) {
            $email = strtolower(trim((string) ($credential['email'] ?? '')));
            $credentialId = trim((string) ($credential['credential_id'] ?? ''));

            if ('' === $email || '' === $credentialId) {
                continue;
            }

            $this->credentialsByEmail[$email][] = [
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
        $credentials = $this->credentialsByEmail[$normalizedEmail] ?? [];

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

        foreach ($this->credentialsByEmail[$normalizedEmail] ?? [] as $credential) {
            if ($normalizedCredentialId === $credential['credential_id']) {
                return true;
            }
        }

        return false;
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

        $this->credentialsByEmail[$normalizedEmail][] = [
            'email' => $normalizedEmail,
            'credential_id' => $normalizedCredentialId,
            'label' => trim($label),
        ];
    }
}