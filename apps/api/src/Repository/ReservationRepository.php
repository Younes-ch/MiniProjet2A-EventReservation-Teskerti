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
