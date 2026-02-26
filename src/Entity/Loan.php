<?php

namespace App\Entity;

use App\Enum\LoanStatus;
use App\Enum\PaymentStatus;
use App\Repository\LoanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['bookCopy', 'member', 'checkoutTime'],
    message: 'Un emprunt identique existe deja pour cet exemplaire, cet adherent et cette date de sortie.'
)]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de sortie est obligatoire.')]
    private ?\DateTimeInterface $checkoutTime = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date limite est obligatoire.')]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $returnDate = null;

    #[ORM\Column(enumType: LoanStatus::class)]
    #[Assert\NotNull(message: 'Le statut de l\'emprunt est obligatoire.')]
    #[Assert\Choice(
        callback: [self::class, 'allowedStatuses'],
        message: 'Le statut de l\'emprunt est invalide.'
    )]
    private LoanStatus $status = LoanStatus::ACTIVE;

    #[ORM\Column]
    #[Assert\GreaterThanOrEqual(
        value: 0,
        message: 'Le nombre de renouvellements doit etre superieur ou egal a 0.'
    )]
    private int $renewalCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'exemplaire est obligatoire.')]
    private ?BookCopy $bookCopy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'adherent est obligatoire.')]
    private ?User $member = null;

    #[ORM\OneToMany(targetEntity: Penalty::class, mappedBy: 'loan', cascade: ['remove'])]
    private Collection $penalties;

    #[ORM\OneToMany(targetEntity: Renewal::class, mappedBy: 'loan', cascade: ['remove'])]
    private Collection $renewals;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastEmailReminderSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSmsReminderSentAt = null;

    /**
     * Non persisted snapshot used to detect dueDate changes after return.
     */
    private ?\DateTimeImmutable $originalDueDateSnapshot = null;

    public function __construct()
    {
        $this->penalties = new ArrayCollection();
        $this->renewals = new ArrayCollection();
    }

    /**
     * @return LoanStatus[]
     */
    public static function allowedStatuses(): array
    {
        return LoanStatus::cases();
    }

    #[ORM\PostLoad]
    public function rememberLoadedDueDate(): void
    {
        $this->originalDueDateSnapshot = $this->dueDate instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($this->dueDate)
            : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheckoutTime(): ?\DateTimeInterface
    {
        return $this->checkoutTime;
    }

    public function setCheckoutTime(\DateTimeInterface $checkoutTime): static
    {
        $this->checkoutTime = $checkoutTime;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getReturnDate(): ?\DateTimeInterface
    {
        return $this->returnDate;
    }

    public function setReturnDate(?\DateTimeInterface $returnDate): static
    {
        $this->returnDate = $returnDate;

        return $this;
    }

    public function getStatus(): LoanStatus
    {
        return $this->status;
    }

    public function setStatus(LoanStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRenewalCount(): int
    {
        return $this->renewalCount;
    }

    public function getCurrentRenewalNumber(): int
    {
        return $this->renewalCount;
    }

    public function hasBeenRenewed(): bool
    {
        return $this->renewalCount > 0;
    }

    public function setRenewalCount(int $renewalCount): static
    {
        $this->renewalCount = $renewalCount;

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

    public function getBookCopy(): ?BookCopy
    {
        return $this->bookCopy;
    }

    public function setBookCopy(?BookCopy $bookCopy): static
    {
        $this->bookCopy = $bookCopy;

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

    /**
     * @return Collection<int, Penalty>
     */
    public function getPenalties(): Collection
    {
        return $this->penalties;
    }

    public function addPenalty(Penalty $penalty): static
    {
        if (!$this->penalties->contains($penalty)) {
            $this->penalties->add($penalty);
            $penalty->setLoan($this);
        }

        return $this;
    }

    public function removePenalty(Penalty $penalty): static
    {
        if ($this->penalties->removeElement($penalty)) {
            if ($penalty->getLoan() === $this) {
                $penalty->setLoan(null);
            }
        }

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

    public function getTotalUnpaidPenalty(): float
    {
        $total = 0.0;
        foreach ($this->penalties as $penalty) {
            if ($penalty->isWaived()) {
                continue;
            }

            if (!\in_array($penalty->getStatus(), [PaymentStatus::UNPAID, PaymentStatus::PARTIAL], true)) {
                continue;
            }

            $total += $penalty->getAmount();
        }

        return round($total, 2);
    }

    public function hasUnpaidPenalty(): bool
    {
        return $this->getTotalUnpaidPenalty() > 0;
    }

    public function getActiveLatePenalty(): ?Penalty
    {
        $active = null;
        foreach ($this->penalties as $penalty) {
            if (!$penalty->isLatePenaltyReason()) {
                continue;
            }

            if ($penalty->isWaived() || $penalty->getStatus() === PaymentStatus::PAID) {
                continue;
            }

            if ($active === null || (($penalty->getId() ?? 0) > ($active->getId() ?? 0))) {
                $active = $penalty;
            }
        }

        return $active;
    }

    public function getDailyLatePenaltiesTotal(): float
    {
        $total = 0.0;
        foreach ($this->penalties as $penalty) {
            if (!$penalty->isDailyLateFee()) {
                continue;
            }

            if ($penalty->getStatus() !== PaymentStatus::UNPAID) {
                continue;
            }

            if ($penalty->isWaived()) {
                continue;
            }

            $total += $penalty->getAmount();
        }

        return round($total, 2);
    }

    public function getOverdueDays(?\DateTimeInterface $reference = null): int
    {
        if ($this->returnDate instanceof \DateTimeInterface || !$this->dueDate instanceof \DateTimeInterface) {
            return 0;
        }

        $referenceDate = self::toDateOnly($reference ?? new \DateTimeImmutable());
        $dueDate = self::toDateOnly($this->dueDate);

        if ($referenceDate <= $dueDate) {
            return 0;
        }

        return (int) $dueDate->diff($referenceDate)->days;
    }

    public function isLate(?\DateTimeInterface $reference = null): bool
    {
        return $this->getDaysLate($reference) > 0;
    }

    public function getDaysLate(?\DateTimeInterface $reference = null): int
    {
        if (!$this->dueDate instanceof \DateTimeInterface) {
            return 0;
        }

        $dueDate = self::toDateOnly($this->dueDate);
        $effectiveEnd = $this->returnDate instanceof \DateTimeInterface
            ? self::toDateOnly($this->returnDate)
            : self::toDateOnly($reference ?? new \DateTimeImmutable());

        if ($effectiveEnd <= $dueDate) {
            return 0;
        }

        return (int) $dueDate->diff($effectiveEnd)->format('%a');
    }

    /**
     * @return Collection<int, Renewal>
     */
    public function getRenewals(): Collection
    {
        return $this->renewals;
    }

    public function addRenewal(Renewal $renewal): static
    {
        if (!$this->renewals->contains($renewal)) {
            $this->renewals->add($renewal);
            $renewal->setLoan($this);
        }

        return $this;
    }

    public function removeRenewal(Renewal $renewal): static
    {
        if ($this->renewals->removeElement($renewal)) {
            if ($renewal->getLoan() === $this) {
                $renewal->setLoan(null);
            }
        }

        return $this;
    }

    public function isActiveOpenLoan(): bool
    {
        return $this->returnDate === null && \in_array($this->status, [LoanStatus::ACTIVE, LoanStatus::OVERDUE], true);
    }

    public function canBeRenewed(): bool
    {
        if ($this->returnDate instanceof \DateTimeInterface) {
            return false;
        }

        return \in_array($this->status, [LoanStatus::ACTIVE, LoanStatus::OVERDUE], true);
    }

    public function isRenewable(int $maxRenewals = 3): bool
    {
        return $this->canBeRenewed() && !$this->maxRenewalsReached($maxRenewals);
    }

    public function maxRenewalsReached(int $maxRenewals): bool
    {
        return $this->renewalCount >= $maxRenewals;
    }

    public function isOverdue(?\DateTimeInterface $reference = null): bool
    {
        if (!$this->isActiveOpenLoan() || !$this->dueDate instanceof \DateTimeInterface) {
            return false;
        }

        $referenceDate = self::toDateOnly($reference ?? new \DateTimeImmutable());
        $dueDate = self::toDateOnly($this->dueDate);

        return $referenceDate > $dueDate;
    }

    /**
     * True only for loans that are still ACTIVE, not returned, and past due date.
     */
    public function shouldBeOverdue(?\DateTimeInterface $reference = null): bool
    {
        if ($this->status !== LoanStatus::ACTIVE) {
            return false;
        }

        if ($this->returnDate instanceof \DateTimeInterface) {
            return false;
        }

        if (!$this->dueDate instanceof \DateTimeInterface) {
            return false;
        }

        $referenceDate = self::toDateOnly($reference ?? new \DateTimeImmutable());
        $dueDate = self::toDateOnly($this->dueDate);

        return $referenceDate > $dueDate;
    }

    /**
     * Apply ACTIVE -> OVERDUE transition only when needed.
     */
    public function markAsOverdueIfNeeded(?\DateTimeInterface $reference = null): bool
    {
        if (!$this->shouldBeOverdue($reference)) {
            return false;
        }

        $this->status = LoanStatus::OVERDUE;

        return true;
    }

    public function daysOverdue(?\DateTimeInterface $reference = null): int
    {
        if (!$this->isOverdue($reference) || !$this->dueDate instanceof \DateTimeInterface) {
            return 0;
        }

        $referenceDate = self::toDateOnly($reference ?? new \DateTimeImmutable());
        $dueDate = self::toDateOnly($this->dueDate);

        return (int) $dueDate->diff($referenceDate)->days;
    }

    public function daysLoaned(?\DateTimeInterface $reference = null): int
    {
        if (!$this->checkoutTime instanceof \DateTimeInterface) {
            return 0;
        }

        $start = \DateTimeImmutable::createFromInterface($this->checkoutTime);
        $end = $this->returnDate instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($this->returnDate)
            : \DateTimeImmutable::createFromInterface($reference ?? new \DateTimeImmutable());

        if ($end < $start) {
            return 0;
        }

        return (int) $start->diff($end)->days;
    }

    public function daysUntilDue(?\DateTimeInterface $reference = null): int
    {
        if (!$this->dueDate instanceof \DateTimeInterface) {
            return 0;
        }

        $referenceDate = self::toDateOnly($reference ?? new \DateTimeImmutable());
        $dueDate = self::toDateOnly($this->dueDate);

        return (int) $referenceDate->diff($dueDate)->format('%r%a');
    }

    public function isReturnedLate(): bool
    {
        if (!$this->returnDate instanceof \DateTimeInterface || !$this->dueDate instanceof \DateTimeInterface) {
            return false;
        }

        return self::toDateOnly($this->returnDate) > self::toDateOnly($this->dueDate);
    }

    /**
     * Sync status with dates.
     * Returns true when status changed.
     */
    public function refreshStatusFromDates(?\DateTimeInterface $reference = null): bool
    {
        $previousStatus = $this->status;

        if ($this->returnDate instanceof \DateTimeInterface) {
            $this->status = LoanStatus::RETURNED;
        } elseif ($this->isOverdue($reference)) {
            $this->status = LoanStatus::OVERDUE;
        } else {
            $this->status = LoanStatus::ACTIVE;
        }

        return $previousStatus !== $this->status;
    }

    public function applyReturnedAt(\DateTimeInterface $returnedAt): void
    {
        $this->returnDate = $returnedAt instanceof \DateTime
            ? clone $returnedAt
            : new \DateTime($returnedAt->format('Y-m-d H:i:s.u'), $returnedAt->getTimezone());
        $this->status = LoanStatus::RETURNED;
    }

    public function __toString(): string
    {
        return sprintf(
            'Loan #%d - %s (%s)',
            $this->id ?? 0,
            $this->member?->getFirstName() . ' ' . $this->member?->getLastName(),
            $this->status->name
        );
    }

    #[Assert\Callback]
    public function validateLoanRules(ExecutionContextInterface $context): void
    {
        $checkout = $this->checkoutTime instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($this->checkoutTime)
            : null;
        $dueDate = $this->dueDate instanceof \DateTimeInterface
            ? self::toDateOnly($this->dueDate)
            : null;
        $returnDate = $this->returnDate instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($this->returnDate)
            : null;

        if ($checkout && $dueDate) {
            $checkoutDate = self::toDateOnly($checkout);
            if ($dueDate < $checkoutDate) {
                $context->buildViolation('La date limite doit etre posterieure ou egale a la date de sortie.')
                    ->atPath('dueDate')
                    ->addViolation();
            }
        }

        if ($this->id === null && $dueDate && $returnDate === null) {
            $today = self::toDateOnly(new \DateTimeImmutable('today'));
            if ($dueDate < $today) {
                $context->buildViolation('La date limite ne peut pas etre dans le passe lors de la creation.')
                    ->atPath('dueDate')
                    ->addViolation();
            }
        }

        if ($returnDate && $checkout && $returnDate < $checkout) {
            $context->buildViolation('La date de retour doit etre posterieure a la date de sortie.')
                ->atPath('returnDate')
                ->addViolation();
        }

        if ($this->status !== LoanStatus::RETURNED && $returnDate !== null) {
            $context->buildViolation('La date de retour doit rester vide tant que le statut n\'est pas "RETURNED".')
                ->atPath('returnDate')
                ->addViolation();
        }

        if ($this->status === LoanStatus::RETURNED && $returnDate === null) {
            $context->buildViolation('La date de retour est obligatoire lorsque le statut est "RETURNED".')
                ->atPath('returnDate')
                ->addViolation();
        }

        if (
            $returnDate !== null
            && $this->originalDueDateSnapshot instanceof \DateTimeInterface
            && $dueDate instanceof \DateTimeInterface
            && self::toDateOnly($this->originalDueDateSnapshot)->format('Y-m-d') !== $dueDate->format('Y-m-d')
        ) {
            $context->buildViolation('La date limite ne peut pas etre modifiee apres l\'enregistrement d\'une date de retour.')
                ->atPath('dueDate')
                ->addViolation();
        }
    }

    private static function toDateOnly(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
    }
}
