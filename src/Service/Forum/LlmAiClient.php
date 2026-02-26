<?php

namespace App\Service\Forum;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LlmAiClient
{
    private bool $missingConfigurationLogged = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled,
        private readonly string $provider,
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly float $timeoutSeconds,
        private readonly float $temperature,
        private readonly int $maxTokens
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requestJson(string $feature, string $systemPrompt, string $userPrompt): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        if (!$this->isConfigured()) {
            $this->logMissingConfigurationOnce();

            return null;
        }

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => $this->temperature,
                    'max_tokens' => $this->maxTokens,
                    'response_format' => ['type' => 'json_object'],
                ],
                'timeout' => $this->timeoutSeconds,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->warning('Forum AI provider returned an HTTP error.', [
                    'feature' => $feature,
                    'provider' => $this->provider,
                    'status_code' => $statusCode,
                ]);

                return null;
            }

            $payload = json_decode($response->getContent(false), true);
            if (!is_array($payload)) {
                $this->logger->warning('Forum AI provider returned an invalid JSON payload.', [
                    'feature' => $feature,
                    'provider' => $this->provider,
                ]);

                return null;
            }

            $rawContent = $this->extractMessageContent($payload);
            if ($rawContent === null || $rawContent === '') {
                $this->logger->warning('Forum AI provider did not return a usable message content.', [
                    'feature' => $feature,
                    'provider' => $this->provider,
                ]);

                return null;
            }

            $decoded = json_decode($rawContent, true);
            if (!is_array($decoded)) {
                $this->logger->warning('Forum AI provider message content is not valid JSON.', [
                    'feature' => $feature,
                    'provider' => $this->provider,
                ]);

                return null;
            }

            $this->logger->info('Forum AI provider response received.', [
                'feature' => $feature,
                'provider' => $this->provider,
                'model' => $this->model,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);

            return $decoded;
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Forum AI provider call failed.', [
                'feature' => $feature,
                'provider' => $this->provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function isConfigured(): bool
    {
        return trim($this->endpoint) !== '' && trim($this->apiKey) !== '' && trim($this->model) !== '';
    }

    private function logMissingConfigurationOnce(): void
    {
        if ($this->missingConfigurationLogged) {
            return;
        }

        $this->missingConfigurationLogged = true;
        $this->logger->warning('Forum AI assistant is disabled due to missing configuration.', [
            'provider' => $this->provider,
            'endpoint_configured' => trim($this->endpoint) !== '',
            'api_key_configured' => trim($this->apiKey) !== '',
            'model_configured' => trim($this->model) !== '',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMessageContent(array $payload): ?string
    {
        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (is_string($content)) {
            return trim($content);
        }

        if (!is_array($content)) {
            return null;
        }

        $parts = [];
        foreach ($content as $chunk) {
            if (is_array($chunk) && isset($chunk['text']) && is_string($chunk['text'])) {
                $parts[] = $chunk['text'];
            }
        }

        return trim(implode("\n", $parts));
    }
}
