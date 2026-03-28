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
     * @return list<Reservation>
     */
    public function findRecentWithEvent(int $limit = 20): array
    {
        /** @var list<Reservation> $reservations */
        $reservations = $this->createQueryBuilder('reservation')
            ->addSelect('event')
            ->join('reservation.event', 'event')
            ->orderBy('reservation.createdAt', 'DESC')
            ->addOrderBy('reservation.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $reservations;
    }
}
