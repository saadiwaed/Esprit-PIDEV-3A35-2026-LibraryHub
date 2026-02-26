<?php

namespace App\Service\Forum;

final class ForumContentModerationResult
{
    public function __construct(
        private readonly bool $blocked,
        private readonly bool $apiAvailable,
        private readonly ?float $toxicityScore,
        private readonly string $message
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function isApiAvailable(): bool
    {
        return $this->apiAvailable;
    }

    public function getToxicityScore(): ?float
    {
        return $this->toxicityScore;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
