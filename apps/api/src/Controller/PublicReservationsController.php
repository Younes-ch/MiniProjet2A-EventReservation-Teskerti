<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PublicReservationsController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly EntityManagerInterface $entityManager,
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

        $reservationId = $this->buildReservationId();

        $reservation = (new Reservation())
            ->setReservationId($reservationId)
            ->setAttendeeName($fullName)
            ->setAttendeeEmail($email)
            ->setAttendeePhone($phone)
            ->setStatus(Reservation::STATUS_CONFIRMED)
            ->setEvent($event)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

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
        ], 201);
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
}
