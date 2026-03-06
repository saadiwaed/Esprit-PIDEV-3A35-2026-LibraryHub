<?php

namespace App\Entity;

use App\Repository\RenewalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RenewalRepository::class)]
class Renewal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
