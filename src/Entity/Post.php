<?php

namespace App\Entity;

use App\Enum\PostStatus;
use App\Repository\PostRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
#[ORM\HasLifecycleCallbacks]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le titre du post est obligatoire')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le contenu du post est obligatoire')]
    #[Assert\Length(
        min: 10,
        max: 10000,
        minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PostStatus::class)]
    private PostStatus $status = PostStatus::DRAFT;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $spoilerWarning = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $allowComments = true;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Url(message: 'L\'URL externe {{ value }} n\'est pas valide')]
    #[Assert\Length(max: 500, maxMessage: 'L\'URL ne peut pas dépasser {{ limit }} caractères')]
    private ?string $externalUrl = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre de commentaires ne peut pas être négatif')]
    private int $commentCount = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Community::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'community_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'La communauté est obligatoire')]
    private ?Community $community = null;

    /** @var Collection<int, Attachment> */
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attachments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->attachments = new ArrayCollection();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): PostStatus
    {
        return $this->status;
    }

    public function setStatus(PostStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isSpoilerWarning(): bool
    {
        return $this->spoilerWarning;
    }

    public function setSpoilerWarning(bool $spoilerWarning): self
    {
        $this->spoilerWarning = $spoilerWarning;
        return $this;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): self
    {
        $this->isPinned = $isPinned;
        return $this;
    }

    public function isAllowComments(): bool
    {
        return $this->allowComments;
    }

    public function setAllowComments(bool $allowComments): self
    {
        $this->allowComments = $allowComments;
        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;
        return $this;
    }

    public function getCommentCount(): int
    {
        return $this->commentCount;
    }

    public function setCommentCount(int $commentCount): self
    {
        $this->commentCount = $commentCount;
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

    public function getCommunity(): ?Community
    {
        return $this->community;
    }

    public function setCommunity(?Community $community): self
    {
        $this->community = $community;
        return $this;
    }

    // ─── Attachments ─────────────────────────────────────

    /** @return Collection<int, Attachment> */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(Attachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setPost($this);
        }
        return $this;
    }

    public function removeAttachment(Attachment $attachment): self
    {
        if ($this->attachments->removeElement($attachment)) {
            if ($attachment->getPost() === $this) {
                $attachment->setPost(null);
            }
        }
        return $this;
    }

    // ─── Business Logic (Métiers de Base) ────────────────

    /**
     * Publie le post
     */
    public function publish(): self
    {
        $this->status = PostStatus::PUBLISHED;
        return $this;
    }

    /**
     * Archive le post
     */
    public function archive(): self
    {
        $this->status = PostStatus::ARCHIVED;
        return $this;
    }

    /**
     * Vérifie si le post est visible (publié et communauté approuvée)
     */
    public function isVisible(): bool
    {
        return $this->status === PostStatus::PUBLISHED
            && $this->community !== null
            && $this->community->getStatus() === \App\Enum\CommunityStatus::APPROVED;
    }

    /**
     * Vérifie si le post accepte des commentaires
     */
    public function canBeCommented(): bool
    {
        return $this->allowComments && $this->isVisible();
    }

    /**
     * Épingle ou désépingle le post
     */
    public function togglePin(): self
    {
        $this->isPinned = !$this->isPinned;
        return $this;
    }

    /**
     * Vérifie si le post a des pièces jointes
     */
    public function hasAttachments(): bool
    {
        return !$this->attachments->isEmpty();
    }

    /**
     * Retourne le nombre de pièces jointes
     */
    public function getAttachmentCount(): int
    {
        return $this->attachments->count();
    }

    /**
     * Retourne uniquement les pièces jointes de type image
     */
    public function getImageAttachments(): Collection
    {
        return $this->attachments->filter(
            fn(Attachment $a) => $a->isImage()
        );
    }

    public function __toString(): string
    {
        return $this->title ?? 'Post';
    }
}