<?php

namespace App\Service;

use App\Entity\Loan;
use App\Entity\Renewal;
use App\Enum\LoanStatus;
use App\Exception\LoanAlreadyReturnedException;
use App\Exception\MaxRenewalsReachedException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class RenewalService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly int $renewalDays,
        private readonly int $maxRenewals,
    ) {
    }

    public function renewLoan(Loan $loan, ?\DateTimeImmutable $newDueDate = null): Renewal
    {
        if ($loan->getId() === null) {
            throw new \InvalidArgumentException('Impossible de renouveler un emprunt non persiste.');
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            /** @var Loan|null $lockedLoan */
            $lockedLoan = $this->entityManager->find(Loan::class, $loan->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$lockedLoan instanceof Loan) {
                throw new \LogicException('Emprunt introuvable.');
            }

            if ($lockedLoan->getReturnDate() instanceof \DateTimeInterface || $lockedLoan->getStatus() === LoanStatus::RETURNED) {
                throw new LoanAlreadyReturnedException('Cet emprunt est deja retourne. Renouvellement impossible.');
            }

            if (!$lockedLoan->canBeRenewed()) {
                throw new \LogicException('Seuls les emprunts actifs ou en retard peuvent etre renouveles.');
            }

            if ($lockedLoan->maxRenewalsReached($this->maxRenewals)) {
                throw new MaxRenewalsReachedException('Limite de renouvellements atteinte');
            }

            $currentDueDate = $lockedLoan->getDueDate();
            if (!$currentDueDate instanceof \DateTimeInterface) {
                throw new \LogicException('Impossible de renouveler un emprunt sans date limite.');
            }

            $computedNewDueDate = $newDueDate instanceof \DateTimeInterface
                ? $this->toMutableDateTime($newDueDate)->setTime(23, 59, 59)
                : $this->toMutableDateTime($currentDueDate)
                    ->setTime(23, 59, 59)
                    ->modify(sprintf('+%d days', $this->renewalDays));

            if ($computedNewDueDate <= $this->toMutableDateTime($currentDueDate)) {
                throw new \InvalidArgumentException('La nouvelle date limite doit etre posterieure a la date limite actuelle.');
            }

            $renewalNumber = $lockedLoan->getRenewals()->count() + 1;
            $newRenewalCount = $lockedLoan->getRenewalCount() + 1;

            $renewal = (new Renewal())
                ->setLoan($lockedLoan)
                ->setPreviousDueDate($currentDueDate)
                ->setNewDueDate($computedNewDueDate)
                ->setRenewedAt(new \DateTime())
                ->setRenewalNumber($renewalNumber);

            $lockedLoan->setDueDate($computedNewDueDate);
            $lockedLoan->setRenewalCount($newRenewalCount);
            $lockedLoan->refreshStatusFromDates();

            $this->entityManager->persist($renewal);
            $this->entityManager->persist($lockedLoan);
            $this->entityManager->flush();

            $connection->commit();

            return $renewal;
        } catch (\Throwable $exception) {
            $this->rollbackQuietly($connection);
            throw $exception;
        }
    }

    public function getRenewalDays(): int
    {
        return $this->renewalDays;
    }

    private function rollbackQuietly(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    private function toMutableDateTime(\DateTimeInterface $dateTime): \DateTime
    {
        if ($dateTime instanceof \DateTime) {
            return clone $dateTime;
        }

        return new \DateTime($dateTime->format('Y-m-d H:i:s.u'), $dateTime->getTimezone());
    }
}
