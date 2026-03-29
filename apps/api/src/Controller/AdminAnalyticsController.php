<?php

namespace App\Controller;

use App\Auth\InMemoryPasskeyPolicyStore;
use App\Auth\JwtTokenService;
use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AdminAnalyticsController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly InMemoryPasskeyPolicyStore $passkeyPolicyStore,
        private readonly JwtTokenService $jwtTokenService,
    ) {
    }

    #[Route('/api/admin/analytics/overview', name: 'api_admin_analytics_overview', methods: ['GET'])]
    public function overview(Request $request): JsonResponse
    {
        $claims = $this->requireAdminClaims($request);
        if ($claims instanceof JsonResponse) {
            return $claims;
        }

        $rows = $this->eventRepository->findAnalyticsRows();

        $revenueEstimate = 0.0;
        $eventCards = [];

        foreach ($rows as $row) {
            $seatsTotal = (int) ($row['seats_total'] ?? 0);
            $seatsAvailable = (int) ($row['seats_available'] ?? 0);
            $priceAmount = (float) ($row['price_amount'] ?? 0.0);
            $soldSeats = max($seatsTotal - $seatsAvailable, 0);
            $occupancyRate = $seatsTotal > 0 ? ($soldSeats / $seatsTotal) * 100 : 0.0;

            $revenueEstimate += $soldSeats * $priceAmount;

            $eventCards[] = [
                'event_id' => (int) ($row['event_id'] ?? 0),
                'event_slug' => (string) ($row['event_slug'] ?? ''),
                'event_title' => (string) ($row['event_title'] ?? ''),
                'reservations_total' => (int) ($row['reservations_total'] ?? 0),
                'confirmed_total' => (int) ($row['confirmed_total'] ?? 0),
                'cancelled_total' => (int) ($row['cancelled_total'] ?? 0),
                'waitlisted_total' => (int) ($row['waitlisted_total'] ?? 0),
                'seats_total' => $seatsTotal,
                'seats_available' => $seatsAvailable,
                'occupancy_rate' => round($occupancyRate, 2),
            ];
        }

        usort(
            $eventCards,
            static fn (array $left, array $right): int => ($right['reservations_total'] <=> $left['reservations_total'])
                ?: ($right['confirmed_total'] <=> $left['confirmed_total']),
        );

        return $this->json([
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'totals' => [
                'events' => $this->eventRepository->count([]),
                'reservations' => $this->reservationRepository->countAllReservations(),
                'confirmed' => $this->reservationRepository->countByStatus(Reservation::STATUS_CONFIRMED),
                'cancelled' => $this->reservationRepository->countByStatus(Reservation::STATUS_CANCELLED),
                'waitlisted' => $this->reservationRepository->countByStatus(Reservation::STATUS_WAITLISTED),
                'checked_in' => $this->reservationRepository->countCheckedInReservations(),
            ],
            'revenue_estimate' => round($revenueEstimate, 2),
            'top_events' => array_slice($eventCards, 0, 5),
        ]);
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

        if (
            $this->passkeyPolicyStore->isPasskeyRequiredAfterPassword($claims['sub'], $claims['roles'])
            && !(true === ($claims['passkey_verified'] ?? false))
        ) {
            return $this->json([
                'error' => 'passkey_verification_required',
            ], 403);
        }

        return $claims;
    }
}
