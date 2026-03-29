<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testAdminCanFilterReservationsByEventSlug(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $this->createReservation($client, 'midnight-resonance-2-0', 'Alya Five', 'alya5@example.com');
        $this->createReservation($client, 'ephemeral-visions-gallery', 'Alya Six', 'alya6@example.com');

        $client->request('GET', '/api/admin/reservations?page=1&per_page=6&event_slug=ephemeral-visions-gallery', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertCount(1, $data['items']);
        $this->assertSame('ephemeral-visions-gallery', $data['items'][0]['event']['slug'] ?? null);
        $this->assertSame('ephemeral-visions-gallery', $data['meta']['event_slug'] ?? null);
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

    public function testAdminCanFilterWaitlistedReservations(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $this->markEventAsSoldOut('ephemeral-visions-gallery');

        $client->request('POST', '/api/reservations/waitlist', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'ephemeral-visions-gallery',
            'full_name' => 'Alya Waitlist',
            'email' => 'alya-waitlist@example.com',
            'phone' => '+212 600 100 100',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/admin/reservations?page=1&per_page=6&status=waitlisted', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertCount(1, $data['items']);
        $this->assertSame('waitlisted', $data['items'][0]['status'] ?? null);
        $this->assertSame('waitlisted', $data['meta']['status'] ?? null);
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

    public function testAdminCanCheckInReservationWithValidTicketToken(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $reservationPayload = $this->createReservation(
            $client,
            'midnight-resonance-2-0',
            'Alya Seven',
            'alya7@example.com',
        );

        $client->request('POST', '/api/admin/reservations/check-in', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'reservation_id' => $reservationPayload['reservation_id'] ?? '',
            'qr_code_token' => $reservationPayload['qr_code_token'] ?? '',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseIsSuccessful();

        $data = $this->decodeResponse($client);
        $this->assertSame('checked_in', $data['status'] ?? null);
        $this->assertSame($reservationPayload['reservation_id'] ?? null, $data['reservation']['reservation_id'] ?? null);
        $this->assertNotNull($data['reservation']['checked_in_at'] ?? null);
    }

    public function testAdminCheckInRejectsMissingPayloadFields(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $client->request('POST', '/api/admin/reservations/check-in', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'reservation_id' => '',
            'qr_code_token' => '',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(400);

        $data = $this->decodeResponse($client);
        $this->assertSame('checkin_payload_invalid', $data['error'] ?? null);
    }

    public function testAdminCheckInReturnsNotFoundForInvalidTicketToken(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $reservationPayload = $this->createReservation(
            $client,
            'midnight-resonance-2-0',
            'Alya Eight',
            'alya8@example.com',
        );

        $client->request('POST', '/api/admin/reservations/check-in', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], json_encode([
            'reservation_id' => $reservationPayload['reservation_id'] ?? '',
            'qr_code_token' => 'INVALID-TOKEN',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(404);

        $data = $this->decodeResponse($client);
        $this->assertSame('ticket_not_found', $data['error'] ?? null);
    }

    public function testAdminCheckInRejectsAlreadyCheckedInReservation(): void
    {
        $client = static::createClient();
        $accessToken = $this->loginAndGetAccessToken($client);

        $reservationPayload = $this->createReservation(
            $client,
            'midnight-resonance-2-0',
            'Alya Nine',
            'alya9@example.com',
        );

        $requestBody = json_encode([
            'reservation_id' => $reservationPayload['reservation_id'] ?? '',
            'qr_code_token' => $reservationPayload['qr_code_token'] ?? '',
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/admin/reservations/check-in', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], $requestBody);

        $this->assertResponseIsSuccessful();

        $client->request('POST', '/api/admin/reservations/check-in', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ], $requestBody);

        $this->assertResponseStatusCodeSame(409);

        $data = $this->decodeResponse($client);
        $this->assertSame('reservation_already_checked_in', $data['error'] ?? null);
        $this->assertNotNull($data['checked_in_at'] ?? null);
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

    /**
     * @return array<string, mixed>
     */
    private function createReservation($client, string $eventSlug, string $fullName, string $email): array
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

        return $this->decodeResponse($client);
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
