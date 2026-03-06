<?php

namespace App\Entity;

use App\Repository\JournalLectureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert; // âœ… AJOUTER CETTE LIGNE

#[ORM\Entity(repositoryClass: JournalLectureRepository::class)]
class JournalLecture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "âŒ Le titre de la lecture est obligatoire")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "âŒ Le titre doit contenir au moins {{ limit }} caractÃ¨res",
        maxMessage: "âŒ Le titre ne peut pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $titre = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ L'ID du livre est obligatoire")]
    #[Assert\Positive(message: "âŒ L'ID du livre doit Ãªtre un nombre positif")]
    private ?int $livre_id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: "âŒ La date de lecture est obligatoire")]
    #[Assert\Type(type: "\DateTime", message: "âŒ Format de date invalide")]
    #[Assert\LessThanOrEqual(
        value: "today",
        message: "âŒ La date de lecture ne peut pas Ãªtre dans le futur"
    )]
    private ?\DateTime $date_lecture = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ La durÃ©e est obligatoire")]
    #[Assert\Positive(message: "âŒ La durÃ©e doit Ãªtre un nombre positif")]
    #[Assert\LessThan(
        value: 1440,
        message: "âŒ La durÃ©e ne peut pas dÃ©passer 24 heures (1440 minutes)"
    )]
    private ?int $duree_minutes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "âŒ Le lieu ne peut pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $lieu = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ La concentration est obligatoire")]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: "âŒ La concentration doit Ãªtre entre {{ min }} et {{ max }}"
    )]
    private ?int $concentration = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ Le nombre de pages est obligatoire")]
    #[Assert\Positive(message: "âŒ Le nombre de pages doit Ãªtre positif")]
    #[Assert\LessThan(
        value: 2000,
        message: "âŒ Le nombre de pages ne peut pas dÃ©passer {{ value }}"
    )]
    private ?int $page_lues = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "âŒ Le rÃ©sumÃ© est obligatoire")]
    #[Assert\Length(
        min: 10,
        max: 255,
        minMessage: "âŒ Le rÃ©sumÃ© doit contenir au moins {{ limit }} caractÃ¨res",
        maxMessage: "âŒ Le rÃ©sumÃ© ne peut pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $resume = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "âŒ Les rÃ©flexions ne peuvent pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $reflexion = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ La note est obligatoire")]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: "âŒ La note doit Ãªtre entre {{ min }} et {{ max }}"
    )]
    private ?int $note_perso = null;

    #[ORM\ManyToOne(inversedBy: 'journaux')]
    private ?DefiPersonel $defi = null;

    public function getId(): ?int
    {
        return $this->id;
    }

   
    // âœ… NOUVEAU GETTER : getUser() retourne l'objet User
    public function getUser(): ?User
    {
        return $this->user;
    }

    // âœ… NOUVEAU SETTER : setUser() prend un objet User
    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    // âœ… GARDER getUserId() pour la compatibilitÃ© (optionnel)
    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getLivreId(): ?int
    {
        return $this->livre_id;
    }

    public function setLivreId(int $livre_id): static
    {
        $this->livre_id = $livre_id;
        return $this;
    }

    public function getDateLecture(): ?\DateTime
    {
        return $this->date_lecture;
    }

    public function setDateLecture(\DateTime $date_lecture): static
    {
        $this->date_lecture = $date_lecture;
        return $this;
    }

    public function getDureeMinutes(): ?int
    {
        return $this->duree_minutes;
    }

    public function setDureeMinutes(int $duree_minutes): static
    {
        $this->duree_minutes = $duree_minutes;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getConcentration(): ?int
    {
        return $this->concentration;
    }

    public function setConcentration(int $concentration): static
    {
        $this->concentration = $concentration;
        return $this;
    }

    public function getPageLues(): ?int
    {
        return $this->page_lues;
    }

    public function setPageLues(int $page_lues): static
    {
        $this->page_lues = $page_lues;
        return $this;
    }

    public function getResume(): ?string
    {
        return $this->resume;
    }

    public function setResume(string $resume): static
    {
        $this->resume = $resume;
        return $this;
    }

    public function getReflexion(): ?string
    {
        return $this->reflexion;
    }

    public function setReflexion(?string $reflexion): static
    {
        $this->reflexion = $reflexion;
        return $this;
    }

    public function getNotePerso(): ?int
    {
        return $this->note_perso;
    }

    public function setNotePerso(int $note_perso): static
    {
        $this->note_perso = $note_perso;
        return $this;
    }

    public function getDefi(): ?DefiPersonel
    {
        return $this->defi;
    }

    public function setDefi(?DefiPersonel $defi): static
    {
        $this->defi = $defi;
        return $this;
    }
}
