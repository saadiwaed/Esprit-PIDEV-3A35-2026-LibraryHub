<?php

namespace App\Entity;

use App\Enum\PostModerationDecision;
use App\Enum\PostReportStatus;
use App\Repository\PostReportRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PostReportRepository::class)]
#[ORM\Table(name: 'post_report')]
#[ORM\HasLifecycleCallbacks]
class PostReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'reports')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reporter_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $reporter = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le motif du signalement est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 1500,
        minMessage: 'Le motif doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le motif ne peut pas depasser {{ limit }} caracteres.'
    )]
    private string $reason = '';

    #[ORM\Column(type: 'string', length: 20, enumType: PostReportStatus::class)]
    private PostReportStatus $status = PostReportStatus::PENDING;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reviewed_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: 'string', length: 30, enumType: PostModerationDecision::class, nullable: true)]
    private ?PostModerationDecision $moderatorDecision = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderatorDecisionReason = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): self
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStatus(): PostReportStatus
    {
        return $this->status;
    }

    public function setStatus(PostReportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeInterface $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    public function getModeratorDecision(): ?PostModerationDecision
    {
        return $this->moderatorDecision;
    }

    public function setModeratorDecision(?PostModerationDecision $moderatorDecision): self
    {
        $this->moderatorDecision = $moderatorDecision;

        return $this;
    }

    public function getModeratorDecisionReason(): ?string
    {
        return $this->moderatorDecisionReason;
    }

    public function setModeratorDecisionReason(?string $moderatorDecisionReason): self
    {
        $this->moderatorDecisionReason = $moderatorDecisionReason;

        return $this;
    }
}

