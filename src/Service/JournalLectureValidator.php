<?php

namespace App\Service;

use App\Entity\JournalLecture;

class JournalLectureValidator
{
    /**
     * Valide une entrée de journal selon les règles métier
     * 
     * @param JournalLecture $journal
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validate(JournalLecture $journal): bool
    {
        // RÈGLE 1 : Le titre ne peut pas être vide
        if (empty($journal->getTitre())) {
            throw new \InvalidArgumentException('Le titre de la lecture est obligatoire.');
        }

        // RÈGLE 2 : Le nombre de pages doit être positif
        if ($journal->getPageLues() <= 0) {
            throw new \InvalidArgumentException('Le nombre de pages doit être positif.');
        }

        // RÈGLE 3 : La note doit être entre 1 et 5
        $note = $journal->getNotePerso();
        if ($note < 1 || $note > 5) {
            throw new \InvalidArgumentException('La note doit être comprise entre 1 et 5.');
        }

        // RÈGLE 4 : La concentration doit être entre 1 et 10
        $concentration = $journal->getConcentration();
        if ($concentration < 1 || $concentration > 10) {
            throw new \InvalidArgumentException('La concentration doit être comprise entre 1 et 10.');
        }

        // RÈGLE 5 : La durée doit être positive
        if ($journal->getDureeMinutes() <= 0) {
            throw new \InvalidArgumentException('La durée doit être positive.');
        }

        return true;
    }

    /**
     * Version alternative qui retourne false au lieu de lancer une exception
     * (comme dans l'exemple du PDF)
     */
    public function isValid(JournalLecture $journal): bool
    {
        if (empty($journal->getTitre())) {
            return false;
        }
        if ($journal->getPageLues() <= 0) {
            return false;
        }
        if ($journal->getNotePerso() < 1 || $journal->getNotePerso() > 5) {
            return false;
        }
        if ($journal->getConcentration() < 1 || $journal->getConcentration() > 10) {
            return false;
        }
        if ($journal->getDureeMinutes() <= 0) {
            return false;
        }
        return true;
    }
}