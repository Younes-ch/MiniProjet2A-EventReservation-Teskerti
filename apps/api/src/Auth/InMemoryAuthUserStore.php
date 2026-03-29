<?php

namespace App\Auth;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @phpstan-type AuthUser array{
 *     email: string,
 *     display_name: string,
 *     password_hash: string,
 *     roles: list<string>
 * }
 */
final class InMemoryAuthUserStore
{
    /**
     * @var list<AuthUser>
     */
    private array $seedUsers = [];

    /**
     * @param list<AuthUser> $users
     */
    public function __construct(
        array $users,
        private readonly CacheItemPoolInterface $cachePool,
    )
    {
        foreach ($users as $user) {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $displayName = trim((string) ($user['display_name'] ?? ''));
            $passwordHash = (string) ($user['password_hash'] ?? '');
            $roles = $user['roles'] ?? [];

            if (
                '' === $email
                || '' === $displayName
                || '' === $passwordHash
                || !is_array($roles)
            ) {
                continue;
            }

            $normalizedRoles = array_values(array_filter(
                $roles,
                static fn (mixed $role): bool => is_string($role) && '' !== trim($role),
            ));

            if ([] === $normalizedRoles) {
                $normalizedRoles = ['ROLE_USER'];
            }

            $this->seedUsers[] = [
                'email' => $email,
                'display_name' => $displayName,
                'password_hash' => $passwordHash,
                'roles' => $normalizedRoles,
            ];
        }
    }

    /**
     * @return AuthUser|null
     */
    public function findByEmail(string $email): ?array
    {
        $needle = strtolower(trim($email));

        foreach ($this->loadUsers() as $user) {
            if (strtolower($user['email']) === $needle) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @param list<string> $roles
     */
    public function createUser(string $email, string $displayName, string $passwordHash, array $roles = ['ROLE_USER']): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $normalizedDisplayName = trim($displayName);

        if ('' === $normalizedEmail || '' === $normalizedDisplayName || '' === trim($passwordHash)) {
            return false;
        }

        if (null !== $this->findByEmail($normalizedEmail)) {
            return false;
        }

        $normalizedRoles = array_values(array_filter(
            $roles,
            static fn (mixed $role): bool => is_string($role) && '' !== trim($role),
        ));

        if ([] === $normalizedRoles) {
            $normalizedRoles = ['ROLE_USER'];
        }

        $users = $this->loadUsers();
        $users[] = [
            'email' => $normalizedEmail,
            'display_name' => $normalizedDisplayName,
            'password_hash' => $passwordHash,
            'roles' => $normalizedRoles,
        ];

        $this->saveUsers($users);

        return true;
    }

    /**
     * @return list<AuthUser>
     */
    private function loadUsers(): array
    {
        $cacheItem = $this->cachePool->getItem($this->cacheKey());

        if ($cacheItem->isHit()) {
            /** @var mixed $value */
            $value = $cacheItem->get();

            if (is_array($value)) {
                /** @var list<AuthUser> $users */
                $users = array_values(array_filter(
                    $value,
                    static fn (mixed $user): bool => is_array($user)
                        && is_string($user['email'] ?? null)
                        && is_string($user['display_name'] ?? null)
                        && is_string($user['password_hash'] ?? null)
                        && is_array($user['roles'] ?? null),
                ));

                return $users;
            }
        }

        $this->saveUsers($this->seedUsers);

        return $this->seedUsers;
    }

    /**
     * @param list<AuthUser> $users
     */
    private function saveUsers(array $users): void
    {
        $cacheItem = $this->cachePool->getItem($this->cacheKey());
        $cacheItem->set($users);
        $this->cachePool->save($cacheItem);
    }

    private function cacheKey(): string
    {
        return 'auth.users.v1';
    }
}
