<?php

namespace App\Controller;

use App\Auth\InMemoryPasskeyChallengeStore;
use App\Auth\InMemoryPasskeyCredentialStore;
use App\Auth\InMemoryRefreshTokenRevocationStore;
use App\Auth\InMemoryAuthUserStore;
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
        private readonly JwtTokenService $jwtTokenService,
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

        return $this->json($this->buildAuthResponse($user));
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

        return $this->json($this->buildAuthResponse($user));
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

        $challenge = $this->passkeyChallengeStore->issueChallenge($email, 'login');

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

        if ('' === $email || '' === $challenge || '' === $credentialId) {
            return $this->json([
                'error' => 'passkey_payload_invalid',
            ], 400);
        }

        $user = $this->userStore->findByEmail($email);
        if (null === $user) {
            return $this->json([
                'error' => 'passkey_not_registered',
            ], 404);
        }

        if (!$this->passkeyChallengeStore->consumeChallenge($email, $challenge, 'login')) {
            return $this->json([
                'error' => 'passkey_challenge_invalid',
            ], 401);
        }

        if (!$this->passkeyCredentialStore->hasCredential($email, $credentialId)) {
            return $this->json([
                'error' => 'passkey_credential_invalid',
            ], 401);
        }

        return $this->json($this->buildAuthResponse($user));
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
        $challenge = $this->passkeyChallengeStore->issueChallenge($email, 'register');

        return $this->json([
            'challenge' => $challenge,
            'timeout' => 60000,
            'rp_id' => 'localhost',
            'user' => [
                'email' => $claims['sub'],
                'display_name' => $claims['name'],
            ],
            'exclude_credentials' => $this->passkeyCredentialStore->findAllowedCredentialsByEmail($email),
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

        if ('' === $challenge || '' === $credentialId) {
            return $this->json([
                'error' => 'passkey_payload_invalid',
            ], 400);
        }

        if (!$this->passkeyChallengeStore->consumeChallenge($email, $challenge, 'register')) {
            return $this->json([
                'error' => 'passkey_challenge_invalid',
            ], 401);
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
        ]);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function buildAuthResponse(array $user): array
    {
        return [
            'access_token' => $this->jwtTokenService->createAccessToken($user),
            'refresh_token' => $this->jwtTokenService->createRefreshToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtTokenService->getAccessTokenTtl(),
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
     * @return array{sub: string, name: string, roles: list<string>, token_type: string, jti: string, iat: int, exp: int}|JsonResponse
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

        return $claims;
    }
}
