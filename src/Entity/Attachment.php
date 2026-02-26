<?php

namespace App\Entity;

use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'attachment')]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le chemin du fichier est obligatoire')]
    private ?string $filePath = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'attachments')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    // ─── Getters & Setters ───────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
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

    // ─── Business Logic ──────────────────────────────────

    /**
     * Retourne l'extension du fichier
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    /**
     * Vérifie si le fichier est une image
     */
    public function isImage(): bool
    {
        return in_array(
            strtolower($this->getFileExtension()),
            ['jpg', 'jpeg', 'png', 'gif', 'webp']
        );
    }

    /**
     * Vérifie si le fichier est un PDF
     */
    public function isPdf(): bool
    {
        return strtolower($this->getFileExtension()) === 'pdf';
    }

    /**
     * Retourne le nom de fichier sans le préfixe unique
     * (Le service ajoute un uniqid — ceci essaie d'extraire le nom original)
     */
    public function getDisplayName(): string
    {
        $filename = $this->filePath;
        // Our upload service stores files as: uniqid-originalname.ext
        $dashPos = strpos($filename, '-');
        if ($dashPos !== false) {
            return substr($filename, $dashPos + 1);
        }
        return $filename;
    }

    public function __toString(): string
    {
        return $this->filePath ?? 'Pièce jointe';
    }
}