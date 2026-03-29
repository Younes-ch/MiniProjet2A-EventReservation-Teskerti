<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminAnalyticsControllerTest extends WebTestCase
{
    use ResetsDatabaseWithSeedEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabaseWithSeedEvents();
    }

    public function testAdminAnalyticsRequiresBearerToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/admin/analytics/overview');

        $this->assertResponseStatusCodeSame(401);

        $data = $this->decodeResponse($client);
        $this->assertSame('missing_bearer_token', $data['error'] ?? null);
    }

    public function testAdminAnalyticsReturnsAggregateMetrics(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Analytics Confirmed',
            'email' => 'analytics-confirmed@example.com',
            'phone' => '+212 633 333 333',
            'seat_labels' => ['A-01'],
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);

        $this->markEventAsSoldOut('ephemeral-visions-gallery');

        $client->request('POST', '/api/reservations/waitlist', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'ephemeral-visions-gallery',
            'full_name' => 'Analytics Waitlist',
            'email' => 'analytics-waitlist@example.com',
            'phone' => '+212 644 444 444',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/admin/analytics/overview', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);

        $this->assertIsArray($data['totals'] ?? null);
        $this->assertGreaterThanOrEqual(3, (int) ($data['totals']['events'] ?? 0));
        $this->assertGreaterThanOrEqual(2, (int) ($data['totals']['reservations'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($data['totals']['confirmed'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($data['totals']['waitlisted'] ?? 0));
        $this->assertIsArray($data['top_events'] ?? null);
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

    private function markEventAsSoldOut(string $eventSlug): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $entityManager->getConnection()->executeStatement(
            'UPDATE events SET seats_available = 0 WHERE slug = :slug',
            [
                'slug' => $eventSlug,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
