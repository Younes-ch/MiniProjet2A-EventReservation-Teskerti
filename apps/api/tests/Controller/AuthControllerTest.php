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

    public function testLogoutRevokesRefreshToken(): void
    {
        $client = static::createClient();
        $loginData = $this->loginAndGetPayload($client);

        $client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => $loginData['refresh_token'],
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $logoutData = $this->decodeResponse($client);
        $this->assertSame('logged_out', $logoutData['status'] ?? null);

        $client->request('POST', '/api/auth/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => $loginData['refresh_token'],
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(401);

        $refreshData = $this->decodeResponse($client);
        $this->assertSame('invalid_refresh_token', $refreshData['error'] ?? null);
    }

    public function testPasskeyOptionsReturnsChallengeForRegisteredCredential(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/passkey/options', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertIsString($data['challenge'] ?? null);
        $this->assertNotSame('', $data['challenge'] ?? '');
        $this->assertIsArray($data['allow_credentials'] ?? null);
        $this->assertGreaterThan(0, count($data['allow_credentials']));
        $this->assertSame('public-key', $data['allow_credentials'][0]['type'] ?? null);
    }

    public function testPasskeyVerifyReturnsTokenPairForValidCredential(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/passkey/options', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $optionsData = $this->decodeResponse($client);
        $challenge = (string) ($optionsData['challenge'] ?? '');
        $credentialId = (string) ($optionsData['allow_credentials'][0]['id'] ?? '');

        $client->request('POST', '/api/auth/passkey/verify', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'challenge' => $challenge,
            'credential_id' => $credentialId,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $verifyData = $this->decodeResponse($client);
        $this->assertSame('Bearer', $verifyData['token_type'] ?? null);
        $this->assertArrayHasKey('access_token', $verifyData);
        $this->assertArrayHasKey('refresh_token', $verifyData);
    }

    public function testPasskeyVerifyRejectsInvalidChallenge(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/passkey/verify', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'challenge' => 'invalid-challenge',
            'credential_id' => 'demo-passkey-alex-2026',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('passkey_challenge_invalid', $data['error'] ?? null);
    }

    public function testPasskeyRegisterOptionsRequiresAccessToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/passkey/register/options', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('missing_bearer_token', $data['error'] ?? null);
    }

    public function testPasskeyRegisterFlowAddsCredentialAndAllowsLogin(): void
    {
        $client = static::createClient();
        $loginData = $this->loginAndGetPayload($client);

        $uniqueCredentialId = 'demo-passkey-register-'.bin2hex(random_bytes(4));

        $client->request('POST', '/api/auth/passkey/register/options', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginData['access_token'],
        ], json_encode([
            'label' => 'Admin Laptop Passkey',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $registerOptions = $this->decodeResponse($client);
        $registerChallenge = (string) ($registerOptions['challenge'] ?? '');
        $this->assertNotSame('', $registerChallenge);

        $client->request('POST', '/api/auth/passkey/register/verify', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$loginData['access_token'],
        ], json_encode([
            'challenge' => $registerChallenge,
            'credential_id' => $uniqueCredentialId,
            'label' => 'Admin Laptop Passkey',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $registerVerifyData = $this->decodeResponse($client);
        $this->assertSame('passkey_registered', $registerVerifyData['status'] ?? null);
        $this->assertSame($uniqueCredentialId, $registerVerifyData['credential']['id'] ?? null);
        $this->assertGreaterThan(1, $registerVerifyData['total_credentials'] ?? 0);

        $client->request('POST', '/api/auth/passkey/options', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $loginOptions = $this->decodeResponse($client);
        $loginChallenge = (string) ($loginOptions['challenge'] ?? '');
        $this->assertNotSame('', $loginChallenge);

        $credentialFound = false;
        foreach (($loginOptions['allow_credentials'] ?? []) as $credential) {
            if (($credential['id'] ?? null) === $uniqueCredentialId) {
                $credentialFound = true;
                break;
            }
        }

        $this->assertTrue($credentialFound);

        $client->request('POST', '/api/auth/passkey/verify', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'challenge' => $loginChallenge,
            'credential_id' => $uniqueCredentialId,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $passkeyLogin = $this->decodeResponse($client);
        $this->assertArrayHasKey('access_token', $passkeyLogin);
        $this->assertArrayHasKey('refresh_token', $passkeyLogin);
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
