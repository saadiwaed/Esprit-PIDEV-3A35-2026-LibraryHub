<?php

namespace App\Tests\Service;  // ← Change le namespace !

use App\Entity\JournalLecture;
use App\Service\JournalLectureValidator;
use PHPUnit\Framework\TestCase;

class JournalLectureValidatorTest extends TestCase
{
    private JournalLectureValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JournalLectureValidator();
    }

    // ===========================================
    // TESTS POUR LA MÉTHODE validate() AVEC EXCEPTIONS
    // ===========================================

    public function testValidJournalLecture()
    {
        $journal = $this->createValidJournal();
        $result = $this->validator->validate($journal);
        $this->assertTrue($result);
    }

    public function testJournalWithoutTitle()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre de la lecture est obligatoire.');

        $journal = $this->createValidJournal();
        $journal->setTitre('');
        $this->validator->validate($journal);
    }

    public function testJournalWithZeroPages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de pages doit être positif.');

        $journal = $this->createValidJournal();
        $journal->setPageLues(0);
        $this->validator->validate($journal);
    }

    public function testJournalWithNegativePages()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre de pages doit être positif.');

        $journal = $this->createValidJournal();
        $journal->setPageLues(-5);
        $this->validator->validate($journal);
    }

    public function testJournalWithNoteTooLow()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être comprise entre 1 et 5.');

        $journal = $this->createValidJournal();
        $journal->setNotePerso(0);
        $this->validator->validate($journal);
    }

    public function testJournalWithNoteTooHigh()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La note doit être comprise entre 1 et 5.');

        $journal = $this->createValidJournal();
        $journal->setNotePerso(6);
        $this->validator->validate($journal);
    }

    public function testJournalWithConcentrationTooLow()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La concentration doit être comprise entre 1 et 10.');

        $journal = $this->createValidJournal();
        $journal->setConcentration(0);
        $this->validator->validate($journal);
    }

    public function testJournalWithConcentrationTooHigh()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La concentration doit être comprise entre 1 et 10.');

        $journal = $this->createValidJournal();
        $journal->setConcentration(11);
        $this->validator->validate($journal);
    }

    public function testJournalWithNegativeDuration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être positive.');

        $journal = $this->createValidJournal();
        $journal->setDureeMinutes(-10);
        $this->validator->validate($journal);
    }

    public function testJournalWithZeroDuration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être positive.');

        $journal = $this->createValidJournal();
        $journal->setDureeMinutes(0);
        $this->validator->validate($journal);
    }

    // ===========================================
    // TESTS POUR LA MÉTHODE isValid() (SANS EXCEPTIONS)
    // ===========================================

    public function testIsValidWithValidJournal()
    {
        $journal = $this->createValidJournal();
        $result = $this->validator->isValid($journal);
        $this->assertTrue($result);
    }

    public function testIsValidWithInvalidJournal()
    {
        $journal = new JournalLecture(); // Tout est null
        $result = $this->validator->isValid($journal);
        $this->assertFalse($result);
    }

    public function testIsValidWithMissingTitle()
    {
        $journal = $this->createValidJournal();
        $journal->setTitre('');
        $result = $this->validator->isValid($journal);
        $this->assertFalse($result);
    }

    // ===========================================
    // MÉTHODE UTILITAIRE POUR CRÉER UN JOURNAL VALIDE
    // ===========================================
    private function createValidJournal(): JournalLecture
    {
        $journal = new JournalLecture();
        $journal->setTitre('Dune - Chapitre 1');
        $journal->setPageLues(30);
        $journal->setNotePerso(4);
        $journal->setConcentration(7);
        $journal->setDureeMinutes(45);
        return $journal;
    }
}