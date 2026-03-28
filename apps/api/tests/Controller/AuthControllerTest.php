<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    private const EMAIL = 'alex@example.com';
    private const PASSWORD = 'Passw0rd!2026';

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        static::createClient();

        $cachePool = static::getContainer()->get('cache.app');
        if (is_object($cachePool) && method_exists($cachePool, 'clear')) {
            $cachePool->clear();
        }

        self::ensureKernelShutdown();
    }

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
            'client_data' => $this->buildClientData('webauthn.get', $challenge),
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
            'client_data' => $this->buildClientData('webauthn.get', 'invalid-challenge'),
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

        $this->registerPasskeyCredential(
            $client,
            $loginData['access_token'],
            $uniqueCredentialId,
            'Admin Laptop Passkey',
        );

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
            'client_data' => $this->buildClientData('webauthn.get', $loginChallenge),
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $passkeyLogin = $this->decodeResponse($client);
        $this->assertArrayHasKey('access_token', $passkeyLogin);
        $this->assertArrayHasKey('refresh_token', $passkeyLogin);
    }

    public function testPasskeyCredentialManagementAllowsListRenameAndRevoke(): void
    {
        $client = static::createClient();
        $loginData = $this->loginAndGetPayload($client);
        $accessToken = (string) ($loginData['access_token'] ?? '');

        $uniqueCredentialId = 'demo-passkey-manage-'.bin2hex(random_bytes(4));
        $this->registerPasskeyCredential($client, $accessToken, $uniqueCredentialId, 'Initial Label');

        $client->request('GET', '/api/auth/passkey/credentials', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $listData = $this->decodeResponse($client);
        $this->assertIsArray($listData['items'] ?? null);

        $hasRegistered = false;
        foreach (($listData['items'] ?? []) as $credential) {
            if (($credential['id'] ?? null) === $uniqueCredentialId) {
                $hasRegistered = true;
                break;
            }
        }

        $this->assertTrue($hasRegistered);

        $client->request('PATCH', '/api/auth/passkey/credentials/'.$uniqueCredentialId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'label' => 'Renamed Label',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $renameData = $this->decodeResponse($client);
        $this->assertSame('Renamed Label', $renameData['label'] ?? null);

        $client->request('DELETE', '/api/auth/passkey/credentials/'.$uniqueCredentialId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $deleteData = $this->decodeResponse($client);
        $this->assertSame('passkey_credential_revoked', $deleteData['status'] ?? null);

        $client->request('GET', '/api/auth/passkey/credentials', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $afterDeleteData = $this->decodeResponse($client);
        $stillPresent = false;
        foreach (($afterDeleteData['items'] ?? []) as $credential) {
            if (($credential['id'] ?? null) === $uniqueCredentialId) {
                $stillPresent = true;
                break;
            }
        }

        $this->assertFalse($stillPresent);
    }

    public function testAdminCanTogglePasskeyPolicyAndPasswordLoginThenRequiresPasskey(): void
    {
        $client = static::createClient();
        $loginData = $this->loginAndGetPayload($client);
        $accessToken = (string) ($loginData['access_token'] ?? '');

        $client->request('PATCH', '/api/auth/passkey/policy', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'require_passkey_after_password_login' => true,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $policyData = $this->decodeResponse($client);
        $this->assertTrue($policyData['require_passkey_after_password_login'] ?? false);

        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $passwordLogin = $this->decodeResponse($client);
        $this->assertTrue($passwordLogin['requires_passkey'] ?? false);
        $this->assertArrayNotHasKey('access_token', $passwordLogin);

        $challenge = (string) ($passwordLogin['passkey_options']['challenge'] ?? '');
        $credentialId = (string) ($passwordLogin['passkey_options']['allow_credentials'][0]['id'] ?? '');

        $client->request('POST', '/api/auth/passkey/verify', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => self::EMAIL,
            'challenge' => $challenge,
            'credential_id' => $credentialId,
            'client_data' => $this->buildClientData('webauthn.get', $challenge),
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $passkeyLogin = $this->decodeResponse($client);
        $this->assertSame('passkey', $passkeyLogin['auth_method'] ?? null);
        $this->assertTrue($passkeyLogin['passkey_verified'] ?? false);
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

    private function registerPasskeyCredential($client, string $accessToken, string $credentialId, string $label): void
    {
        $client->request('POST', '/api/auth/passkey/register/options', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'label' => $label,
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $registerOptions = $this->decodeResponse($client);
        $registerChallenge = (string) ($registerOptions['challenge'] ?? '');
        $this->assertNotSame('', $registerChallenge);

        $client->request('POST', '/api/auth/passkey/register/verify', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'challenge' => $registerChallenge,
            'credential_id' => $credentialId,
            'label' => $label,
            'client_data' => $this->buildClientData('webauthn.create', $registerChallenge),
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array{type: string, challenge: string, origin: string}
     */
    private function buildClientData(string $type, string $challenge): array
    {
        return [
            'type' => $type,
            'challenge' => $challenge,
            'origin' => 'http://localhost:5173',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
