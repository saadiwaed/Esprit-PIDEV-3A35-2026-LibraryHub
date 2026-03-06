<?php

namespace App\Entity;

use App\Repository\DefiPersonelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert; // âœ… AJOUTER CETTE LIGNE

#[ORM\Entity(repositoryClass: DefiPersonelRepository::class)]
class DefiPersonel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $user_id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?float $progression = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "âŒ Le titre du dÃ©fi est obligatoire")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "âŒ Le titre doit contenir au moins {{ limit }} caractÃ¨res",
        maxMessage: "âŒ Le titre ne peut pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $titre = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "âŒ La description est obligatoire")]
    #[Assert\Length(
        min: 10,
        max: 255,
        minMessage: "âŒ La description doit contenir au moins {{ limit }} caractÃ¨res",
        maxMessage: "âŒ La description ne peut pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "âŒ Le type de dÃ©fi est obligatoire")]
    #[Assert\Choice(
        choices: ["Quantitatif", "ThÃ©matique", "DÃ©couverte"],
        message: "âŒ Veuillez choisir un type valide"
    )]
    private ?string $type_defi = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: "âŒ La date de dÃ©but est obligatoire")]
    #[Assert\Type(type: "\DateTime", message: "âŒ Format de date invalide")]
    #[Assert\GreaterThanOrEqual(
        value: "today",
        message: "âŒ La date de dÃ©but ne peut pas Ãªtre dans le passÃ©"
    )]
    private ?\DateTime $date_debut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: "âŒ La date de fin est obligatoire")]
    #[Assert\Type(type: "\DateTime", message: "âŒ Format de date invalide")]
    #[Assert\GreaterThan(
        propertyPath: "date_debut",
        message: "âŒ La date de fin doit Ãªtre postÃ©rieure Ã  la date de dÃ©but"
    )]
    private ?\DateTime $date_fin = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ L'objectif est obligatoire")]
    #[Assert\Positive(message: "âŒ L'objectif doit Ãªtre un nombre positif")]
    #[Assert\LessThan(
        value: 1000,
        message: "âŒ L'objectif ne peut pas dÃ©passer {{ value }}"
    )]
    private ?int $objectif = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "âŒ L'unitÃ© est obligatoire parmi Livres Pages Heures ")]
    #[Assert\Choice(
        choices: ["Livres", "Pages", "Heures"],
        message: "âŒ Veuillez choisir une unitÃ© valide"
    )]
    private ?string $unite = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "âŒ La difficultÃ© est obligatoire")]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: "âŒ La difficultÃ© doit Ãªtre entre {{ min }} et {{ max }}"
    )]
    private ?int $difficulte = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "âŒ La rÃ©compense ne peut pas dÃ©passer {{ limit }} caractÃ¨res"
    )]
    private ?string $recompense = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Choice(
        choices: ["En cours", "TerminÃ©", "AbandonnÃ©"],
        message: "âŒ Statut invalide"
    )]
    private ?string $statut = null;

    /**
     * @var Collection<int, JournalLecture>
     */
    #[ORM\OneToMany(targetEntity: JournalLecture::class, mappedBy: 'defi')]
    private Collection $journaux;

    public function __construct()
    {
        $this->journaux = new ArrayCollection();
    }

    // ... Getters et setters existants (inchangÃ©s) ...
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): static
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getProgression(): ?float
    {
        return $this->progression;
    }

    public function setProgression(?float $progression): static
    {
        $this->progression = $progression;
        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;
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

    public function getTypeDefi(): ?string
    {
        return $this->type_defi;
    }

    public function setTypeDefi(?string $type_defi): static
    {
        $this->type_defi = $type_defi;
        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTime $date_debut): static
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->date_fin;
    }

    public function setDateFin(\DateTime $date_fin): static
    {
        $this->date_fin = $date_fin;
        return $this;
    }

    public function getObjectif(): ?int
    {
        return $this->objectif;
    }

    public function setObjectif(?int $objectif): static
    {
        $this->objectif = $objectif;
        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;
        return $this;
    }

    public function getDifficulte(): ?int
    {
        return $this->difficulte;
    }

    public function setDifficulte(?int $difficulte): static
    {
        $this->difficulte = $difficulte;
        return $this;
    }

    public function getRecompense(): ?string
    {
        return $this->recompense;
    }

    public function setRecompense(?string $recompense): static
    {
        $this->recompense = $recompense;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    /**
     * @return Collection<int, JournalLecture>
     */
    public function getJournaux(): Collection
    {
        return $this->journaux;
    }

    public function addJournaux(JournalLecture $journaux): static
    {
        if (!$this->journaux->contains($journaux)) {
            $this->journaux->add($journaux);
            $journaux->setDefi($this);
        }
        return $this;
    }

    public function removeJournaux(JournalLecture $journaux): static
    {
        if ($this->journaux->removeElement($journaux)) {
            if ($journaux->getDefi() === $this) {
                $journaux->setDefi(null);
            }
        }
        return $this;
    }
}
