<?php

namespace App\Entity;

use App\Enum\PaymentStatus;
use App\Repository\PenaltyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PenaltyRepository::class)]
class Penalty
{
    public const DAILY_LATE_REASON_PREFIX = 'Retard journalier';

    public const REASON_LATE_RETURN = 'late_return';
    public const REASON_DAMAGED_BOOK = 'damaged_book';
    public const REASON_OTHER = 'other';

    public const FIXED_REASONS = [
        self::REASON_LATE_RETURN,
        self::REASON_DAMAGED_BOOK,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotNull(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant de l\'amende doit etre strictement superieur a 0.')]
    private float $amount = 0.0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.50'])]
    #[Assert\Positive(message: 'Le taux journalier doit etre strictement superieur a 0.')]
    private string $dailyRate = '0.50';

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le nombre de jours de retard doit etre positif.')]
    private int $lateDays = 0;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'La raison de l\'amende est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'La raison ne doit pas depasser {{ limit }} caracteres.')]
    private string $reason = '';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotNull(message: 'La date d\'emission est obligatoire.')]
    #[Assert\LessThanOrEqual(value: 'today', message: 'La date d\'emission ne peut pas etre dans le futur.')]
    private ?\DateTimeInterface $issueDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    #[Assert\Type(type: 'bool', message: 'Le champ exoneration doit etre un booleen.')]
    private bool $waived = false;

    #[ORM\Column(enumType: PaymentStatus::class)]
    #[Assert\NotNull(message: 'Le statut de paiement est obligatoire.')]
    #[Assert\Choice(callback: [PaymentStatus::class, 'cases'], message: 'Le statut de paiement selectionne est invalide.')]
    private PaymentStatus $status = PaymentStatus::UNPAID;

    #[ORM\ManyToOne(inversedBy: 'penalties')]
    #[ORM\JoinColumn(nullable: true)]
    #[Assert\NotNull(message: 'L\'emprunt associe est obligatoire.')]
    private ?Loan $loan = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDailyRate(): float
    {
        return (float) $this->dailyRate;
    }

    public function setDailyRate(float $dailyRate): static
    {
        $this->dailyRate = number_format(max(0, $dailyRate), 2, '.', '');

        return $this;
    }

    public function getLateDays(): int
    {
        return $this->lateDays;
    }

    public function setLateDays(int $lateDays): static
    {
        $this->lateDays = max(0, $lateDays);

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = trim((string) ($reason ?? ''));

        return $this;
    }

    public function getReasonLabel(): string
    {
        return match ($this->reason) {
            self::REASON_LATE_RETURN => 'Retour tardif',
            self::REASON_DAMAGED_BOOK => 'Livre endommage',
            default => $this->reason,
        };
    }

    public function isDailyLateFee(): bool
    {
        return str_starts_with($this->reason, self::DAILY_LATE_REASON_PREFIX);
    }

    public function isLatePenaltyReason(): bool
    {
        return $this->reason === self::REASON_LATE_RETURN || $this->isDailyLateFee();
    }

    public function incrementLateDaysAndFee(): void
    {
        if ($this->status === PaymentStatus::PAID || $this->waived) {
            return;
        }

        $this->lateDays += 1;
        $this->amount = round($this->amount + $this->getDailyRate(), 2);
    }

    public function getIssueDate(): ?\DateTimeInterface
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeInterface $issueDate): static
    {
        $this->issueDate = $issueDate instanceof \DateTime
            ? clone $issueDate
            : new \DateTime($issueDate->format('Y-m-d'), $issueDate->getTimezone());

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

    public function isWaived(): bool
    {
        return $this->waived;
    }

    public function setWaived(bool $waived): static
    {
        $this->waived = $waived;

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        return $this;
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

    public function __toString(): string
    {
        return sprintf(
            'Penalty #%d - %s (%s)',
            $this->id ?? 0,
            number_format($this->amount, 2),
            $this->status->name
        );
    }

    #[Assert\Callback]
    public function validatePenaltyRules(ExecutionContextInterface $context): void
    {
        if ($this->status === PaymentStatus::PAID && $this->amount <= 0) {
            $context->buildViolation('Une amende payee doit avoir un montant strictement positif.')
                ->atPath('amount')
                ->addViolation();
        }

        if ($this->reason === self::REASON_OTHER) {
            $context->buildViolation('Veuillez preciser le motif lorsque vous choisissez "Autre".')
                ->atPath('reason')
                ->addViolation();
        }
    }
}
