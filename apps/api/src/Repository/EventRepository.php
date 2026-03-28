<?php

namespace App\Repository;

use App\Entity\Event;
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
}