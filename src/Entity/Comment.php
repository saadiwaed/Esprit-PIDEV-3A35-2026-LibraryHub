<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'post_comment')]
#[ORM\HasLifecycleCallbacks]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le commentaire est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 5000,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caracteres',
        maxMessage: 'Le commentaire ne peut pas depasser {{ limit }} caracteres'
    )]
    private ?string $content = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $likeCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $dislikeCount = 0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /** @var Collection<int, CommentReaction> */
    #[ORM\OneToMany(targetEntity: CommentReaction::class, mappedBy: 'comment', cascade: ['remove'], orphanRemoval: true)]
    private Collection $reactions;

    public function __construct()
    {
        $this->reactions = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;

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

    /** @return Collection<int, CommentReaction> */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    public function addReaction(CommentReaction $reaction): self
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions->add($reaction);
            $reaction->setComment($this);
        }

        return $this;
    }

    public function removeReaction(CommentReaction $reaction): self
    {
        if ($this->reactions->removeElement($reaction)) {
            if ($reaction->getComment() === $this) {
                $reaction->setComment(null);
            }
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
}
