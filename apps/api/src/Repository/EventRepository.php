<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * @return list<Event>
     */
    public function findAllOrderedByStart(): array
    {
        /** @var list<Event> $events */
        $events = $this->createQueryBuilder('event')
            ->orderBy('event.startsAt', 'ASC')
            ->addOrderBy('event.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $events;
    }

    public function findOneBySlug(string $slug): ?Event
    {
        /** @var Event|null $event */
        $event = $this->findOneBy(['slug' => $slug]);

        return $event;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAnalyticsRows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->createQueryBuilder('event')
            ->leftJoin('event.reservations', 'reservation')
            ->select('event.id AS event_id')
            ->addSelect('event.slug AS event_slug')
            ->addSelect('event.title AS event_title')
            ->addSelect('event.seatsTotal AS seats_total')
            ->addSelect('event.seatsAvailable AS seats_available')
            ->addSelect('event.priceAmount AS price_amount')
            ->addSelect('COUNT(reservation.id) AS reservations_total')
            ->addSelect('SUM(CASE WHEN reservation.status = :confirmedStatus THEN 1 ELSE 0 END) AS confirmed_total')
            ->addSelect('SUM(CASE WHEN reservation.status = :cancelledStatus THEN 1 ELSE 0 END) AS cancelled_total')
            ->addSelect('SUM(CASE WHEN reservation.status = :waitlistedStatus THEN 1 ELSE 0 END) AS waitlisted_total')
            ->setParameter('confirmedStatus', Reservation::STATUS_CONFIRMED)
            ->setParameter('cancelledStatus', Reservation::STATUS_CANCELLED)
            ->setParameter('waitlistedStatus', Reservation::STATUS_WAITLISTED)
            ->groupBy('event.id')
            ->orderBy('event.startsAt', 'ASC')
            ->addOrderBy('event.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }
}
