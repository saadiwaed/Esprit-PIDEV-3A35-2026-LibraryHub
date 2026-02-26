<?php

namespace App\Entity;

// Guard against accidental double-inclusion (e.g. PSR-4 case mismatch on Windows).
if (class_exists(__NAMESPACE__ . '\\LoanRequest', false)) {
    return;
}

use App\Repository\LoanRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LoanRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoanRequest
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le membre est obligatoire.')]
    private ?User $member = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'L\'ID du livre est obligatoire.')]
    #[Assert\Positive(message: 'L\'ID du livre doit être un nombre positif.')]
    private int $bookId = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date d\'emprunt souhaitée est obligatoire.')]
    private ?\DateTimeImmutable $desiredLoanDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de retour souhaitée est obligatoire.')]
    private ?\DateTimeImmutable $desiredReturnDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(choices: self::ALLOWED_STATUSES, message: 'Statut invalide.')]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::STRING, length: 15)]
    #[Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^\+216\d{8}$/',
        message: 'Le numéro doit contenir exactement 8 chiffres après +216'
    )]
    private ?string $phoneNumber = null;

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

    public function getMember(): ?User
    {
        return $this->member;
    }

    public function setMember(?User $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getBookId(): int
    {
        return $this->bookId;
    }

    public function setBookId(int $bookId): static
    {
        $this->bookId = max(0, $bookId);

        return $this;
    }

    public function getDesiredLoanDate(): ?\DateTimeImmutable
    {
        return $this->desiredLoanDate;
    }

    public function setDesiredLoanDate(?\DateTimeInterface $desiredLoanDate): static
    {
        $this->desiredLoanDate = $desiredLoanDate instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($desiredLoanDate)->setTime(0, 0, 0)
            : null;

        return $this;
    }

    public function getDesiredReturnDate(): ?\DateTimeImmutable
    {
        return $this->desiredReturnDate;
    }

    public function setDesiredReturnDate(?\DateTimeInterface $desiredReturnDate): static
    {
        $this->desiredReturnDate = $desiredReturnDate instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($desiredReturnDate)->setTime(0, 0, 0)
            : null;

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

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $value = preg_replace('/\s+/', '', trim((string) ($phoneNumber ?? '')));
        $value = str_replace(['-', '(', ')', '.'], '', $value);

        if ($value === '') {
            $this->phoneNumber = null;

            return $this;
        }

        if (preg_match('/^\+216\d{8}$/', $value) === 1) {
            $this->phoneNumber = $value;

            return $this;
        }

        if (preg_match('/^216(\d{8})$/', $value, $m) === 1) {
            $this->phoneNumber = '+216' . $m[1];

            return $this;
        }

        if (preg_match('/^(\d{8})$/', $value, $m) === 1) {
            $this->phoneNumber = '+216' . $m[1];

            return $this;
        }

        $this->phoneNumber = $value;

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

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (!$this->desiredLoanDate instanceof \DateTimeInterface || !$this->desiredReturnDate instanceof \DateTimeInterface) {
            return;
        }

        $loanDate = \DateTimeImmutable::createFromInterface($this->desiredLoanDate)->setTime(0, 0, 0);
        $returnDate = \DateTimeImmutable::createFromInterface($this->desiredReturnDate)->setTime(0, 0, 0);

        if ($returnDate <= $loanDate) {
            $context->buildViolation('La date de retour souhaitée doit être supérieure à la date d\'emprunt souhaitée.')
                ->atPath('desiredReturnDate')
                ->addViolation();
        }
    }
}
