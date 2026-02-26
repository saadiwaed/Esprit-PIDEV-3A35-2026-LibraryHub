<?php

namespace App\Entity;

use App\Repository\RenewalRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RenewalRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RenewalRequest
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'renewalRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'emprunt est obligatoire.')]
    private ?Loan $loan = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le membre est obligatoire.')]
    private ?User $member = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(choices: self::ALLOWED_STATUSES, message: 'Statut invalide.')]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastEmailReminderSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSmsReminderSentAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoan(): ?Loan
    {
        return $this->loan;
    }

    public function setLoan(?Loan $loan): static
    {
        $this->loan = $loan;

        return $this;
    }

    public function getMember(): ?User
    {
        return $this->member;
    }

    public function setMember(?User $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(?\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $status = strtoupper(trim($status));
        $this->status = \in_array($status, self::ALLOWED_STATUSES, true) ? $status : self::STATUS_PENDING;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getLastEmailReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastEmailReminderSentAt;
    }

    public function setLastEmailReminderSentAt(?\DateTimeImmutable $lastEmailReminderSentAt): static
    {
        $this->lastEmailReminderSentAt = $lastEmailReminderSentAt;

        return $this;
    }

    public function getLastSmsReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastSmsReminderSentAt;
    }

    public function setLastSmsReminderSentAt(?\DateTimeImmutable $lastSmsReminderSentAt): static
    {
        $this->lastSmsReminderSentAt = $lastSmsReminderSentAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function initRequestedAt(): void
    {
        if (!$this->requestedAt instanceof \DateTimeImmutable) {
            $this->requestedAt = new \DateTimeImmutable();
        }
    }
}
