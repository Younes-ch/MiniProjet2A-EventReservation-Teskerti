<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicEventsControllerTest extends WebTestCase
{
    use ResetsDatabaseWithSeedEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabaseWithSeedEvents();
    }

    public function testListEndpointReturnsEventsCollection(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertIsArray($data['items'] ?? null);
        $this->assertGreaterThanOrEqual(3, count($data['items']));
        $this->assertSame('midnight-resonance-2-0', $data['items'][0]['slug'] ?? null);
    }

    public function testDetailEndpointReturnsSingleEventBySlug(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events/ephemeral-visions-gallery');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertSame('ephemeral-visions-gallery', $data['slug'] ?? null);
        $this->assertSame('Ephemeral Visions Gallery', $data['title'] ?? null);
        $this->assertSame('Modern Art', $data['category'] ?? null);
    }

    public function testDetailEndpointReturnsNotFoundForUnknownSlug(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events/not-existing-event');

        $this->assertResponseStatusCodeSame(404);

        $data = $this->decodeResponse($client);
        $this->assertSame('event_not_found', $data['error'] ?? null);
    }

    public function testSeatMapEndpointReturnsEventSeatGrid(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/events/midnight-resonance-2-0/seats');

        $this->assertResponseIsSuccessful();
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertSame('midnight-resonance-2-0', $data['event_slug'] ?? null);
        $this->assertSame(300, $data['total_seats'] ?? null);
        $this->assertSame(82, $data['available_seats'] ?? null);
        $this->assertSame(12, $data['layout']['columns'] ?? null);
        $this->assertIsArray($data['items'] ?? null);
        $this->assertCount(300, $data['items']);
        $this->assertSame('A-01', $data['items'][0]['label'] ?? null);
        $this->assertSame('available', $data['items'][0]['status'] ?? null);
    }

    public function testSeatMapEndpointMarksReservedSeatsAsReserved(): void
    {
        $client = static::createClient();

        $this->createReservation($client, ['A-01', 'A-02']);

        $client->request('GET', '/api/events/midnight-resonance-2-0/seats');

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $items = $data['items'] ?? [];
        $statusByLabel = [];

        foreach ($items as $item) {
            $label = $item['label'] ?? null;
            $status = $item['status'] ?? null;

            if (is_string($label) && is_string($status)) {
                $statusByLabel[$label] = $status;
            }
        }

        $this->assertSame('reserved', $statusByLabel['A-01'] ?? null);
        $this->assertSame('reserved', $statusByLabel['A-02'] ?? null);
    }

    /**
     * @param list<string> $seatLabels
     */
    private function createReservation($client, array $seatLabels): void
    {
        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Seat Map User',
            'email' => 'seat-map@example.com',
            'phone' => '+212 600 000 001',
            'seat_labels' => $seatLabels,
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
