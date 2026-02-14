<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Enum\EventStatus;
use App\Enum\EventTypes;
use Symfony\Component\Validator\Constraints as Assert;
use App\Enum\RegistrationStatus;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'events')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'La description doit contenir au moins {{ limit }} caractères')]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    #[Assert\GreaterThan('today', message: 'La date de début doit être future')]
    private ?\DateTimeInterface $startDateTime = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire')]
    #[Assert\GreaterThan(
        propertyPath: 'startDateTime',
        message: 'La date de fin doit être après la date de début'
    )]
    private ?\DateTimeInterface $endDateTime = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire')]
    private ?string $location = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'La capacité est obligatoire')]
    #[Assert\Positive(message: 'La capacité doit être un nombre positif')]
    #[Assert\LessThanOrEqual(value: 1000, message: 'La capacité ne peut pas dépasser 1000 personnes')]
    private ?int $capacity = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank(message: 'La date limite d\'inscription est obligatoire')]
    #[Assert\LessThan(
        propertyPath: 'startDateTime',
        message: 'La date limite d\'inscription doit être avant la date de début'
    )]
    private ?\DateTimeInterface $registrationDeadline = null;

    #[ORM\Column(type: 'string', length: 20, enumType: EventStatus::class)]
    private EventStatus $status = EventStatus::UPCOMING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdDate = null;


    #[ORM\ManyToMany(
        targetEntity: Club::class, 
        mappedBy: 'organizedEvents'
    )]
    private Collection $organizingClubs;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventRegistration::class)]
    private Collection $registrations;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    #[Vich\UploadableField(mapping: 'event_image', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    public function __construct()
    {
        $this->organizingClubs = new ArrayCollection();
        $this->createdDate = new \DateTime();
        $this->registrations = new ArrayCollection();
    }
#[ORM\Column(type: 'string', length: 50, enumType: EventTypes::class)]
    private EventTypes $type = EventTypes::READING; 


    public function getType(): EventTypes
    {
        return $this->type;
    }

    public function setType(EventTypes $type): self
    {
        $this->type = $type;
        return $this;
    }
    #[ORM\PrePersist]
    public function setCreatedDateValue(): void
    {
        if ($this->createdDate === null) {
            $this->createdDate = new \DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStartDateTime(): ?\DateTimeInterface
    {
        return $this->startDateTime;
    }

    public function setStartDateTime(\DateTimeInterface $startDateTime): self
    {
        $this->startDateTime = $startDateTime;
        return $this;
    }

    public function getEndDateTime(): ?\DateTimeInterface
    {
        return $this->endDateTime;
    }

    public function setEndDateTime(\DateTimeInterface $endDateTime): self
    {
        $this->endDateTime = $endDateTime;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getRegistrationDeadline(): ?\DateTimeInterface
    {
        return $this->registrationDeadline;
    }

    public function setRegistrationDeadline(\DateTimeInterface $registrationDeadline): self
    {
        $this->registrationDeadline = $registrationDeadline;
        return $this;
    }

    public function getStatus(): EventStatus
    {
        return $this->status;
    }

    public function setStatus(EventStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedDate(): ?\DateTimeInterface
    {
        return $this->createdDate;
    }

    public function setCreatedDate(\DateTimeInterface $createdDate): self
    {
        $this->createdDate = $createdDate;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, Club>
     */
    public function getOrganizingClubs(): Collection
    {
        return $this->organizingClubs;
    }

    public function addOrganizingClub(Club $club): self
    {
        if (!$this->organizingClubs->contains($club)) {
            $this->organizingClubs->add($club);
            $club->addOrganizedEvent($this);
        }
        return $this;
    }

    public function removeOrganizingClub(Club $club): self
    {
        if ($this->organizingClubs->removeElement($club)) {
            $club->removeOrganizedEvent($this);
        }
        return $this;
    }

    public function isUpcoming(): bool
    {
        return $this->startDateTime > new \DateTime();
    }

   
    public function isOngoing(): bool
    {
        $now = new \DateTime();
        return $this->startDateTime <= $now && $this->endDateTime >= $now;
    }

  
    public function isPast(): bool
    {
        return $this->endDateTime < new \DateTime();
    }


   
    public function getMainOrganizer(): ?Club
    {
        return $this->organizingClubs->first() ?: null;
    }

 
    public function isOrganizedByClub(Club $club): bool
    {
        return $this->organizingClubs->contains($club);
    }

    public function isCollaborative(): bool
    {
        return $this->organizingClubs->count() > 1;
    }

    public function getOrganizerCount(): int
    {
        return $this->organizingClubs->count();
    }

    public function getDurationInHours(): float
    {
        if (!$this->startDateTime || !$this->endDateTime) {
            return 0.0;
        }
        
        $interval = $this->startDateTime->diff($this->endDateTime);
        $hours = $interval->h + ($interval->days * 24);
        $hours += $interval->i / 60;
        
        return round($hours, 1);
    }

  
    public function isStartingSoon(): bool
    {
        $now = new \DateTime();
        $difference = $this->startDateTime->getTimestamp() - $now->getTimestamp();
        
        return $difference > 0 && $difference <= 86400; // 24h en secondes
    }

    public function getAllOrganizersMembers(): Collection
    {
        $allMembers = new ArrayCollection();
        
        foreach ($this->organizingClubs as $club) {
            foreach ($club->getMembers() as $member) {
                if (!$allMembers->contains($member)) {
                    $allMembers->add($member);
                }
            }
        }
        
        return $allMembers;
    }

    public function isUserInOrganizingClub(User $user): bool
    {
        foreach ($this->organizingClubs as $club) {
            if ($club->isMember($user)) {
                return true;
            }
        }
        return false;
    }


    public function isFull(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Événement';
    }
    public function getAvailableSpots(): int
{
    $confirmedRegistrations = $this->registrations->filter(
        fn(EventRegistration $reg) => $reg->getStatus() === RegistrationStatus::CONFIRMED
    )->count();
    
    return $this->capacity - $confirmedRegistrations;
}

public function isRegistrationOpen(): bool
{
    return $this->registrationDeadline > new \DateTime() 
        && $this->status === EventStatus::UPCOMING
        && $this->getAvailableSpots() > 0;
}

public function getWaitlistCount(): int
{
    return $this->registrations->filter(
        fn(EventRegistration $reg) => $reg->isWaitlisted()
    )->count();
}

public function setImageFile(?File $imageFile = null): void
{
    $this->imageFile = $imageFile;
    
    if ($imageFile) {
        $this->createdDate = new \DateTime(); // Use your existing createdDate field
    }
}

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }
}