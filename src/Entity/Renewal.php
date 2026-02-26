<?php

namespace App\Entity;

use App\Repository\RenewalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RenewalRepository::class)]
class Renewal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $previousDueDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $newDueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $renewedAt = null;

    #[ORM\Column]
    private int $renewalNumber = 0;

    #[ORM\ManyToOne(inversedBy: 'renewals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Loan $loan = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPreviousDueDate(): ?\DateTimeInterface
    {
        return $this->previousDueDate;
    }

    public function setPreviousDueDate(\DateTimeInterface $previousDueDate): static
    {
        $this->previousDueDate = $previousDueDate;
        return $this;
    }

    public function getNewDueDate(): ?\DateTimeInterface
    {
        return $this->newDueDate;
    }

    public function setNewDueDate(\DateTimeInterface $newDueDate): static
    {
        $this->newDueDate = $newDueDate;
        return $this;
    }

    public function getRenewedAt(): ?\DateTimeInterface
    {
        return $this->renewedAt;
    }

    public function setRenewedAt(\DateTimeInterface $renewedAt): static
    {
        $this->renewedAt = $renewedAt;
        return $this;
    }

    public function getRenewalNumber(): int
    {
        return $this->renewalNumber;
    }

    public function setRenewalNumber(int $renewalNumber): static
    {
        $this->renewalNumber = $renewalNumber;
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
}
