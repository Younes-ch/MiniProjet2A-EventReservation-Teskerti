<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\UniqueConstraint(name: 'uniq_reservations_reservation_id', columns: ['reservation_id'])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'reservation_id', length: 32)]
    private string $reservationId = '';

    #[ORM\Column(name: 'attendee_name', length: 180)]
    private string $attendeeName = '';

    #[ORM\Column(name: 'attendee_email', length: 180)]
    private string $attendeeEmail = '';

    #[ORM\Column(name: 'attendee_phone', length: 80)]
    private string $attendeePhone = '';

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservationId(): string
    {
        return $this->reservationId;
    }

    public function setReservationId(string $reservationId): self
    {
        $this->reservationId = $reservationId;

        return $this;
    }

    public function getAttendeeName(): string
    {
        return $this->attendeeName;
    }

    public function setAttendeeName(string $attendeeName): self
    {
        $this->attendeeName = $attendeeName;

        return $this;
    }

    public function getAttendeeEmail(): string
    {
        return $this->attendeeEmail;
    }

    public function setAttendeeEmail(string $attendeeEmail): self
    {
        $this->attendeeEmail = $attendeeEmail;

        return $this;
    }

    public function getAttendeePhone(): string
    {
        return $this->attendeePhone;
    }

    public function setAttendeePhone(string $attendeePhone): self
    {
        $this->attendeePhone = $attendeePhone;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
