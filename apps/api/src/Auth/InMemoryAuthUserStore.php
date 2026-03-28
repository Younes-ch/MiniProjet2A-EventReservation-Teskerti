<?php

namespace App\Auth;

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
     * @param list<AuthUser> $users
     */
    public function __construct(private readonly array $users)
    {
    }

    /**
     * @return AuthUser|null
     */
    public function findByEmail(string $email): ?array
    {
        $needle = strtolower(trim($email));

        foreach ($this->users as $user) {
            if (strtolower($user['email']) === $needle) {
                return $user;
            }
        }

        return null;
    }
}
