<?php

namespace App\Entity;

use App\Enum\ParticipationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ChallengeParticipantRepository;

#[ORM\Entity(repositoryClass: ChallengeParticipantRepository::class)]
#[ORM\Table(name: 'challenge_participants')]
#[ORM\UniqueConstraint(name: 'unique_participant', columns: ['participant_id', 'challenge_id'])]
class ChallengeParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $participant = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $joinedAt;

    #[ORM\Column]
    private int $booksRead = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(enumType: ParticipationStatus::class)]
    private ParticipationStatus $status = ParticipationStatus::ACTIVE;

    #[ORM\Column(name: 'challenge_id', type: 'integer', nullable: true)]
    private ?int $challenge = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipant(): ?User
    {
        return $this->participant;
    }

    public function setParticipant(?User $participant): self
    {
        $this->participant = $participant;
        return $this;
    }

    public function getJoinedAt(): \DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeInterface $joinedAt): self
    {
        $this->joinedAt = $joinedAt;
        return $this;
    }

    public function getBooksRead(): int
    {
        return $this->booksRead;
    }

    public function setBooksRead(int $booksRead): self
    {
        $this->booksRead = $booksRead;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getStatus(): ParticipationStatus
    {
        return $this->status;
    }

    public function setStatus(ParticipationStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getChallenge(): ?int
    {
        return $this->challenge;
    }

    public function setChallenge(?int $challenge): self
    {
        $this->challenge = $challenge;
        return $this;
    }
}
