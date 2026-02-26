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

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre de likes ne peut pas etre negatif')]
    private int $likeCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre de dislikes ne peut pas etre negatif')]
    private int $dislikeCount = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdPosts')]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: Community::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'community_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'La communauté est obligatoire')]
    private ?Community $community = null;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'post', cascade: ['remove'], orphanRemoval: true)]
    private Collection $comments;

    /** @var Collection<int, PostReaction> */
    #[ORM\OneToMany(targetEntity: PostReaction::class, mappedBy: 'post', cascade: ['remove'], orphanRemoval: true)]
    private Collection $reactions;

    /** @var Collection<int, PostReport> */
    #[ORM\OneToMany(targetEntity: PostReport::class, mappedBy: 'post', cascade: ['remove'], orphanRemoval: true)]
    private Collection $reports;

    /** @var Collection<int, Attachment> */
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attachments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->comments = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->reports = new ArrayCollection();
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

    public function getLikeCount(): int
    {
        return $this->likeCount;
    }

    public function setLikeCount(int $likeCount): self
    {
        $this->likeCount = max(0, $likeCount);

        return $this;
    }

    public function getDislikeCount(): int
    {
        return $this->dislikeCount;
    }

    public function setDislikeCount(int $dislikeCount): self
    {
        $this->dislikeCount = max(0, $dislikeCount);

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

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, PostReaction> */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    public function addReaction(PostReaction $reaction): self
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions->add($reaction);
            $reaction->setPost($this);
        }

        return $this;
    }

    public function removeReaction(PostReaction $reaction): self
    {
        if ($this->reactions->removeElement($reaction)) {
            if ($reaction->getPost() === $this) {
                $reaction->setPost(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, PostReport> */
    public function getReports(): Collection
    {
        return $this->reports;
    }

    public function addReport(PostReport $report): self
    {
        if (!$this->reports->contains($report)) {
            $this->reports->add($report);
            $report->setPost($this);
        }

        return $this;
    }

    public function removeReport(PostReport $report): self
    {
        if ($this->reports->removeElement($report)) {
            if ($report->getPost() === $this) {
                $report->setPost(null);
            }
        }

        return $this;
    }

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

    public function incrementCommentCount(): self
    {
        ++$this->commentCount;

        return $this;
    }

    public function decrementCommentCount(): self
    {
        if ($this->commentCount > 0) {
            --$this->commentCount;
        }

        return $this;
    }

    public function incrementLikeCount(): self
    {
        ++$this->likeCount;

        return $this;
    }

    public function decrementLikeCount(): self
    {
        if ($this->likeCount > 0) {
            --$this->likeCount;
        }

        return $this;
    }

    public function incrementDislikeCount(): self
    {
        ++$this->dislikeCount;

        return $this;
    }

    public function decrementDislikeCount(): self
    {
        if ($this->dislikeCount > 0) {
            --$this->dislikeCount;
        }

        return $this;
    }

    public function getScore(): int
    {
        return $this->likeCount - $this->dislikeCount;
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
