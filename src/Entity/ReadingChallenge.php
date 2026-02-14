<?php

namespace App\Entity;

use App\Repository\ReadingChallengeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Enum\ChallengeType;
use App\Enum\ParticipationStatus;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReadingChallengeRepository::class)]
#[ORM\Table(name: 'reading_challenges')]
class ReadingChallenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'objectif du défi est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'L\'objectif doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'objectif ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $goal = null;

    #[ORM\Column(enumType: ChallengeType::class)]
    #[Assert\NotNull(message: 'Le type de défi est obligatoire.')]
    private ChallengeType $type = ChallengeType::READING;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['upcoming', 'ongoing', 'completed', 'cancelled'],
        message: 'Le statut doit être : upcoming, ongoing, completed ou cancelled.'
    )]
    private string $status = 'upcoming';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La récompense ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $reward = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Les règles du défi sont obligatoires.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Les règles doivent contenir au moins {{ limit }} caractères.'
    )]
    private ?string $rules = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La difficulté est obligatoire.')]
    #[Assert\Choice(
        choices: ['easy', 'medium', 'hard'],
        message: 'La difficulté doit être : easy, medium ou hard.'
    )]
    private ?string $difficulty = null;

    #[ORM\ManyToOne(targetEntity: Club::class, inversedBy: 'challenges')]
    private ?Club $club = null;

    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: ChallengeParticipant::class)]
    private Collection $participants;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être après la date de début.'
    )]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le créateur est obligatoire.')]
    private ?User $createdBy = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de création est obligatoire.')]
    private ?\DateTimeInterface $createdDate = null;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
        $this->createdDate = new \DateTime();
    }
    public function getActiveParticipants(): Collection
{
    return $this->participants->filter(
        fn(ChallengeParticipant $cp) => $cp->getStatus() === ParticipationStatus::ACTIVE
    );
}

public function getCompletionRate(): float
{
    $totalParticipants = $this->participants->count();
    if ($totalParticipants === 0) return 0.0;
    
    $completed = $this->participants->filter(
        fn(ChallengeParticipant $cp) => $cp->getStatus() === ParticipationStatus::COMPLETED
    )->count();
    
    return round(($completed / $totalParticipants) * 100, 1);
}

public function isActive(): bool
{
    $now = new \DateTime();
    return $this->status === 'ongoing' 
        && $this->startDate <= $now 
        && $this->endDate >= $now;
}
    // =============== GETTERS ===============
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoal(): ?string
    {
        return $this->goal;
    }

    public function getType(): ChallengeType
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReward(): ?string
    {
        return $this->reward;
    }

    public function getRules(): ?string
    {
        return $this->rules;
    }

    public function getDifficulty(): ?string
    {
        return $this->difficulty;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    /**
     * @return Collection<int, ChallengeParticipant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function getCreatedDate(): ?\DateTimeInterface
    {
        return $this->createdDate;
    }

    // =============== SETTERS ===============
    public function setGoal(string $goal): self
    {
        $this->goal = $goal;
        return $this;
    }

    public function setType(ChallengeType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setReward(?string $reward): self
    {
        $this->reward = $reward;
        return $this;
    }

    public function setRules(string $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function setDifficulty(string $difficulty): self
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function setClub(?Club $club): self
    {
        $this->club = $club;
        return $this;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function setCreatedDate(\DateTimeInterface $createdDate): self
    {
        $this->createdDate = $createdDate;
        return $this;
    }

    
}
