<?php

namespace App\Entity;

use App\Enum\LoanRequestStatus;
use App\Repository\LoanRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRequestRepository::class)]
#[ORM\Index(columns: ['status', 'requested_at'], name: 'loan_request_status_requested_at_idx')]
class LoanRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $member = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le livre est obligatoire.')]
    private ?Book $book = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date d'emprunt souhaitée est obligatoire.")]
    #[Assert\GreaterThanOrEqual(value: 'today', message: "La date d'emprunt souhaitée doit être aujourd'hui ou plus tard.")]
    private ?\DateTimeImmutable $desiredLoanDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de retour souhaitée est obligatoire.')]
    private ?\DateTimeImmutable $desiredReturnDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(enumType: LoanRequestStatus::class)]
    private LoanRequestStatus $status = LoanRequestStatus::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMember(): ?User
    {
        return $this->member;
    }

    public function setMember(?User $member): self
    {
        $this->member = $member;

        return $this;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): self
    {
        $this->book = $book;

        return $this;
    }

    public function getDesiredLoanDate(): ?\DateTimeImmutable
    {
        return $this->desiredLoanDate;
    }

    public function setDesiredLoanDate(?\DateTimeImmutable $desiredLoanDate): self
    {
        $this->desiredLoanDate = $desiredLoanDate;

        return $this;
    }

    public function getDesiredReturnDate(): ?\DateTimeImmutable
    {
        return $this->desiredReturnDate;
    }

    public function setDesiredReturnDate(?\DateTimeImmutable $desiredReturnDate): self
    {
        $this->desiredReturnDate = $desiredReturnDate;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): self
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getStatus(): LoanRequestStatus
    {
        return $this->status;
    }

    public function setStatus(LoanRequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }
}
