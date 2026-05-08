<?php

namespace App\Tests\Service;

use App\Entity\Loan;
use App\Service\LoanValidator;
use PHPUnit\Framework\TestCase;

final class LoanValidatorTest extends TestCase
{
    public function testValidLoan(): void
    {
        $loan = new Loan();
        $loan->setCheckoutTime(new \DateTime('2026-03-01 10:00:00'));
        $loan->setDueDate(new \DateTime('2026-03-01')); // même jour => valide
        $loan->setRenewalCount(0);

        $validator = new LoanValidator();

        $this->assertTrue($validator->validate($loan));
    }

    public function testLoanWithInvalidDueDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $loan = new Loan();
        $loan->setCheckoutTime(new \DateTime('2026-03-02 10:00:00'));
        $loan->setDueDate(new \DateTime('2026-03-01')); // avant la date d'emprunt => invalide
        $loan->setRenewalCount(0);

        $validator = new LoanValidator();
        $validator->validate($loan);
    }

    public function testLoanWithNegativeRenewalCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $loan = new Loan();
        $loan->setCheckoutTime(new \DateTime('2026-03-01 10:00:00'));
        $loan->setDueDate(new \DateTime('2026-03-10'));
        $loan->setRenewalCount(-1); // invalide

        $validator = new LoanValidator();
        $validator->validate($loan);
    }
}

