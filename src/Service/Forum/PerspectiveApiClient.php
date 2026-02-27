<?php

namespace App\Service\Forum;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PerspectiveApiClient
{
    private bool $missingConfigurationLogged = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly float $timeoutSeconds
    ) {
    }

    public function analyzeToxicity(string $content): ?float
    {
        $content = trim($content);
        if ($content === '') {
            return 0.0;
        }

        if (!$this->isConfigured()) {
            $this->logMissingConfigurationOnce();

            return null;
        }

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'comment' => ['text' => $content],
                    'languages' => ['fr', 'en'],
                    'requestedAttributes' => [
                        'TOXICITY' => (object) [],
                    ],
                    'doNotStore' => true,
                ],
                'timeout' => $this->timeoutSeconds,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->warning('Perspective API returned an HTTP error.', [
                    'status_code' => $statusCode,
                ]);

                return null;
            }

            $payload = json_decode($response->getContent(false), true);
            if (!is_array($payload)) {
                $this->logger->warning('Perspective API returned an invalid payload.');

                return null;
            }

            $score = $payload['attributeScores']['TOXICITY']['summaryScore']['value'] ?? null;
            if (!is_numeric($score)) {
                $this->logger->warning('Perspective API did not return a valid toxicity score.');

                return null;
            }

            return max(0.0, min(1.0, (float) $score));
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Perspective API call failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function isConfigured(): bool
    {
        return trim($this->endpoint) !== '' && trim($this->apiKey) !== '';
    }

    private function logMissingConfigurationOnce(): void
    {
        if ($this->missingConfigurationLogged) {
            return;
        }

        $this->missingConfigurationLogged = true;
        $this->logger->warning('Perspective API moderation is disabled due to missing configuration.', [
            'endpoint_configured' => trim($this->endpoint) !== '',
            'api_key_configured' => trim($this->apiKey) !== '',
        ]);
    }
}
