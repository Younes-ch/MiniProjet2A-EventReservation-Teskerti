<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicReservationsControllerTest extends WebTestCase
{
    use ResetsDatabaseWithSeedEvents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDatabaseWithSeedEvents();
    }

    public function testCreateReservationReturnsConfirmationPayload(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Yassine Builder',
            'email' => 'yassine@example.com',
            'phone' => '+212 600 000 000',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertMatchesRegularExpression('/^RSV-[A-F0-9]{4}-[A-F0-9]{4}$/', (string) ($data['reservation_id'] ?? ''));
        $this->assertSame('Yassine Builder', $data['attendee_name'] ?? null);
        $this->assertSame('midnight-resonance-2-0', $data['event_slug'] ?? null);
        $this->assertSame('Midnight Resonance 2.0', $data['event_title'] ?? null);
    }

    public function testCreateReservationRejectsMissingFields(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Yassine Builder',
            'email' => 'yassine@example.com',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(400);

        $data = $this->decodeResponse($client);
        $this->assertSame('reservation_fields_required', $data['error'] ?? null);
    }

    public function testCreateReservationRejectsInvalidEmailAndPhone(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Yassine Builder',
            'email' => 'not-an-email',
            'phone' => '1234',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(400);

        $data = $this->decodeResponse($client);
        $this->assertSame('invalid_reservation_payload', $data['error'] ?? null);
    }

    public function testCreateReservationReturnsNotFoundForMissingEvent(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'missing-event',
            'full_name' => 'Yassine Builder',
            'email' => 'yassine@example.com',
            'phone' => '+212 600 000 000',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(404);

        $data = $this->decodeResponse($client);
        $this->assertSame('event_not_found', $data['error'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse($client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
