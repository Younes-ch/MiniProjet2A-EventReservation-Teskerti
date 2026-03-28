<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminReservationsControllerTest extends WebTestCase
{
    use ResetsDatabaseWithSeedEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabaseWithSeedEvents();
    }

    public function testAdminReservationsListRequiresBearerToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/reservations');

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('missing_bearer_token', $data['error'] ?? null);
    }

    public function testAdminCanListReservations(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $this->createReservation($client, 'midnight-resonance-2-0', 'Alya One', 'alya1@example.com');
        $this->createReservation($client, 'ephemeral-visions-gallery', 'Alya Two', 'alya2@example.com');

        $client->request('GET', '/api/admin/reservations?limit=20', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertIsArray($data['items'] ?? null);
        $this->assertCount(2, $data['items']);
        $this->assertSame('confirmed', $data['items'][0]['status'] ?? null);
        $this->assertArrayHasKey('event', $data['items'][0] ?? []);
    }

    public function testAdminCanUpdateReservationStatus(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $this->createReservation($client, 'midnight-resonance-2-0', 'Alya Three', 'alya3@example.com');

        $client->request('GET', '/api/admin/reservations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $listData = $this->decodeResponse($client);
        $targetId = (int) ($listData['items'][0]['id'] ?? 0);

        $client->request('PATCH', '/api/admin/reservations/'.$targetId.'/status', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'status' => 'cancelled',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $updatedData = $this->decodeResponse($client);
        $this->assertSame('cancelled', $updatedData['status'] ?? null);
    }

    private function loginAndGetAccessToken($client): string
    {
        $client->request('POST', '/api/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'alex@example.com',
            'password' => 'Passw0rd!2026',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);

        return (string) ($data['access_token'] ?? '');
    }

    private function createReservation($client, string $eventSlug, string $fullName, string $email): void
    {
        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => $eventSlug,
            'full_name' => $fullName,
            'email' => $email,
            'phone' => '+212 600 000 000',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}