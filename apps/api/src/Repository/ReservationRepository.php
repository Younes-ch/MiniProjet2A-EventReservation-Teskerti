<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findOneByReservationId(string $reservationId): ?Reservation
    {
        /** @var Reservation|null $reservation */
        $reservation = $this->findOneBy(['reservationId' => $reservationId]);

        return $reservation;
    }

    public function findOneByReservationIdAndQrCodeToken(string $reservationId, string $qrCodeToken): ?Reservation
    {
        /** @var Reservation|null $reservation */
        $reservation = $this->findOneBy([
            'reservationId' => $reservationId,
            'qrCodeToken' => $qrCodeToken,
        ]);

        return $reservation;
    }

    public function countAllReservations(): int
    {
        return (int) $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->where('reservation.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCheckedInReservations(): int
    {
        return (int) $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->where('reservation.checkedInAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWaitlistedForEvent(int $eventId): int
    {
        return (int) $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->where('IDENTITY(reservation.event) = :eventId')
            ->andWhere('reservation.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', Reservation::STATUS_WAITLISTED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function findReservedSeatLabelsForEvent(int $eventId): array
    {
        /** @var list<array{seatLabels?: mixed}> $rows */
        $rows = $this->createQueryBuilder('reservation')
            ->select('reservation.seatLabels')
            ->where('IDENTITY(reservation.event) = :eventId')
            ->andWhere('reservation.status = :status')
            ->setParameter('eventId', $eventId)
            ->setParameter('status', Reservation::STATUS_CONFIRMED)
            ->getQuery()
            ->getArrayResult();

        $seatLabels = [];

        foreach ($rows as $row) {
            $rowSeatLabels = $row['seatLabels'] ?? null;
            if (!is_array($rowSeatLabels)) {
                continue;
            }

            foreach ($rowSeatLabels as $seatLabel) {
                if (!is_string($seatLabel)) {
                    continue;
                }

                $normalized = strtoupper(trim($seatLabel));
                if ('' !== $normalized) {
                    $seatLabels[$normalized] = true;
                }
            }
        }

        return array_keys($seatLabels);
    }

    /**
     * @return array{items: list<Reservation>, total: int}
     */
    public function findRecentWithEventPage(
        int $page,
        int $perPage,
        ?string $statusFilter = null,
        ?string $eventSlugFilter = null,
        ?string $queryFilter = null,
    ): array {
        $queryBuilder = $this->createQueryBuilder('reservation')
            ->addSelect('event')
            ->join('reservation.event', 'event');

        if (null !== $statusFilter && '' !== $statusFilter) {
            $queryBuilder
                ->andWhere('reservation.status = :statusFilter')
                ->setParameter('statusFilter', $statusFilter);
        }

        if (null !== $eventSlugFilter && '' !== $eventSlugFilter) {
            $queryBuilder
                ->andWhere('event.slug = :eventSlugFilter')
                ->setParameter('eventSlugFilter', $eventSlugFilter);
        }

        if (null !== $queryFilter && '' !== $queryFilter) {
            $normalizedQuery = mb_strtolower($queryFilter);

            $queryBuilder
                ->andWhere('(
                    LOWER(reservation.reservationId) LIKE :searchQuery
                    OR LOWER(reservation.attendeeName) LIKE :searchQuery
                    OR LOWER(reservation.attendeeEmail) LIKE :searchQuery
                    OR LOWER(event.title) LIKE :searchQuery
                )')
                ->setParameter('searchQuery', '%'.$normalizedQuery.'%');
        }

        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder
            ->select('COUNT(reservation.id)')
            ->resetDQLPart('orderBy');

        $total = (int) $countQueryBuilder
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Reservation> $reservations */
        $reservations = $queryBuilder
            ->orderBy('reservation.createdAt', 'DESC')
            ->addOrderBy('reservation.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $reservations,
            'total' => $total,
        ];
    }
}
