<?php

namespace App\Controller;

use App\Auth\InMemoryAuthUserStore;
use App\Auth\InMemoryPasskeyChallengeStore;
use App\Auth\InMemoryPasskeyCredentialStore;
use App\Auth\InMemoryPasskeyPolicyStore;
use App\Auth\InMemoryRefreshTokenRevocationStore;
use App\Auth\JwtTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    public function __construct(
        private readonly InMemoryAuthUserStore $userStore,
        private readonly InMemoryPasskeyCredentialStore $passkeyCredentialStore,
        private readonly InMemoryPasskeyChallengeStore $passkeyChallengeStore,
        private readonly InMemoryRefreshTokenRevocationStore $refreshTokenRevocationStore,
        private readonly InMemoryPasskeyPolicyStore $passkeyPolicyStore,
        private readonly JwtTokenService $jwtTokenService,
        /** @var list<string> */
        private readonly array $passkeyAllowedOrigins,
    ) {
    }

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');

        if ('' === $email || '' === $password) {
            return $this->json([
                'error' => 'email_and_password_required',
            ], 400);
        }

        $user = $this->userStore->findByEmail($email);
        if (null === $user || !password_verify($password, $user['password_hash'])) {
            return $this->json([
                'error' => 'invalid_credentials',
            ], 401);
        }

        $requiresPasskey = $this->passkeyPolicyStore->isPasskeyRequiredAfterPassword($email, $user['roles']);
        if ($requiresPasskey) {
            $allowCredentials = $this->passkeyCredentialStore->findAllowedCredentialsByEmail($email);
            if ([] === $allowCredentials) {
                return $this->json([
                    'error' => 'passkey_required_but_not_registered',
                ], 403);
            }

            $challenge = $this->passkeyChallengeStore->issueChallenge(
                $email,
                'login',
                120,
                [
                    'allowed_credential_ids' => $this->extractCredentialIds($allowCredentials),
                ],
            );

            return $this->json([
                'requires_passkey' => true,
                'user' => [
                    'email' => $user['email'],
                    'display_name' => $user['display_name'],
                    'roles' => $user['roles'],
                ],
                'passkey_options' => [
                    'challenge' => $challenge,
                    'timeout' => 60000,
                    'rp_id' => 'localhost',
                    'user_verification' => 'preferred',
                    'allow_credentials' => $allowCredentials,
                ],
            ]);
        }

        return $this->json($this->buildAuthResponse($user, 'password', false));
    }

    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
        if ('' === $refreshToken) {
            return $this->json([
                'error' => 'refresh_token_required',
            ], 400);
        }

        $tokenPayload = $this->jwtTokenService->parseAndValidate($refreshToken, 'refresh');
        if (null === $tokenPayload) {
            return $this->json([
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        if ($this->refreshTokenRevocationStore->isRevoked($tokenPayload['jti'])) {
            return $this->json([
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        $user = $this->userStore->findByEmail($tokenPayload['sub']);
        if (null === $user) {
            return $this->json([
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        $authMethod = (string) ($tokenPayload['auth_method'] ?? 'password');
        $passkeyVerified = true === ($tokenPayload['passkey_verified'] ?? false);

        if (
            $this->passkeyPolicyStore->isPasskeyRequiredAfterPassword($user['email'], $user['roles'])
            && !$passkeyVerified
        ) {
            return $this->json([
                'error' => 'passkey_verification_required',
            ], 403);
        }

        return $this->json($this->buildAuthResponse($user, $authMethod, $passkeyVerified));
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
        if ('' === $refreshToken) {
            return $this->json([
                'error' => 'refresh_token_required',
            ], 400);
        }

        $tokenPayload = $this->jwtTokenService->parseAndValidate($refreshToken, 'refresh');
        if (null === $tokenPayload) {
            return $this->json([
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        $this->refreshTokenRevocationStore->revoke($tokenPayload['jti'], $tokenPayload['exp']);

        return $this->json([
            'status' => 'logged_out',
        ]);
    }

    #[Route('/api/auth/passkey/options', name: 'api_auth_passkey_options', methods: ['POST'])]
    public function passkeyOptions(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ('' === $email) {
            return $this->json([
                'error' => 'email_required',
            ], 400);
        }

        $user = $this->userStore->findByEmail($email);
        if (null === $user) {
            return $this->json([
                'error' => 'passkey_not_registered',
            ], 404);
        }

        $allowCredentials = $this->passkeyCredentialStore->findAllowedCredentialsByEmail($email);
        if ([] === $allowCredentials) {
            return $this->json([
                'error' => 'passkey_not_registered',
            ], 404);
        }

        $challenge = $this->passkeyChallengeStore->issueChallenge(
            $email,
            'login',
            120,
            [
                'allowed_credential_ids' => $this->extractCredentialIds($allowCredentials),
            ],
        );

        return $this->json([
            'challenge' => $challenge,
            'timeout' => 60000,
            'rp_id' => 'localhost',
            'user_verification' => 'preferred',
            'allow_credentials' => $allowCredentials,
        ]);
    }

    #[Route('/api/auth/passkey/verify', name: 'api_auth_passkey_verify', methods: ['POST'])]
    public function passkeyVerify(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $challenge = trim((string) ($payload['challenge'] ?? ''));
        $credentialId = trim((string) ($payload['credential_id'] ?? ''));
        $clientData = $this->extractPasskeyClientData($payload, 'webauthn.get');

        if ('' === $email || '' === $challenge || '' === $credentialId || null === $clientData) {
            return $this->json([
                'error' => 'passkey_payload_invalid',
            ], 400);
        }

        if ($clientData['challenge'] !== $challenge) {
            return $this->json([
                'error' => 'passkey_payload_invalid',
            ], 400);
        }

        if (!$this->isAllowedPasskeyOrigin($clientData['origin'])) {
            return $this->json([
                'error' => 'passkey_origin_invalid',
            ], 401);
        }

        $user = $this->userStore->findByEmail($email);
        if (null === $user) {
            return $this->json([
                'error' => 'passkey_not_registered',
            ], 404);
        }

        $challengeEntry = $this->passkeyChallengeStore->consumeChallengeWithContext($email, $challenge, 'login');
        if (null === $challengeEntry) {
            return $this->json([
                'error' => 'passkey_challenge_invalid',
            ], 401);
        }

        $allowedCredentialIds = $challengeEntry['context']['allowed_credential_ids'] ?? [];
        if (
            is_array($allowedCredentialIds)
            && [] !== $allowedCredentialIds
            && !in_array($credentialId, $allowedCredentialIds, true)
        ) {
            return $this->json([
                'error' => 'passkey_credential_invalid',
            ], 401);
        }

        if (!$this->passkeyCredentialStore->hasCredential($email, $credentialId)) {
            return $this->json([
                'error' => 'passkey_credential_invalid',
            ], 401);
        }

        return $this->json($this->buildAuthResponse($user, 'passkey', true));
    }

    #[Route('/api/auth/passkey/register/options', name: 'api_auth_passkey_register_options', methods: ['POST'])]
    public function passkeyRegisterOptions(Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        $payload = $this->decodeOptionalJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $email = $claims['sub'];
        $label = trim((string) ($payload['label'] ?? ''));
        $excludeCredentials = $this->passkeyCredentialStore->findAllowedCredentialsByEmail($email);

        $challenge = $this->passkeyChallengeStore->issueChallenge(
            $email,
            'register',
            120,
            [
                'exclude_credential_ids' => $this->extractCredentialIds($excludeCredentials),
            ],
        );

        return $this->json([
            'challenge' => $challenge,
            'timeout' => 60000,
            'rp_id' => 'localhost',
            'user' => [
                'email' => $claims['sub'],
                'display_name' => $claims['name'],
            ],
            'exclude_credentials' => $excludeCredentials,
            'label' => $label,
        ]);
    }

    #[Route('/api/auth/passkey/register/verify', name: 'api_auth_passkey_register_verify', methods: ['POST'])]
    public function passkeyRegisterVerify(Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $email = $claims['sub'];
        $challenge = trim((string) ($payload['challenge'] ?? ''));
        $credentialId = trim((string) ($payload['credential_id'] ?? ''));
        $label = trim((string) ($payload['label'] ?? ''));
        $clientData = $this->extractPasskeyClientData($payload, 'webauthn.create');

        if ('' === $challenge || '' === $credentialId || null === $clientData) {
            return $this->json([
                'error' => 'passkey_payload_invalid',
            ], 400);
        }

        if ($clientData['challenge'] !== $challenge) {
            return $this->json([
                'error' => 'passkey_payload_invalid',
            ], 400);
        }

        if (!$this->isAllowedPasskeyOrigin($clientData['origin'])) {
            return $this->json([
                'error' => 'passkey_origin_invalid',
            ], 401);
        }

        $challengeEntry = $this->passkeyChallengeStore->consumeChallengeWithContext($email, $challenge, 'register');
        if (null === $challengeEntry) {
            return $this->json([
                'error' => 'passkey_challenge_invalid',
            ], 401);
        }

        $excludedCredentialIds = $challengeEntry['context']['exclude_credential_ids'] ?? [];
        if (is_array($excludedCredentialIds) && in_array($credentialId, $excludedCredentialIds, true)) {
            return $this->json([
                'error' => 'passkey_credential_exists',
            ], 409);
        }

        if ($this->passkeyCredentialStore->hasCredential($email, $credentialId)) {
            return $this->json([
                'error' => 'passkey_credential_exists',
            ], 409);
        }

        $this->passkeyCredentialStore->addCredential($email, $credentialId, $label);
        $totalCredentials = count($this->passkeyCredentialStore->findAllowedCredentialsByEmail($email));

        return $this->json([
            'status' => 'passkey_registered',
            'credential' => [
                'id' => $credentialId,
                'type' => 'public-key',
                'label' => $label,
            ],
            'total_credentials' => $totalCredentials,
        ]);
    }

    #[Route('/api/auth/passkey/credentials', name: 'api_auth_passkey_credentials_list', methods: ['GET'])]
    public function passkeyCredentialsList(Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        return $this->json([
            'items' => $this->passkeyCredentialStore->listCredentialsByEmail($claims['sub']),
        ]);
    }

    #[Route('/api/auth/passkey/credentials/{credentialId}', name: 'api_auth_passkey_credentials_rename', methods: ['PATCH'])]
    public function passkeyCredentialsRename(string $credentialId, Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $label = trim((string) ($payload['label'] ?? ''));
        if ('' === $label || strlen($label) > 80) {
            return $this->json([
                'error' => 'passkey_label_invalid',
            ], 400);
        }

        if (!$this->passkeyCredentialStore->renameCredential($claims['sub'], $credentialId, $label)) {
            return $this->json([
                'error' => 'passkey_credential_not_found',
            ], 404);
        }

        foreach ($this->passkeyCredentialStore->listCredentialsByEmail($claims['sub']) as $credential) {
            if (($credential['id'] ?? null) === $credentialId) {
                return $this->json($credential);
            }
        }

        return $this->json([
            'error' => 'passkey_credential_not_found',
        ], 404);
    }

    #[Route('/api/auth/passkey/credentials/{credentialId}', name: 'api_auth_passkey_credentials_revoke', methods: ['DELETE'])]
    public function passkeyCredentialsRevoke(string $credentialId, Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        if (!$this->passkeyCredentialStore->revokeCredential($claims['sub'], $credentialId)) {
            return $this->json([
                'error' => 'passkey_credential_not_found',
            ], 404);
        }

        return $this->json([
            'status' => 'passkey_credential_revoked',
            'total_credentials' => count($this->passkeyCredentialStore->findAllowedCredentialsByEmail($claims['sub'])),
        ]);
    }

    #[Route('/api/auth/passkey/policy', name: 'api_auth_passkey_policy_get', methods: ['GET'])]
    public function passkeyPolicyGet(Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        if (!in_array('ROLE_ADMIN', $claims['roles'], true)) {
            return $this->json([
                'error' => 'insufficient_role',
            ], 403);
        }

        return $this->json([
            'require_passkey_after_password_login' => $this->passkeyPolicyStore->isPasskeyRequiredAfterPassword($claims['sub'], $claims['roles']),
        ]);
    }

    #[Route('/api/auth/passkey/policy', name: 'api_auth_passkey_policy_update', methods: ['PATCH'])]
    public function passkeyPolicyUpdate(Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        if (!in_array('ROLE_ADMIN', $claims['roles'], true)) {
            return $this->json([
                'error' => 'insufficient_role',
            ], 403);
        }

        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $required = $payload['require_passkey_after_password_login'] ?? null;
        if (!is_bool($required)) {
            return $this->json([
                'error' => 'passkey_policy_invalid',
            ], 400);
        }

        $this->passkeyPolicyStore->setPasskeyRequiredAfterPassword($claims['sub'], $claims['roles'], $required);

        return $this->json([
            'require_passkey_after_password_login' => $this->passkeyPolicyStore->isPasskeyRequiredAfterPassword($claims['sub'], $claims['roles']),
        ]);
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $claims = $this->extractAccessTokenClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        return $this->json([
            'email' => $claims['sub'],
            'display_name' => $claims['name'],
            'roles' => $claims['roles'],
            'auth_method' => $claims['auth_method'],
            'passkey_verified' => $claims['passkey_verified'],
            'passkey_required_after_password_login' => $this->passkeyPolicyStore->isPasskeyRequiredAfterPassword($claims['sub'], $claims['roles']),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function buildAuthResponse(array $user, string $authMethod, bool $passkeyVerified): array
    {
        return [
            'access_token' => $this->jwtTokenService->createAccessToken($user, $authMethod, $passkeyVerified),
            'refresh_token' => $this->jwtTokenService->createRefreshToken($user, $authMethod, $passkeyVerified),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtTokenService->getAccessTokenTtl(),
            'auth_method' => $authMethod,
            'passkey_verified' => $passkeyVerified,
            'user' => [
                'email' => $user['email'],
                'display_name' => $user['display_name'],
                'roles' => $user['roles'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(Request $request): ?array
    {
        try {
            /** @var mixed $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeOptionalJsonPayload(Request $request): ?array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [];
        }

        try {
            /** @var mixed $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
    * @return array{sub: string, name: string, roles: list<string>, auth_method: string, passkey_verified: bool, token_type: string, jti: string, iat: int, exp: int}|JsonResponse
     */
    private function extractAccessTokenClaims(Request $request): array|JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return $this->json([
                'error' => 'missing_bearer_token',
            ], 401);
        }

        $accessToken = trim(substr($header, 7));
        $claims = $this->jwtTokenService->parseAndValidate($accessToken, 'access');
        if (null === $claims) {
            return $this->json([
                'error' => 'invalid_access_token',
            ], 401);
        }

        return [
            'sub' => $claims['sub'],
            'name' => $claims['name'],
            'roles' => $claims['roles'],
            'auth_method' => $claims['auth_method'] ?? 'password',
            'passkey_verified' => true === ($claims['passkey_verified'] ?? false),
            'token_type' => $claims['token_type'],
            'jti' => $claims['jti'],
            'iat' => $claims['iat'],
            'exp' => $claims['exp'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{type: string, challenge: string, origin: string}|null
     */
    private function extractPasskeyClientData(array $payload, string $expectedType): ?array
    {
        $clientData = $payload['client_data'] ?? null;
        if (!is_array($clientData)) {
            return null;
        }

        $type = trim((string) ($clientData['type'] ?? ''));
        $challenge = trim((string) ($clientData['challenge'] ?? ''));
        $origin = trim((string) ($clientData['origin'] ?? ''));

        if ($expectedType !== $type || '' === $challenge || '' === $origin) {
            return null;
        }

        return [
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $origin,
        ];
    }

    private function isAllowedPasskeyOrigin(string $origin): bool
    {
        $normalizedOrigin = strtolower(trim($origin));

        foreach ($this->passkeyAllowedOrigins as $allowedOrigin) {
            if ($normalizedOrigin === strtolower(trim((string) $allowedOrigin))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{id: string, type: string}> $credentials
     * @return list<string>
     */
    private function extractCredentialIds(array $credentials): array
    {
        $credentialIds = [];

        foreach ($credentials as $credential) {
            $credentialId = trim((string) ($credential['id'] ?? ''));
            if ('' !== $credentialId) {
                $credentialIds[] = $credentialId;
            }
        }

        return $credentialIds;
    }
}
