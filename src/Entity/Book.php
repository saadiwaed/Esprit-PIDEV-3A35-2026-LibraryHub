<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\Table(name: 'book')]
class Book
{
/**
 * @var int|null
 */
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: 'integer')]
private ?int $id = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: 'Le titre du livre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 500,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $title ;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        max: 10000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'L\'éditeur est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'éditeur ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $publisher = null;

    #[ORM\Column(name: 'publication_year', type: 'integer', nullable: true)]
    #[Assert\NotNull(message: 'L\'année de publication est obligatoire.')]
    #[Assert\Range(
        min: 1000,
        max: 2026,
        notInRangeMessage: 'L\'année doit être entre {{ min }} et {{ max }}.',
        invalidMessage: 'L\'année de publication doit être un nombre valide.'
    )]
    private ?int $publicationYear = null;

    #[ORM\Column(name: 'page_count', type: 'integer', nullable: true)]
    #[Assert\NotNull(message: 'Le nombre de pages est obligatoire.')]
    #[Assert\Range(
        min: 1,
        max: 50000,
        notInRangeMessage: 'Le nombre de pages doit être entre {{ min }} et {{ max }}.',
        invalidMessage: 'Le nombre de pages doit être un entier valide.'
    )]
    private ?int $pageCount = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'La langue est obligatoire.')]
    #[Assert\Length(
        max: 50,
        maxMessage: 'La langue ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $language = null;

    #[ORM\Column(name: 'cover_image', length: 500, nullable: true)]
    #[Assert\NotBlank(message: 'L\'image de couverture est obligatoire.')]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Le chemin de l\'image ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $coverImage = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['available', 'borrowed', 'maintenance', 'reserved'],
        message: 'Le statut doit être l\'un des suivants : Disponible, Emprunté, En maintenance, Réservé.'
    )]
    private string $status = 'available';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date d\'ajout est obligatoire.')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'books')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id_cat', nullable: false)]
    #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Author::class, inversedBy: 'books')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'L\'auteur est obligatoire.')]
    private ?Author $author = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;
        return $this;
    }

    public function getPublicationYear(): ?int
    {
        return $this->publicationYear;
    }

    public function setPublicationYear(?int $publicationYear): static
    {
        $this->publicationYear = $publicationYear;
        return $this;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): static
    {
        $this->pageCount = $pageCount;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface    {
        return $this->createdAt;
    }

    protected  function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    public function setAuthor(?Author $author): static
    {
        $this->author = $author;
        return $this;
    }
}
