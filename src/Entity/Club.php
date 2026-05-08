<?php

namespace App\Entity;

use App\Enum\ClubStatus;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\ClubRepository;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: ClubRepository::class)]
#[ORM\Table(name: 'clubs')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Club
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
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres',
        maxMessage: 'Le titre ne peut pas depasser {{ limit }} caracteres'
    )]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'La description doit contenir au moins {{ limit }} caracteres')]
    private string $description = '';

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'La categorie est obligatoire')]
    private string $category = '';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'foundedClubs')]
    #[ORM\JoinColumn(name: 'founder_id', referencedColumnName: 'id', nullable: true)]
    private ?User $founder = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'clubs')]
    #[ORM\JoinTable(name: 'club_members')]
    private Collection $members;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank(message: 'La date de reunion est obligatoire')]
    #[Assert\GreaterThan('today', message: 'La date de reunion doit ÃƒÂªtre future')]
    private \DateTimeInterface $meetingDate;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le lieu de reunion est obligatoire')]
    private string $meetingLocation = '';

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'La capacite est obligatoire')]
    #[Assert\Positive(message: 'La capacite doit ÃƒÂªtre un nombre positif')]
    #[Assert\LessThanOrEqual(value: 500, message: 'La capacite ne peut pas depasser 500 membres')]
    private int $capacity = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPrivate = false;

    #[ORM\Column(type: 'string', length: 20, enumType: ClubStatus::class)]
    private ClubStatus $status = ClubStatus::ACTIVE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdDate;

   #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    #[Vich\UploadableField(mapping: 'club_image', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    /** @var Collection<int, Event> */
    #[ORM\ManyToMany(
        targetEntity: Event::class, 
        inversedBy: 'organizingClubs',
        cascade: ['persist']
    )]
    #[ORM\JoinTable(name: 'club_organizes_events')]
    #[ORM\JoinColumn(name: 'club_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'event_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $organizedEvents;



    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->organizedEvents = new ArrayCollection();
        $this->createdDate = new \DateTime();
        $this->meetingDate = new \DateTime('+1 day');
    }

    #[ORM\PrePersist]
    public function setCreatedDateValue(): void
    {
        $this->createdDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getFounder(): ?User
    {
        return $this->founder;
    }

    public function setFounder(?User $founder): self
    {
        $this->founder = $founder;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): self
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
        }
        return $this;
    }

    public function removeMember(User $member): self
    {
        $this->members->removeElement($member);
        return $this;
    }

    public function getMeetingDate(): \DateTimeInterface
    {
        return $this->meetingDate;
    }

    public function setMeetingDate(\DateTimeInterface $meetingDate): self
    {
        $this->meetingDate = $meetingDate;
        return $this;
    }

    public function getMeetingLocation(): string
    {
        return $this->meetingLocation;
    }

    public function setMeetingLocation(string $meetingLocation): self
    {
        $this->meetingLocation = $meetingLocation;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): self
    {
        $this->isPrivate = $isPrivate;
        return $this;
    }

    public function getStatus(): ClubStatus
    {
        return $this->status;
    }

    public function setStatus(ClubStatus $status): self
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

    public function getCreatedDate(): \DateTimeInterface
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
     * @return Collection<int, Event>
     */
    public function getOrganizedEvents(): Collection
    {
        return $this->organizedEvents;
    }

    public function addOrganizedEvent(Event $event): self
    {
        if (!$this->organizedEvents->contains($event)) {
            $this->organizedEvents->add($event);
            $event->addOrganizingClub($this);
        }
        return $this;
    }

    public function removeOrganizedEvent(Event $event): self
    {
        if ($this->organizedEvents->removeElement($event)) {
            $event->removeOrganizingClub($this);
        }
        return $this;
    }

    public function getAvailableSpots(): int
    {
        return $this->capacity - $this->members->count();
    }

    public function isFull(): bool
    {
        return $this->members->count() >= $this->capacity;
    }

    public function isMember(User $user): bool
    {
        return $this->members->contains($user);
    }

    public function canJoin(): bool
    {
        return !$this->isFull() && $this->status === ClubStatus::ACTIVE;
    }

    public function activate(): self
    {
        $this->status = ClubStatus::ACTIVE;
        return $this;
    }

    public function deactivate(): self
    {
        $this->status = ClubStatus::INACTIVE;
        return $this;
    }

    public function pause(): self
    {
        $this->status = ClubStatus::PAUSED;
        return $this;
    }

    public function archive(): self
    {
        $this->status = ClubStatus::ARCHIVED;
        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getUpcomingEvents(): Collection
    {
        return $this->organizedEvents->filter(
            static fn (Event $event): bool => $event->getStartDateTime() > new \DateTime()
        );
    }

    /**
     * @return Collection<int, Event>
     */
    public function getPastEvents(): Collection
    {
        return $this->organizedEvents->filter(
            static fn (Event $event): bool => $event->getEndDateTime() < new \DateTime()
        );
    }

    /**
     * @return Collection<int, Event>
     */
    public function getOngoingEvents(): Collection
    {
        return $this->organizedEvents->filter(
            static fn (Event $event): bool => $event->isOngoing()
        );
    }

    public function isOrganizingEvent(Event $event): bool
    {
        return $this->organizedEvents->contains($event);
    }

    public function getEventCount(): int
    {
        return $this->organizedEvents->count();
    }

    public function getNextEvent(): ?Event
    {
        $upcomingEvents = $this->getUpcomingEvents();
        
        if ($upcomingEvents->isEmpty()) {
            return null;
        }
        
        /** @var array<int, Event> $eventsArray */
        $eventsArray = array_values($upcomingEvents->toArray());
        usort($eventsArray, static fn (Event $a, Event $b): int => $a->getStartDateTime() <=> $b->getStartDateTime());
        
        return $eventsArray[0];
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if ($imageFile) {
            $this->createdDate = new \DateTime();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }
}
