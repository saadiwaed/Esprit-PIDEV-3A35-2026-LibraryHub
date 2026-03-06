<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\Table(name: 'author')]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $firstname ;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string      $lastname ;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank(message: 'Le biographie est obligatoire.')]

    #[Assert\Length(
        max: 5000,
        maxMessage: 'La biographie ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $biography = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Le chemin de la photo ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $photo = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'La nationalité est obligatoire.')]

    #[Assert\Length(
        max: 100,
        maxMessage: 'La nationalité ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nationality = null;


        /**
     * @var Collection<int, Book>
     */
    #[ORM\OneToMany(targetEntity: Book::class, mappedBy: 'author' )]
    private Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;
        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstname . ' ' . $this->lastname);
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): static
    {
        $this->biography = $biography;
        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): static
    {
        $this->nationality = $nationality;
        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}
