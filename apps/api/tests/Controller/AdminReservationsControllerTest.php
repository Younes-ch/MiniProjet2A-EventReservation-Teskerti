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

        $client->request('GET', '/api/admin/reservations?page=1&per_page=6', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertIsArray($data['items'] ?? null);
        $this->assertCount(2, $data['items']);
        $this->assertSame('confirmed', $data['items'][0]['status'] ?? null);
        $this->assertArrayHasKey('event', $data['items'][0] ?? []);
        $this->assertSame(1, $data['meta']['page'] ?? null);
        $this->assertSame(6, $data['meta']['per_page'] ?? null);
        $this->assertSame(2, $data['meta']['total_items'] ?? null);
        $this->assertSame(1, $data['meta']['total_pages'] ?? null);
        $this->assertSame('all', $data['meta']['status'] ?? null);
    }

    public function testAdminCanFilterReservationsByStatus(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $this->createReservation($client, 'midnight-resonance-2-0', 'Alya Four', 'alya4@example.com');

        $client->request('GET', '/api/admin/reservations?page=1&per_page=6', [], [], [
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

        $client->request('GET', '/api/admin/reservations?page=1&per_page=6&status=cancelled', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $filteredData = $this->decodeResponse($client);
        $this->assertCount(1, $filteredData['items']);
        $this->assertSame('cancelled', $filteredData['items'][0]['status'] ?? null);
        $this->assertSame('cancelled', $filteredData['meta']['status'] ?? null);
    }

    public function testAdminReservationsListRejectsInvalidStatusFilter(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $client->request('GET', '/api/admin/reservations?status=archived', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseStatusCodeSame(400);

        $data = $this->decodeResponse($client);
        $this->assertSame('invalid_reservation_status_filter', $data['error'] ?? null);
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
