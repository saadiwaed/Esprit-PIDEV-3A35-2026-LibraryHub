<?php

namespace App\Entity;

use App\Enum\RegistrationStatus;
use App\Repository\EventRegistrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRegistrationRepository::class)]
#[ORM\Table(name: 'event_registrations')]
#[ORM\UniqueConstraint(name: 'unique_registration', columns: ['user_id', 'event_id'])]
class EventRegistration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'eventRegistrations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Event $event = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $registeredAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: RegistrationStatus::class)]
    private RegistrationStatus $status = RegistrationStatus::PENDING;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $attendedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->registeredAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function getRegisteredAt(): \DateTimeInterface
    {
        return $this->registeredAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): RegistrationStatus
    {
        return $this->status;
    }

    public function getAttendedAt(): ?\DateTimeInterface
    {
        return $this->attendedAt;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function setRegisteredAt(\DateTimeInterface $registeredAt): self
    {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setStatus(RegistrationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setAttendedAt(?\DateTimeInterface $attendedAt): self
    {
        $this->attendedAt = $attendedAt;

        return $this;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function isWaitlisted(): bool
    {
        return $this->status === RegistrationStatus::WAITLISTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === RegistrationStatus::CANCELLED;
    }

    public function hasAttended(): bool
    {
        return $this->status === RegistrationStatus::ATTENDED;
    }

    public function isNoShow(): bool
    {
        return $this->status === RegistrationStatus::NO_SHOW;
    }

    public function confirmAttendance(): self
    {
        $this->status = RegistrationStatus::ATTENDED;
        $this->attendedAt = new \DateTime();

        return $this;
    }

    public function markAsNoShow(): self
    {
        $this->status = RegistrationStatus::NO_SHOW;

        return $this;
    }

    public function cancel(): self
    {
        $this->status = RegistrationStatus::CANCELLED;

        return $this;
    }

    public function putOnWaitlist(): self
    {
        $this->status = RegistrationStatus::WAITLISTED;

        return $this;
    }
}
