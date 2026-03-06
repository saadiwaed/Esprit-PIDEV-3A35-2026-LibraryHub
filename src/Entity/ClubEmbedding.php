<?php

namespace App\Entity;

use App\Repository\ClubEmbeddingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClubEmbeddingRepository::class)]
#[ORM\Table(name: 'club_embeddings')]
class ClubEmbedding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(name: 'club_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Club $club = null;

    /** @var list<float> */
    #[ORM\Column(type: 'json')]
    private array $embedding = [];

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): self
    {
        $this->club = $club;
        return $this;
    }

    /** @return list<float> */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    /** @param list<float> $embedding */
    public function setEmbedding(array $embedding): self
    {
        $this->embedding = $embedding;
        return $this;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }
}
