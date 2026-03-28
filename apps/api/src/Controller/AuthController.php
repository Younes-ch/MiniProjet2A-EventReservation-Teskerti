<?php

namespace App\Controller;

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

        $user = $this->userStore->findByEmail($tokenPayload['sub']);
        if (null === $user) {
            return $this->json([
                'error' => 'invalid_refresh_token',
            ], 401);
        }

        return $this->json($this->buildAuthResponse($user));
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return $this->json([
                'error' => 'missing_bearer_token',
            ], 401);
        }

        $accessToken = trim(substr($header, 7));
        $tokenPayload = $this->jwtTokenService->parseAndValidate($accessToken, 'access');
        if (null === $tokenPayload) {
            return $this->json([
                'error' => 'invalid_access_token',
            ], 401);
        }

        return $this->json([
            'email' => $tokenPayload['sub'],
            'display_name' => $tokenPayload['name'],
            'roles' => $tokenPayload['roles'],
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
}
