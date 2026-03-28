<?php

namespace App\Controller;

use App\Auth\JwtTokenService;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Reservation\ReservationApiView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AdminReservationsController extends AbstractController
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationApiView $reservationApiView,
        private readonly EntityManagerInterface $entityManager,
        private readonly JwtTokenService $jwtTokenService,
    ) {
    }

    #[Route('/api/admin/reservations', name: 'api_admin_reservations_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $authorizationResult = $this->requireAdminClaims($request);
        if ($authorizationResult instanceof JsonResponse) {
            return $authorizationResult;
        }

        $limitRaw = $request->query->get('limit', '30');
        $limit = filter_var($limitRaw, FILTER_VALIDATE_INT);
        if (false === $limit || $limit < 1 || $limit > 100) {
            $limit = 30;
        }

        $reservations = $this->reservationRepository->findRecentWithEvent($limit);

        return $this->json([
            'items' => $this->reservationApiView->toList($reservations),
        ]);
    }

    #[Route('/api/admin/reservations/{reservationId<\d+>}/status', name: 'api_admin_reservations_update_status', methods: ['PATCH'])]
    public function updateStatus(int $reservationId, Request $request): JsonResponse
    {
        $authorizationResult = $this->requireAdminClaims($request);
        if ($authorizationResult instanceof JsonResponse) {
            return $authorizationResult;
        }

        $reservation = $this->reservationRepository->find($reservationId);
        if (null === $reservation) {
            return $this->json([
                'error' => 'reservation_not_found',
            ], 404);
        }

        $payload = $this->decodeJsonPayload($request);
        if (null === $payload) {
            return $this->json([
                'error' => 'invalid_json_payload',
            ], 400);
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (!in_array($status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_CANCELLED], true)) {
            return $this->json([
                'error' => 'invalid_reservation_status',
            ], 400);
        }

        $reservation->setStatus($status);
        $this->entityManager->flush();

        return $this->json($this->reservationApiView->toArray($reservation));
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
}