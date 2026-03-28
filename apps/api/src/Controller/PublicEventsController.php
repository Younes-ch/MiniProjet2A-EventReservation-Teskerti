<?php

namespace App\Controller;

use App\Event\InMemoryEventCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PublicEventsController extends AbstractController
{
    public function __construct(private readonly InMemoryEventCatalog $eventCatalog)
    {
    }

    #[Route('/api/events', name: 'api_public_events_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json([
            'items' => $this->eventCatalog->list(),
        ]);
    }

    #[Route('/api/events/{slug}', name: 'api_public_events_detail', methods: ['GET'])]
    public function detail(string $slug): JsonResponse
    {
        $event = $this->eventCatalog->findBySlug($slug);

        if (null === $event) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        return $this->json($event);
    }
}