<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    private const EMAIL = 'alex@example.com';
    private const PASSWORD = 'Passw0rd!2026';

    public function testLoginReturnsTokenPairForValidCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertSame('Bearer', $data['token_type'] ?? null);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertSame(self::EMAIL, $data['user']['email'] ?? null);
        $this->assertContains('ROLE_ADMIN', $data['user']['roles'] ?? []);
        $this->assertGreaterThan(0, $data['expires_in'] ?? 0);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'password' => 'wrong-password',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('invalid_credentials', $data['error'] ?? null);
    }

    public function testRefreshReturnsRotatedTokenPair(): void
    {
        $client = static::createClient();
        $loginData = $this->loginAndGetPayload($client);

        $client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => $loginData['refresh_token'],
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $refreshData = $this->decodeResponse($client);
        $this->assertArrayHasKey('access_token', $refreshData);
        $this->assertArrayHasKey('refresh_token', $refreshData);
        $this->assertNotSame($loginData['access_token'], $refreshData['access_token']);
        $this->assertNotSame($loginData['refresh_token'], $refreshData['refresh_token']);
    }

    public function testMeReturnsProfileForValidAccessToken(): void
    {
        $client = static::createClient();
        $loginData = $this->loginAndGetPayload($client);

        $client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginData['access_token'],
        ]);

        $this->assertResponseIsSuccessful();

        $meData = $this->decodeResponse($client);
        $this->assertSame(self::EMAIL, $meData['email'] ?? null);
        $this->assertSame('Alex Rivera', $meData['display_name'] ?? null);
        $this->assertContains('ROLE_USER', $meData['roles'] ?? []);
    }

    public function testMeRejectsMissingBearerToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('missing_bearer_token', $data['error'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function loginAndGetPayload($client): array
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        return $this->decodeResponse($client);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
