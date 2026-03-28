<?php

namespace App\Controller;

use App\Event\EventApiView;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PublicEventsController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventApiView $eventApiView,
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
}
