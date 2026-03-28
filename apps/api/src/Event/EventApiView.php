<?php

namespace App\Event;

use App\Entity\Event;

final class EventApiView
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Event $event): array
    {
        return [
            'id' => $event->getId(),
            'slug' => $event->getSlug(),
            'title' => $event->getTitle(),
            'summary' => $event->getSummary(),
            'category' => $event->getCategory(),
            'location' => $event->getLocation(),
            'city' => $event->getCity(),
            'starts_at' => $event->getStartsAt()->format(DATE_ATOM),
            'price_amount' => $event->getPriceAmount(),
            'currency' => $event->getCurrency(),
            'seats_total' => $event->getSeatsTotal(),
            'seats_available' => $event->getSeatsAvailable(),
            'visual_tone' => $event->getVisualTone(),
        ];
    }

    /**
     * @param list<Event> $events
     * @return list<array<string, mixed>>
     */
    public function toList(array $events): array
    {
        return array_map(fn (Event $event): array => $this->toArray($event), $events);
    }
}
