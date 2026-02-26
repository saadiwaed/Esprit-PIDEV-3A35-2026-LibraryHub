<?php

namespace App\Entity;

use App\Enum\CommunityStatus;
use App\Repository\CommunityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommunityRepository::class)]
#[ORM\Table(name: 'community')]
#[ORM\HasLifecycleCallbacks]
class Community
{
    private const ICON_PRESETS = [
        'general' => 'fa-book',
        'fantasy' => 'fa-hat-wizard',
        'science-fiction' => 'fa-robot',
        'sci-fi' => 'fa-robot',
        'policier' => 'fa-user-secret',
        'thriller' => 'fa-user-secret',
        'romance' => 'fa-heart',
        'histoire' => 'fa-landmark',
        'science' => 'fa-flask',
        'poesie' => 'fa-feather-alt',
        'jeunesse' => 'fa-child',
        'voyage' => 'fa-globe',
        'developpement personnel' => 'fa-seedling',
        'discussions' => 'fa-comments',
        'academique' => 'fa-graduation-cap',
        'aventure' => 'fa-map',
        'horreur' => 'fa-ghost',
        'mythologie' => 'fa-dragon',
        'classiques' => 'fa-book-open',
        'manga' => 'fa-book-open',
        'bande dessinee' => 'fa-book-open',
        'biographie' => 'fa-user',
        'philosophie' => 'fa-brain',
        'religion' => 'fa-praying-hands',
        'politique' => 'fa-landmark',
        'economie' => 'fa-chart-line',
        'histoire antique' => 'fa-monument',
        'informatique' => 'fa-laptop-code',
        'programmation' => 'fa-code',
        'cybersecurite' => 'fa-shield-alt',
        'intelligence artificielle' => 'fa-microchip',
        'mathematiques' => 'fa-square-root-alt',
        'physique' => 'fa-atom',
        'chimie' => 'fa-vial',
        'astronomie' => 'fa-star',
        'medecine' => 'fa-stethoscope',
        'psychologie' => 'fa-brain',
        'droit' => 'fa-gavel',
        'art' => 'fa-palette',
        'musique' => 'fa-music',
        'cinema' => 'fa-film',
        'theatre' => 'fa-theater-masks',
        'photographie' => 'fa-camera',
        'cuisine' => 'fa-utensils',
        'nature' => 'fa-leaf',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la communauté est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $name = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'L\'objectif de la communauté est obligatoire')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L\'objectif doit contenir au moins {{ limit }} caractères'
    )]
    private ?string $purpose = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 3000,
        maxMessage: 'Les règles ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $rules = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'L\'icône ne peut pas dépasser {{ limit }} caractères')]
    private ?string $icon = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Le message de bienvenue ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $welcomeMessage = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    #[Assert\Email(message: 'L\'adresse email {{ value }} n\'est pas valide')]
    private ?string $contactEmail = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\Column(type: 'string', length: 20, enumType: CommunityStatus::class)]
    private CommunityStatus $status = CommunityStatus::PENDING;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre de membres ne peut pas être négatif')]
    private int $memberCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre de posts ne peut pas être négatif')]
    private int $postCount = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdCommunities')]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'communities')]
    #[ORM\JoinTable(name: 'community_members')]
    private Collection $members;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'community', cascade: ['remove'])]
    private Collection $posts;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    // ─── Getters & Setters ───────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPurpose(): ?string
    {
        return $this->purpose;
    }

    public function setPurpose(?string $purpose): self
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function getRules(): ?string
    {
        return $this->rules;
    }

    public function setRules(?string $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        if ($icon !== null) {
            $icon = trim($icon);
        }

        if ($icon === '') {
            $icon = null;
        }

        if ($icon !== null) {
            $icon = $this->normalizePresetLabel($icon);
        }

        if ($icon !== null && !str_contains($icon, ' ')) {
            if (str_starts_with($icon, 'fa-')) {
                $icon = 'fas ' . $icon;
            } elseif (str_starts_with($icon, 'bi-')) {
                $icon = 'bi ' . $icon;
            }
        }

        $this->icon = $icon;
        return $this;
    }

    public function getIconClass(): string
    {
        $icon = $this->icon ? trim($this->icon) : '';

        if ($icon === '') {
            return 'fas fa-users';
        }

        $icon = $this->normalizePresetLabel($icon);

        if (!str_contains($icon, ' ')) {
            if (str_starts_with($icon, 'fa-')) {
                return 'fas ' . $icon;
            }

            if (str_starts_with($icon, 'bi-')) {
                return 'bi ' . $icon;
            }
        }

        return $icon;
    }

    private function normalizePresetLabel(string $value): string
    {
        $presetKey = strtolower(trim($value));

        return self::ICON_PRESETS[$presetKey] ?? $value;
    }

    public function getWelcomeMessage(): ?string
    {
        return $this->welcomeMessage;
    }

    public function setWelcomeMessage(?string $welcomeMessage): self
    {
        $this->welcomeMessage = $welcomeMessage;
        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getStatus(): CommunityStatus
    {
        return $this->status;
    }

    public function setStatus(CommunityStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMemberCount(): int
    {
        return $this->memberCount;
    }

    public function setMemberCount(int $memberCount): self
    {
        $this->memberCount = $memberCount;
        return $this;
    }

    public function getPostCount(): int
    {
        return $this->postCount;
    }

    public function setPostCount(int $postCount): self
    {
        $this->postCount = $postCount;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /** @return Collection<int, User> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(User $member): self
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $this->memberCount = $this->members->count();
        }

        return $this;
    }

    public function removeMember(User $member): self
    {
        if ($this->members->removeElement($member)) {
            $this->memberCount = $this->members->count();
        }

        return $this;
    }

    public function hasMember(User $member): bool
    {
        return $this->members->contains($member);
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setCommunity($this);
        }
        return $this;
    }

    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getCommunity() === $this) {
                $post->setCommunity(null);
            }
        }
        return $this;
    }

    // ─── Business Logic (Métiers de Base) ────────────────

    /**
     * Vérifie si la communauté accepte de nouveaux posts
     */
    public function canAcceptPosts(): bool
    {
        return $this->status === CommunityStatus::APPROVED && $this->isPublic;
    }

    /**
     * Approuve la communauté
     */
    public function approve(): self
    {
        $this->status = CommunityStatus::APPROVED;
        return $this;
    }

    /**
     * Rejette la communauté
     */
    public function reject(): self
    {
        $this->status = CommunityStatus::REJECTED;
        return $this;
    }

    /**
     * Incrémente le compteur de posts
     */
    public function incrementPostCount(): self
    {
        $this->postCount++;
        return $this;
    }

    /**
     * Décrémente le compteur de posts
     */
    public function decrementPostCount(): self
    {
        if ($this->postCount > 0) {
            $this->postCount--;
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Communauté';
    }
}
