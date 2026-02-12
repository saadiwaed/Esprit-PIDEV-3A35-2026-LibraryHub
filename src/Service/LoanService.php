<?php

namespace App\Service;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Entity\Renewal;
use App\Enum\PaymentStatus;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class LoanService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoanRepository $loanRepository,
        private readonly PenaltyRepository $penaltyRepository,
        private readonly int $defaultLoanDays,
        private readonly int $renewalDays,
        private readonly int $maxRenewals,
        private readonly float $dailyPenaltyRate,
    ) {
    }

    public function calculateDueDate(\DateTimeInterface $checkoutTime, ?int $loanDays = null): \DateTime
    {
        $days = $loanDays ?? $this->defaultLoanDays;

        return $this->toMutableDateTime($checkoutTime)
            ->setTime(23, 59, 59)
            ->modify(sprintf('+%d days', $days));
    }

    public function createLoan(Loan $loan, bool $flush = true): Loan
    {
        $checkoutTime = $loan->getCheckoutTime() instanceof \DateTimeInterface
            ? $this->toMutableDateTime($loan->getCheckoutTime())
            : new \DateTime();

        $loan->setCheckoutTime($checkoutTime);
        $loan->setDueDate($this->calculateDueDate($checkoutTime));
        $loan->refreshStatusFromDates();

        $this->entityManager->persist($loan);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $loan;
    }

    public function renewLoan(Loan $loan, bool $flush = true): Renewal
    {
        if (!$loan->isActiveOpenLoan()) {
            throw new \LogicException('Seuls les emprunts ouverts peuvent etre renouveles.');
        }

        if ($loan->getRenewalCount() >= $this->maxRenewals) {
            throw new \LogicException(sprintf(
                'Nombre maximal de renouvellements atteint (%d).',
                $this->maxRenewals
            ));
        }

        $currentDueDate = $loan->getDueDate();
        if (!$currentDueDate instanceof \DateTimeInterface) {
            throw new \LogicException('Impossible de renouveler un emprunt sans date limite.');
        }

        $newDueDate = $this->toMutableDateTime($currentDueDate)
            ->setTime(23, 59, 59)
            ->modify(sprintf('+%d days', $this->renewalDays));

        $renewal = (new Renewal())
            ->setLoan($loan)
            ->setPreviousDueDate($currentDueDate)
            ->setNewDueDate($newDueDate)
            ->setRenewedAt(new \DateTime())
            ->setRenewalNumber($loan->getRenewalCount() + 1);

        $loan->setDueDate($newDueDate);
        $loan->setRenewalCount($loan->getRenewalCount() + 1);
        $loan->refreshStatusFromDates();

        $this->entityManager->persist($renewal);
        $this->entityManager->persist($loan);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $renewal;
    }

    public function returnLoan(
        Loan $loan,
        ?\DateTimeInterface $returnedAt = null,
        bool $allowPastDate = false,
        bool $flush = true
    ): Loan {
        if ($loan->getReturnDate() instanceof \DateTimeInterface) {
            throw new \LogicException('Cet emprunt est deja marque comme retourne.');
        }

        $checkoutTime = $loan->getCheckoutTime();
        if (!$checkoutTime instanceof \DateTimeInterface) {
            throw new \LogicException('Date de sortie manquante sur cet emprunt.');
        }

        $returnMoment = $returnedAt instanceof \DateTimeInterface
            ? $this->toMutableDateTime($returnedAt)
            : new \DateTime();

        if (!$allowPastDate && $returnMoment < $checkoutTime) {
            throw new \InvalidArgumentException(
                'La date de retour ne peut pas etre anterieure a la date de sortie.'
            );
        }

        $loan->applyReturnedAt($returnMoment);

        $this->entityManager->persist($loan);

        if ($flush) {
            $this->entityManager->flush();
        }

        return $loan;
    }

    public function refreshOverdueStatuses(?\DateTimeInterface $reference = null, bool $flush = true): int
    {
        $referenceAt = $reference instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($reference)
            : new \DateTimeImmutable();

        $updatedCount = 0;
        foreach ($this->loanRepository->findOverdueCandidates($referenceAt) as $loan) {
            if ($loan->markAsOverdueIfNeeded($referenceAt)) {
                ++$updatedCount;
                $this->entityManager->persist($loan);
            }
        }

        if ($flush && $updatedCount > 0) {
            $this->entityManager->flush();
        }

        return $updatedCount;
    }

    public function generateOrUpdateOverduePenalties(?\DateTimeInterface $reference = null, bool $flush = true): int
    {
        $referenceAt = \DateTimeImmutable::createFromInterface($reference ?? new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $updatedCount = 0;

        foreach ($this->loanRepository->findOverdueOpenLoansForPenalty($referenceAt) as $loan) {
            $overdueDays = $loan->getOverdueDays($referenceAt);
            if ($overdueDays <= 0) {
                continue;
            }

            $amount = round($overdueDays * $this->dailyPenaltyRate, 2);
            if ($amount <= 0) {
                continue;
            }

            $penalty = $this->penaltyRepository->findOpenOverduePenaltyForLoan($loan);
            if ($penalty instanceof Penalty) {
                if (abs($penalty->getAmount() - $amount) < 0.0001) {
                    continue;
                }

                $penalty
                    ->setAmount($amount)
                    ->setIssueDate($referenceAt)
                    ->setReason(sprintf('Retard de %d jour(s) - penalite journaliere.', $overdueDays))
                    ->setStatus(PaymentStatus::UNPAID)
                    ->setWaived(false)
                    ->setNotes('AUTO_OVERDUE_DAILY');
                $this->entityManager->persist($penalty);
                ++$updatedCount;
                continue;
            }

            $newPenalty = (new Penalty())
                ->setLoan($loan)
                ->setAmount($amount)
                ->setReason(sprintf('Retard de %d jour(s) - penalite journaliere.', $overdueDays))
                ->setIssueDate($referenceAt)
                ->setWaived(false)
                ->setStatus(PaymentStatus::UNPAID)
                ->setNotes('AUTO_OVERDUE_DAILY');
            $this->entityManager->persist($newPenalty);
            ++$updatedCount;
        }

        if ($flush && $updatedCount > 0) {
            $this->entityManager->flush();
        }

        return $updatedCount;
    }

    public function getDefaultLoanDays(): int
    {
        return $this->defaultLoanDays;
    }

    public function getRenewalDays(): int
    {
        return $this->renewalDays;
    }

    public function getMaxRenewals(): int
    {
        return $this->maxRenewals;
    }

    private function toMutableDateTime(\DateTimeInterface $dateTime): \DateTime
    {
        if ($dateTime instanceof \DateTime) {
            return clone $dateTime;
        }

        return new \DateTime($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
    }
}
