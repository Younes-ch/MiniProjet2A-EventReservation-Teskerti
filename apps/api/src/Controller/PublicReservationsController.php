<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Reservation\ReservationNotificationService;
use App\Reservation\SeatMapBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicReservationsController extends AbstractController
{
    private const MAX_SEAT_SELECTION = 4;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly SeatMapBuilder $seatMapBuilder,
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationNotificationService $notificationService,
    ) {
    }

    #[Route('/api/reservations', name: 'api_public_reservations_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $eventSlug = trim((string) ($payload['event_slug'] ?? ''));
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $waitlistIfFull = filter_var($payload['waitlist_if_full'] ?? false, FILTER_VALIDATE_BOOL);

        if ('' === $eventSlug || '' === $fullName || '' === $email || '' === $phone) {
            return $this->json([
                'error' => 'reservation_fields_required',
            ], 400);
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || null === $phoneDigits || strlen($phoneDigits) < 10) {
            return $this->json([
                'error' => 'invalid_reservation_payload',
            ], 400);
        }

        $event = $this->eventRepository->findOneBySlug($eventSlug);
        if (null === $event) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        $eventId = $event->getId();
        if (null === $eventId) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        $reservedSeatLabels = $this->reservationRepository->findReservedSeatLabelsForEvent($eventId);
        $seatMap = $this->seatMapBuilder->buildSeatMap($event->getSeatsTotal(), $reservedSeatLabels);

        $validSeatSet = [];
        $availableSeatSet = [];

        foreach ($seatMap['items'] as $item) {
            $label = $item['label'];
            $status = $item['status'];

            $validSeatSet[$label] = true;
            if ('available' === $status) {
                $availableSeatSet[$label] = true;
            }
        }

        $hasSeatSelection = array_key_exists('seat_labels', $payload);
        $selectedSeatLabels = [];

        if ($hasSeatSelection) {
            $normalizedSeatLabels = $this->normalizeSeatLabels($payload['seat_labels'] ?? null);
            if (null === $normalizedSeatLabels) {
                return $this->json([
                    'error' => 'seat_selection_invalid',
                ], 400);
            }

            if ([] === $normalizedSeatLabels) {
                return $this->json([
                    'error' => 'seat_selection_required',
                ], 400);
            }

            if (count($normalizedSeatLabels) > self::MAX_SEAT_SELECTION) {
                return $this->json([
                    'error' => 'seat_selection_too_large',
                ], 400);
            }

            $selectedSeatLabels = $normalizedSeatLabels;
        } else {
            $selectedSeatLabels = array_slice(array_keys($availableSeatSet), 0, 1);
        }

        if ([] === $selectedSeatLabels) {
            if ($waitlistIfFull) {
                return $this->createWaitlistedReservation($event, $fullName, $email, $phone);
            }

            return $this->json([
                'error' => 'seats_unavailable',
                'seats' => [],
            ], 409);
        }

        $invalidSeatLabels = [];
        $unavailableSeatLabels = [];

        foreach ($selectedSeatLabels as $seatLabel) {
            if (!isset($validSeatSet[$seatLabel])) {
                $invalidSeatLabels[] = $seatLabel;
                continue;
            }

            if (!isset($availableSeatSet[$seatLabel])) {
                $unavailableSeatLabels[] = $seatLabel;
            }
        }

        if ([] !== $invalidSeatLabels) {
            return $this->json([
                'error' => 'seat_selection_invalid',
                'seats' => $invalidSeatLabels,
            ], 400);
        }

        if ([] !== $unavailableSeatLabels || count($selectedSeatLabels) > $event->getSeatsAvailable()) {
            if ($waitlistIfFull) {
                return $this->createWaitlistedReservation($event, $fullName, $email, $phone);
            }

            return $this->json([
                'error' => 'seats_unavailable',
                'seats' => [] !== $unavailableSeatLabels ? $unavailableSeatLabels : $selectedSeatLabels,
            ], 409);
        }

        $reservationId = $this->buildReservationId();
        $qrCodeToken = $this->buildQrCodeToken($reservationId);

        $reservation = (new Reservation())
            ->setReservationId($reservationId)
            ->setQrCodeToken($qrCodeToken)
            ->setAttendeeName($fullName)
            ->setAttendeeEmail($email)
            ->setAttendeePhone($phone)
            ->setStatus(Reservation::STATUS_CONFIRMED)
            ->setSeatLabels($selectedSeatLabels)
            ->setEvent($event)
            ->setCreatedAt(new \DateTimeImmutable());

        $event->setSeatsAvailable(max($event->getSeatsAvailable() - count($selectedSeatLabels), 0));

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $ticketDownloadPath = sprintf('/api/reservations/%s/ticket.pdf?token=%s', $reservationId, $qrCodeToken);
        $calendarDownloadPath = sprintf('/api/reservations/%s/calendar.ics?token=%s', $reservationId, $qrCodeToken);

        $this->notificationService->sendConfirmedReservationEmail(
            $reservation,
            $this->toAbsoluteUrl($request, $ticketDownloadPath),
            $this->toAbsoluteUrl($request, $calendarDownloadPath),
        );

        $startsAt = $event->getStartsAt();
        $eventDate = $startsAt->format('F j, Y');
        $eventTime = $startsAt->format('H:i');

        return $this->json([
            'reservation_id' => $reservationId,
            'attendee_name' => $fullName,
            'attendee_email' => $email,
            'attendee_phone' => $phone,
            'event_slug' => $event->getSlug(),
            'event_title' => $event->getTitle(),
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'event_location' => $event->getLocation().', '.$event->getCity(),
            'status' => Reservation::STATUS_CONFIRMED,
            'seat_labels' => $selectedSeatLabels,
            'qr_code_token' => $qrCodeToken,
            'ticket_download_url' => $ticketDownloadPath,
            'calendar_download_url' => $calendarDownloadPath,
            'waitlist_position' => null,
        ], 201);
    }

    #[Route('/api/reservations/waitlist', name: 'api_public_reservations_waitlist', methods: ['POST'])]
    public function joinWaitlist(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $eventSlug = trim((string) ($payload['event_slug'] ?? ''));
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));

        if ('' === $eventSlug || '' === $fullName || '' === $email || '' === $phone) {
            return $this->json([
                'error' => 'reservation_fields_required',
            ], 400);
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || null === $phoneDigits || strlen($phoneDigits) < 10) {
            return $this->json([
                'error' => 'invalid_reservation_payload',
            ], 400);
        }

        $event = $this->eventRepository->findOneBySlug($eventSlug);
        if (null === $event) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        if ($event->getSeatsAvailable() > 0) {
            return $this->json([
                'error' => 'event_not_sold_out',
            ], 409);
        }

        return $this->createWaitlistedReservation($event, $fullName, $email, $phone);
    }

    #[Route('/api/reservations/{reservationId}/ticket.pdf', name: 'api_public_reservations_ticket_pdf', methods: ['GET'])]
    public function downloadTicketPdf(string $reservationId, Request $request): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        if ('' === $token) {
            return $this->json([
                'error' => 'ticket_token_required',
            ], 400);
        }

        $reservation = $this->reservationRepository->findOneByReservationIdAndQrCodeToken($reservationId, $token);
        if (null === $reservation) {
            return $this->json([
                'error' => 'ticket_not_found',
            ], 404);
        }

        if (Reservation::STATUS_CONFIRMED !== $reservation->getStatus()) {
            return $this->json([
                'error' => 'reservation_not_confirmed',
            ], 409);
        }

        $pdf = $this->buildTicketPdf($reservation);

        return new Response(
            $pdf,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="ticket-%s.pdf"', $reservation->getReservationId()),
            ],
        );
    }

    #[Route('/api/reservations/{reservationId}/calendar.ics', name: 'api_public_reservations_calendar_ics', methods: ['GET'])]
    public function downloadCalendarInvite(string $reservationId, Request $request): Response
    {
        $token = trim((string) $request->query->get('token', ''));
        if ('' === $token) {
            return $this->json([
                'error' => 'ticket_token_required',
            ], 400);
        }

        $reservation = $this->reservationRepository->findOneByReservationIdAndQrCodeToken($reservationId, $token);
        if (null === $reservation) {
            return $this->json([
                'error' => 'ticket_not_found',
            ], 404);
        }

        if (Reservation::STATUS_CONFIRMED !== $reservation->getStatus()) {
            return $this->json([
                'error' => 'reservation_not_confirmed',
            ], 409);
        }

        $ics = $this->buildCalendarInvite($reservation);

        return new Response(
            $ics,
            200,
            [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="ticket-%s.ics"', $reservation->getReservationId()),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(Request $request): ?array
    {
        try {
            /** @var mixed $data */
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    private function buildReservationId(): string
    {
        do {
            $stamp = strtoupper(dechex(time()));
            $entropy = strtoupper(bin2hex(random_bytes(2)));
            $candidate = sprintf('RSV-%s-%s', substr($stamp, -4), $entropy);
            $existing = $this->reservationRepository->findOneByReservationId($candidate);
        } while (null !== $existing);

        return $candidate;
    }

    private function buildQrCodeToken(string $reservationId): string
    {
        return strtoupper(bin2hex(random_bytes(6))).'-'.substr(hash('sha256', $reservationId), 0, 20);
    }

    /**
     * @return list<string>|null
     */
    private function normalizeSeatLabels(mixed $seatLabels): ?array
    {
        if (!is_array($seatLabels)) {
            return null;
        }

        $normalized = [];

        foreach ($seatLabels as $seatLabel) {
            if (!is_string($seatLabel)) {
                return null;
            }

            $value = strtoupper(trim($seatLabel));
            if ('' === $value) {
                return null;
            }

            $normalized[] = $value;
        }

        if (count($normalized) !== count(array_unique($normalized))) {
            return null;
        }

        return $normalized;
    }

    private function buildTicketPdf(Reservation $reservation): string
    {
        $event = $reservation->getEvent();
        $seatLabels = $reservation->getSeatLabels();

        $lines = [
            'Ticket Ref: '.$reservation->getReservationId(),
            'Reservation ID: '.$reservation->getReservationId(),
            'QR Token: '.$reservation->getQrCodeToken(),
            'Attendee: '.$reservation->getAttendeeName(),
            'Email: '.$reservation->getAttendeeEmail(),
            'Seats: '.([] === $seatLabels ? 'N/A' : implode(', ', $seatLabels)),
            'Event: '.($event?->getTitle() ?? 'N/A'),
            'Date: '.($event?->getStartsAt()->format('Y-m-d H:i') ?? 'N/A'),
            'Location: '.(($event?->getLocation() ?? '').' '.($event?->getCity() ?? '')),
            'Status: Confirmed',
            'Powered by Tiskerti x EventFlow',
        ];

        return $this->renderBrandedPdf($lines);
    }

    /**
     * @param list<string> $lines
     */
    private function renderBrandedPdf(array $lines): string
    {
        $contentOps = [
            'q',
            '0.08 0.16 0.35 rg',
            '40 774 515 36 re f',
            'Q',
            'BT',
            '/F1 14 Tf',
            '1 1 1 rg',
            '50 788 Td',
            '(Tiskerti x EventFlow Digital Ticket) Tj',
            'ET',
            'BT',
            '/F1 11 Tf',
            '0.1 0.14 0.22 rg',
            '50 750 Td',
        ];

        $lineCount = count($lines);
        foreach ($lines as $index => $line) {
            $escapedLine = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $contentOps[] = sprintf('(%s) Tj', $escapedLine);

            if ($index < $lineCount - 1) {
                $contentOps[] = '0 -16 Td';
            }
        }

        $contentOps[] = 'ET';

        $contentStream = implode("\n", $contentOps)."\n";

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => "<< /Length ".strlen($contentStream)." >>\nstream\n".$contentStream."endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        for ($i = 1; $i <= 5; ++$i) {
            $offsets[$i] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $i, $objects[$i]);
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= 5; ++$i) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function createWaitlistedReservation(
        Event $event,
        string $fullName,
        string $email,
        string $phone,
    ): JsonResponse {
        $eventId = $event->getId();
        if (null === $eventId) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        $reservationId = $this->buildReservationId();
        $qrCodeToken = $this->buildQrCodeToken($reservationId);
        $waitlistPosition = $this->reservationRepository->countWaitlistedForEvent($eventId) + 1;

        $reservation = (new Reservation())
            ->setReservationId($reservationId)
            ->setQrCodeToken($qrCodeToken)
            ->setAttendeeName($fullName)
            ->setAttendeeEmail($email)
            ->setAttendeePhone($phone)
            ->setStatus(Reservation::STATUS_WAITLISTED)
            ->setSeatLabels([])
            ->setEvent($event)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->notificationService->sendWaitlistEmail($reservation, $waitlistPosition);

        $startsAt = $event->getStartsAt();

        return $this->json([
            'reservation_id' => $reservationId,
            'attendee_name' => $fullName,
            'attendee_email' => $email,
            'attendee_phone' => $phone,
            'event_slug' => $event->getSlug(),
            'event_title' => $event->getTitle(),
            'event_date' => $startsAt->format('F j, Y'),
            'event_time' => $startsAt->format('H:i'),
            'event_location' => $event->getLocation().', '.$event->getCity(),
            'status' => Reservation::STATUS_WAITLISTED,
            'seat_labels' => [],
            'qr_code_token' => $qrCodeToken,
            'ticket_download_url' => '',
            'calendar_download_url' => '',
            'waitlist_position' => $waitlistPosition,
        ], 201);
    }

    private function buildCalendarInvite(Reservation $reservation): string
    {
        $event = $reservation->getEvent();
        $eventStart = $event?->getStartsAt() ?? new \DateTimeImmutable();
        $eventEnd = $eventStart->modify('+2 hours');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tiskerti//Reservations//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.strtolower($reservation->getReservationId()).'@tiskerti.local',
            'DTSTAMP:'.gmdate('Ymd\\THis\\Z'),
            'DTSTART:'.$eventStart->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z'),
            'DTEND:'.$eventEnd->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z'),
            'SUMMARY:'.$this->escapeIcsText($event?->getTitle() ?? 'Tiskerti Event'),
            'DESCRIPTION:'.$this->escapeIcsText('Reservation '.$reservation->getReservationId().' for '.$reservation->getAttendeeName()),
            'LOCATION:'.$this->escapeIcsText(trim(($event?->getLocation() ?? '').', '.($event?->getCity() ?? ''))),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private function escapeIcsText(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', ''],
            $value,
        );
    }

    private function toAbsoluteUrl(Request $request, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').$path;
    }
}
