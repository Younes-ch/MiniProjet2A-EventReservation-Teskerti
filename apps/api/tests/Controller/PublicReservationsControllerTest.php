<?php

namespace App\Tests\Controller;

use App\Tests\Support\ResetsDatabaseWithSeedEvents;
use Doctrine\ORM\EntityManagerInterface;
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
            'seat_labels' => ['A-03', 'A-04'],
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseFormatSame('json');

        $data = $this->decodeResponse($client);

        $this->assertMatchesRegularExpression('/^RSV-[A-F0-9]{4}-[A-F0-9]{4}$/', (string) ($data['reservation_id'] ?? ''));
        $this->assertSame('Yassine Builder', $data['attendee_name'] ?? null);
        $this->assertSame('midnight-resonance-2-0', $data['event_slug'] ?? null);
        $this->assertSame('Midnight Resonance 2.0', $data['event_title'] ?? null);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{12}-[a-f0-9]{20}$/', (string) ($data['qr_code_token'] ?? ''));
        $this->assertStringContainsString('/api/reservations/', (string) ($data['ticket_download_url'] ?? ''));
        $this->assertSame(['A-03', 'A-04'], $data['seat_labels'] ?? null);
    }

    public function testCreateReservationAssignsFallbackSeatWhenSelectionIsMissing(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Auto Seat User',
            'email' => 'auto-seat@example.com',
            'phone' => '+212 600 000 100',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);

        $data = $this->decodeResponse($client);
        $seatLabels = $data['seat_labels'] ?? null;

        $this->assertIsArray($seatLabels);
        $this->assertCount(1, $seatLabels);
        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{2}$/', (string) ($seatLabels[0] ?? ''));
    }

    public function testCreateReservationRejectsInvalidSeatLabels(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Invalid Seat User',
            'email' => 'invalid-seat@example.com',
            'phone' => '+212 600 000 200',
            'seat_labels' => ['INVALID-SEAT'],
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(400);

        $data = $this->decodeResponse($client);
        $this->assertSame('seat_selection_invalid', $data['error'] ?? null);
    }

    public function testCreateReservationRejectsAlreadyReservedSeatSelection(): void
    {
        $client = static::createClient();

        $requestPayload = [
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Seat Owner',
            'email' => 'seat-owner@example.com',
            'phone' => '+212 600 000 300',
            'seat_labels' => ['A-01'],
        ];

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestPayload, JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            ...$requestPayload,
            'email' => 'seat-owner-2@example.com',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(409);

        $data = $this->decodeResponse($client);
        $this->assertSame('seats_unavailable', $data['error'] ?? null);
        $this->assertSame(['A-01'], $data['seats'] ?? null);
    }

    public function testDownloadTicketPdfReturnsPdfForValidReservationToken(): void
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
        $reservationPayload = $this->decodeResponse($client);

        $reservationId = (string) ($reservationPayload['reservation_id'] ?? '');
        $qrCodeToken = (string) ($reservationPayload['qr_code_token'] ?? '');

        $client->request('GET', sprintf('/api/reservations/%s/ticket.pdf?token=%s', $reservationId, $qrCodeToken));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/pdf');

        $contentDisposition = (string) $client->getResponse()->headers->get('content-disposition', '');
        $this->assertStringContainsString('ticket-'.$reservationId.'.pdf', $contentDisposition);

        $pdfContent = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfContent);
    }

    public function testDownloadTicketPdfRejectsMissingToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/reservations/RSV-0000-0000/ticket.pdf');

        $this->assertResponseStatusCodeSame(400);

        $data = $this->decodeResponse($client);
        $this->assertSame('ticket_token_required', $data['error'] ?? null);
    }

    public function testDownloadTicketPdfReturnsNotFoundForInvalidToken(): void
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
        $reservationPayload = $this->decodeResponse($client);

        $reservationId = (string) ($reservationPayload['reservation_id'] ?? '');

        $client->request('GET', sprintf('/api/reservations/%s/ticket.pdf?token=INVALID-TOKEN', $reservationId));

        $this->assertResponseStatusCodeSame(404);

        $data = $this->decodeResponse($client);
        $this->assertSame('ticket_not_found', $data['error'] ?? null);
    }

    public function testJoinWaitlistCreatesWaitlistedReservationWhenEventIsSoldOut(): void
    {
        $client = static::createClient();
        $this->markEventAsSoldOut('ephemeral-visions-gallery');

        $client->request('POST', '/api/reservations/waitlist', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'ephemeral-visions-gallery',
            'full_name' => 'Waitlist User',
            'email' => 'waitlist@example.com',
            'phone' => '+212 611 111 111',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $data = $this->decodeResponse($client);

        $this->assertSame('waitlisted', $data['status'] ?? null);
        $this->assertSame([], $data['seat_labels'] ?? null);
        $this->assertSame('', $data['ticket_download_url'] ?? null);
        $this->assertSame(1, $data['waitlist_position'] ?? null);
    }

    public function testDownloadCalendarInviteReturnsIcsForConfirmedReservation(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/reservations', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'event_slug' => 'midnight-resonance-2-0',
            'full_name' => 'Calendar User',
            'email' => 'calendar@example.com',
            'phone' => '+212 622 222 222',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $reservationPayload = $this->decodeResponse($client);

        $reservationId = (string) ($reservationPayload['reservation_id'] ?? '');
        $qrCodeToken = (string) ($reservationPayload['qr_code_token'] ?? '');

        $client->request('GET', sprintf('/api/reservations/%s/calendar.ics?token=%s', $reservationId, $qrCodeToken));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/calendar; charset=utf-8');

        $icsContent = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('BEGIN:VEVENT', $icsContent);
        $this->assertStringContainsString('SUMMARY:', $icsContent);
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
}
