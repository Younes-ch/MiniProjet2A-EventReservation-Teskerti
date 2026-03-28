<?php

namespace App\Reservation;

use App\Entity\Reservation;

final class ReservationApiView
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Reservation $reservation): array
    {
        $event = $reservation->getEvent();

        return [
            'id' => $reservation->getId(),
            'reservation_id' => $reservation->getReservationId(),
            'attendee_name' => $reservation->getAttendeeName(),
            'attendee_email' => $reservation->getAttendeeEmail(),
            'attendee_phone' => $reservation->getAttendeePhone(),
            'status' => $reservation->getStatus(),
            'created_at' => $reservation->getCreatedAt()->format(DATE_ATOM),
            'event' => [
                'id' => $event?->getId(),
                'slug' => $event?->getSlug(),
                'title' => $event?->getTitle(),
                'location' => $event?->getLocation(),
                'city' => $event?->getCity(),
                'starts_at' => $event?->getStartsAt()->format(DATE_ATOM),
            ],
        ];
    }

    /**
     * @param list<Reservation> $reservations
     * @return list<array<string, mixed>>
     */
    public function toList(array $reservations): array
    {
        return array_map(fn (Reservation $reservation): array => $this->toArray($reservation), $reservations);
    }
}