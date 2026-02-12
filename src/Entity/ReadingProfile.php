<?php

namespace App\Entity;

use App\Repository\ReadingProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReadingProfileRepository::class)]
class ReadingProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * RELATION OneToOne (owning side): Each ReadingProfile belongs to exactly one User.
     * JoinColumn means this table has the foreign key column "user_id".
     */
    #[ORM\OneToOne(inversedBy: 'readingProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'A user must be selected for this reading profile.')]
    private ?User $user = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $favoriteGenres = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $preferredLanguages = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Reading goal must be a positive number.')]
    private ?int $readingGoalPerMonth = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Total books read cannot be negative.')]
    private ?int $totalBooksRead = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 5,
        notInRangeMessage: 'Average rating must be between {{ min }} and {{ max }}.'
    )]
    private ?float $averageRating = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getFavoriteGenres(): ?array
    {
        return $this->favoriteGenres;
    }

    public function setFavoriteGenres(?array $favoriteGenres): static
    {
        $this->favoriteGenres = $favoriteGenres;
        return $this;
    }

    public function getPreferredLanguages(): ?array
    {
        return $this->preferredLanguages;
    }

    public function setPreferredLanguages(?array $preferredLanguages): static
    {
        $this->preferredLanguages = $preferredLanguages;
        return $this;
    }

    public function getReadingGoalPerMonth(): ?int
    {
        return $this->readingGoalPerMonth;
    }

    public function setReadingGoalPerMonth(?int $readingGoalPerMonth): static
    {
        $this->readingGoalPerMonth = $readingGoalPerMonth;
        return $this;
    }

    public function getTotalBooksRead(): ?int
    {
        return $this->totalBooksRead;
    }

    public function setTotalBooksRead(int $totalBooksRead): static
    {
        $this->totalBooksRead = $totalBooksRead;
        return $this;
    }

    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    public function setAverageRating(?float $averageRating): static
    {
        $this->averageRating = $averageRating;
        return $this;
    }

    public function __toString(): string
    {
        return $this->user ? 'Profile of ' . $this->user->getFullName() : 'Reading Profile';
    }
}
