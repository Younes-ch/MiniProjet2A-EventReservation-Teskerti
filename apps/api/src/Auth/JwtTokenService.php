<?php

namespace App\Auth;

final class JwtTokenService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
    ) {
    }

    /**
     * @param array{email: string, display_name: string, roles: list<string>} $user
     */
    public function createAccessToken(array $user): string
    {
        return $this->encode([
            'sub' => $user['email'],
            'name' => $user['display_name'],
            'roles' => $user['roles'],
            'token_type' => 'access',
            'jti' => bin2hex(random_bytes(8)),
            'iat' => time(),
            'exp' => time() + $this->accessTokenTtl,
        ]);
    }

    /**
     * @param array{email: string, display_name: string, roles: list<string>} $user
     */
    public function createRefreshToken(array $user): string
    {
        return $this->encode([
            'sub' => $user['email'],
            'name' => $user['display_name'],
            'roles' => $user['roles'],
            'token_type' => 'refresh',
            'jti' => bin2hex(random_bytes(8)),
            'iat' => time(),
            'exp' => time() + $this->refreshTokenTtl,
        ]);
    }

    /**
     * @return array{sub: string, name: string, roles: list<string>, token_type: string, jti: string, iat: int, exp: int}|null
     */
    public function parseAndValidate(string $token, string $expectedType): ?array
    {
        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            return null;
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $parts;

        $headerJson = $this->base64UrlDecode($headerSegment);
        $payloadJson = $this->base64UrlDecode($payloadSegment);
        $signature = $this->base64UrlDecode($signatureSegment);

        if (null === $headerJson || null === $payloadJson || null === $signature) {
            return null;
        }

        try {
            /** @var mixed $header */
            $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);
            /** @var mixed $payload */
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($header) || !is_array($payload)) {
            return null;
        }

        if (('HS256' !== ($header['alg'] ?? null)) || ('JWT' !== ($header['typ'] ?? null))) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $headerSegment.'.'.$payloadSegment, $this->secret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $expiration = $payload['exp'] ?? null;
        if (!is_int($expiration) || $expiration <= time()) {
            return null;
        }

        if ($expectedType !== ($payload['token_type'] ?? null)) {
            return null;
        }

        if (
            !is_string($payload['sub'] ?? null)
            || !is_string($payload['name'] ?? null)
            || !is_array($payload['roles'] ?? null)
            || !is_string($payload['jti'] ?? null)
            || !is_int($payload['iat'] ?? null)
        ) {
            return null;
        }

        /** @var list<string> $roles */
        $roles = array_values(array_filter($payload['roles'], 'is_string'));

        return [
            'sub' => $payload['sub'],
            'name' => $payload['name'],
            'roles' => $roles,
            'token_type' => $payload['token_type'],
            'jti' => $payload['jti'],
            'iat' => $payload['iat'],
            'exp' => $expiration,
        ];
    }

    public function getAccessTokenTtl(): int
    {
        return $this->accessTokenTtl;
    }

    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerSegment = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadSegment = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerSegment.'.'.$payloadSegment, $this->secret, true);
        $signatureSegment = $this->base64UrlEncode($signature);

        return $headerSegment.'.'.$payloadSegment.'.'.$signatureSegment;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if (0 !== $padding) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if (false === $decoded) {
            return null;
        }

        return $decoded;
    }
}
