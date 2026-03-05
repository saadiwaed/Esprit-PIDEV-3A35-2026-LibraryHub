<?php

namespace App\Service\Forum;

final class ForumAiResult
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly bool $usedAi,
        private readonly bool $fallbackUsed,
        private readonly float $confidence,
        private readonly string $message
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function usedAi(): bool
    {
        return $this->usedAi;
    }

    public function isFallbackUsed(): bool
    {
        return $this->fallbackUsed;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
