<?php

namespace App\Service;

use App\Entity\Loan;

/**
 * Service métier simple pour valider les règles internes d'un emprunt (Loan).
 * Utilisé dans un workshop de tests unitaires (validation isolée, sans base de données).
 */
final class LoanValidator
{
    /**
     * Valide les règles métier d'un emprunt.
     *
     * @throws \InvalidArgumentException si une règle n'est pas respectée
     */
    public function validate(Loan $loan): bool
    {
        $checkoutTime = $loan->getCheckoutTime();
        $dueDate = $loan->getDueDate();

        if (!$checkoutTime instanceof \DateTimeInterface || !$dueDate instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('La date d’emprunt et la date d’échéance sont obligatoires.');
        }

        // Règle 1 : dueDate >= checkoutTime (comparaison à la journée, comme une échéance "date").
        $checkoutDay = (new \DateTimeImmutable($checkoutTime->format('Y-m-d')))->setTime(0, 0, 0);
        $dueDay = (new \DateTimeImmutable($dueDate->format('Y-m-d')))->setTime(0, 0, 0);

        if ($dueDay < $checkoutDay) {
            throw new \InvalidArgumentException('La date d’échéance doit être postérieure ou égale à la date d’emprunt.');
        }

        // Règle 2 : renewalCount ne peut pas être négatif.
        if ($loan->getRenewalCount() < 0) {
            throw new \InvalidArgumentException('Le nombre de renouvellements ne peut pas être négatif.');
        }

        return true;
    }
}

