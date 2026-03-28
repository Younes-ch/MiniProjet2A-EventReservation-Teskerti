<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminEventsControllerTest extends WebTestCase
{
    use ResetsDatabaseWithSeedEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabaseWithSeedEvents();
    }

    public function testAdminListRequiresBearerToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/events');

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('missing_bearer_token', $data['error'] ?? null);
    }

    public function testAdminCanCreateEvent(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $client->request('POST', '/api/admin/events', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'title' => 'Urban Futures Forum',
            'summary' => 'Architecture and policy roundtables for future cities.',
            'category' => 'Conference',
            'location' => 'Atlas Convention Hall',
            'city' => 'Marrakech',
            'starts_at' => '2026-11-06T09:30:00+01:00',
            'price_amount' => 89.5,
            'currency' => 'USD',
            'seats_total' => 260,
            'seats_available' => 260,
            'visual_tone' => 'indigo',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);

        $data = $this->decodeResponse($client);
        $this->assertSame('urban-futures-forum', $data['slug'] ?? null);
        $this->assertSame('Urban Futures Forum', $data['title'] ?? null);
        $this->assertSame('Marrakech', $data['city'] ?? null);
    }

    public function testAdminCanUpdateEvent(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);
        $eventId = $this->fetchFirstAdminEventId($client, $accessToken);

        $client->request('PUT', '/api/admin/events/'.$eventId, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'title' => 'Midnight Resonance 2.0 - Updated',
            'summary' => 'Updated summary for admin edit workflow.',
            'category' => 'Electronic Fusion',
            'location' => 'The Warehouse District',
            'city' => 'Casablanca',
            'starts_at' => '2026-10-12T21:30:00+01:00',
            'price_amount' => 49,
            'currency' => 'USD',
            'seats_total' => 320,
            'seats_available' => 80,
            'visual_tone' => 'cyan',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertSame('Midnight Resonance 2.0 - Updated', $data['title'] ?? null);
        $this->assertSame('cyan', $data['visual_tone'] ?? null);
        $this->assertSame(320, $data['seats_total'] ?? null);
    }

    public function testAdminCanDeleteEvent(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $client->request('GET', '/api/admin/events', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $listData = $this->decodeResponse($client);
        $target = $listData['items'][0] ?? null;

        $this->assertIsArray($target);
        $this->assertArrayHasKey('id', $target);
        $this->assertArrayHasKey('slug', $target);

        $eventId = (int) $target['id'];
        $eventSlug = (string) $target['slug'];

        $client->request('DELETE', '/api/admin/events/'.$eventId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/events/'.$eventSlug);
        $this->assertResponseStatusCodeSame(404);
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

    private function fetchFirstAdminEventId($client, string $accessToken): int
    {
        $client->request('GET', '/api/admin/events', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $first = $data['items'][0] ?? null;

        $this->assertIsArray($first);

        return (int) ($first['id'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}