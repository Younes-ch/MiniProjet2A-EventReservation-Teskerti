<?php

namespace App\Controller;

use App\Auth\JwtTokenService;
use App\Entity\Event;
use App\Event\EventApiView;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AdminEventsController extends AbstractController
{
    /**
     * @var list<string>
     */
    private const ALLOWED_VISUAL_TONES = ['indigo', 'cyan', 'amber'];

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventApiView $eventApiView,
        private readonly JwtTokenService $jwtTokenService,
    ) {
    }

    #[Route('/api/admin/events', name: 'api_admin_events_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $authorizationResult = $this->requireAdminClaims($request);
        if ($authorizationResult instanceof JsonResponse) {
            return $authorizationResult;
        }

        $events = $this->eventRepository->findAllOrderedByStart();

        return $this->json([
            'items' => $this->eventApiView->toList($events),
        ]);
    }

    #[Route('/api/admin/events', name: 'api_admin_events_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $authorizationResult = $this->requireAdminClaims($request);
        if ($authorizationResult instanceof JsonResponse) {
            return $authorizationResult;
        }

        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $event = new Event();
        $validationError = $this->applyPayloadToEvent($event, $payload);
        if (null !== $validationError) {
            return $this->json([
                'error' => $validationError,
            ], 400);
        }

        $event->setCreatedAt(new \DateTimeImmutable());
        $event->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->json($this->eventApiView->toArray($event), 201);
    }

    #[Route('/api/admin/events/{eventId<\d+>}', name: 'api_admin_events_update', methods: ['PUT'])]
    public function update(int $eventId, Request $request): JsonResponse
    {
        $authorizationResult = $this->requireAdminClaims($request);
        if ($authorizationResult instanceof JsonResponse) {
            return $authorizationResult;
        }

        $event = $this->eventRepository->find($eventId);
        if (null === $event) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $validationError = $this->applyPayloadToEvent($event, $payload);
        if (null !== $validationError) {
            return $this->json([
                'error' => $validationError,
            ], 400);
        }

        $event->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json($this->eventApiView->toArray($event));
    }

    #[Route('/api/admin/events/{eventId<\d+>}', name: 'api_admin_events_delete', methods: ['DELETE'])]
    public function delete(int $eventId, Request $request): JsonResponse
    {
        $authorizationResult = $this->requireAdminClaims($request);
        if ($authorizationResult instanceof JsonResponse) {
            return $authorizationResult;
        }

        $event = $this->eventRepository->find($eventId);
        if (null === $event) {
            return $this->json([
                'error' => 'event_not_found',
            ], 404);
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return new JsonResponse(null, 204);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function requireAdminClaims(Request $request): array|JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return $this->json([
                'error' => 'missing_bearer_token',
            ], 401);
        }

        $accessToken = trim(substr($header, 7));
        $claims = $this->jwtTokenService->parseAndValidate($accessToken, 'access');
        if (null === $claims) {
            return $this->json([
                'error' => 'invalid_access_token',
            ], 401);
        }

        if (!in_array('ROLE_ADMIN', $claims['roles'], true)) {
            return $this->json([
                'error' => 'insufficient_role',
            ], 403);
        }

        return $claims;
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

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPayloadToEvent(Event $event, array $payload): ?string
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $summary = trim((string) ($payload['summary'] ?? ''));
        $category = trim((string) ($payload['category'] ?? ''));
        $location = trim((string) ($payload['location'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        $startsAtRaw = trim((string) ($payload['starts_at'] ?? ''));
        $visualTone = trim((string) ($payload['visual_tone'] ?? 'indigo'));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? 'USD')));

        if ('' === $title || '' === $summary || '' === $category || '' === $location || '' === $city || '' === $startsAtRaw) {
            return 'event_payload_invalid';
        }

        $startsAt = $this->parseDate($startsAtRaw);
        if (null === $startsAt) {
            return 'event_payload_invalid';
        }

        $priceAmount = $payload['price_amount'] ?? null;
        if (!is_numeric($priceAmount) || (float) $priceAmount < 0) {
            return 'event_payload_invalid';
        }

        $seatsTotal = filter_var($payload['seats_total'] ?? null, FILTER_VALIDATE_INT);
        $seatsAvailable = filter_var($payload['seats_available'] ?? null, FILTER_VALIDATE_INT);
        if (false === $seatsTotal || false === $seatsAvailable || $seatsTotal < 1 || $seatsAvailable < 0 || $seatsAvailable > $seatsTotal) {
            return 'event_payload_invalid';
        }

        if (3 !== strlen($currency)) {
            return 'event_payload_invalid';
        }

        if (!in_array($visualTone, self::ALLOWED_VISUAL_TONES, true)) {
            return 'event_payload_invalid';
        }

        $slugRaw = trim((string) ($payload['slug'] ?? ''));
        $slug = $this->ensureUniqueSlug('' !== $slugRaw ? $slugRaw : $title, $event->getId());

        $event
            ->setSlug($slug)
            ->setTitle($title)
            ->setSummary($summary)
            ->setCategory($category)
            ->setLocation($location)
            ->setCity($city)
            ->setStartsAt($startsAt)
            ->setPriceAmount((float) $priceAmount)
            ->setCurrency($currency)
            ->setSeatsTotal((int) $seatsTotal)
            ->setSeatsAvailable((int) $seatsAvailable)
            ->setVisualTone($visualTone);

        return null;
    }

    private function ensureUniqueSlug(string $source, ?int $currentEventId): string
    {
        $base = $this->slugify($source);
        if ('' === $base) {
            $base = 'event';
        }

        $candidate = $base;
        $suffix = 2;

        while (true) {
            $existing = $this->eventRepository->findOneBySlug($candidate);
            if (null === $existing || $existing->getId() === $currentEventId) {
                return $candidate;
            }

            $candidate = $base.'-'.$suffix;
            ++$suffix;
        }
    }

    private function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}