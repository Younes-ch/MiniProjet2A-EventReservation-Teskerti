<?php

namespace App\Controller;

use App\Event\EventApiView;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Reservation\SeatMapBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PublicEventsController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventApiView $eventApiView,
        private readonly ReservationRepository $reservationRepository,
        private readonly SeatMapBuilder $seatMapBuilder,
    )
    {
    }

    #[Route('/api/events', name: 'api_public_events_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $events = $this->eventRepository->findAllOrderedByStart();

        return $this->json([
            'items' => $this->eventApiView->toList($events),
        ]);
    }

    #[Route('/api/events/{slug}', name: 'api_public_events_detail', methods: ['GET'])]
    public function detail(string $slug): JsonResponse
    {
        $event = $this->eventRepository->findOneBySlug($slug);

        if (null === $event) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        return $this->json($this->eventApiView->toArray($event));
    }

    #[Route('/api/events/{slug}/seats', name: 'api_public_events_seat_map', methods: ['GET'])]
    public function seatMap(string $slug): JsonResponse
    {
        $event = $this->eventRepository->findOneBySlug($slug);

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

        return $this->json([
            'event_slug' => $event->getSlug(),
            'total_seats' => $event->getSeatsTotal(),
            'available_seats' => $event->getSeatsAvailable(),
            'layout' => $seatMap['layout'],
            'items' => $seatMap['items'],
        ]);
    }
}
