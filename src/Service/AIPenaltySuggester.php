<?php

namespace App\Service;

use App\Entity\Book;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AIPenaltySuggester
{
    private const SUCCESS_TTL_SECONDS = 3600; // 1 hour
    private const FAILURE_TTL_SECONDS = 300; // 5 min
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $aiProvider = 'auto',
        private readonly string $geminiApiKey = '',
        private readonly string $geminiModel = 'gemini-1.5-flash-latest',
        private readonly string $openaiApiKey = '',
        private readonly string $openaiModel = 'gpt-4o-mini',
        private readonly float $dailyRate = 0.50,
        private readonly float $minAmount = 1.0,
        private readonly float $maxAmount = 10.0,
    ) {
    }

    /**
     * @param array<string, mixed> $memberHistory
     * Returns: ['amount' => float, 'reason' => string, 'confidence' => int (0-100)]
     */
    public function suggestPenalty(int $daysLate, array $memberHistory, ?Book $book = null): array
    {
        $cacheKey = $this->buildCacheKeyFromInputs($daysLate, $memberHistory, $book);
        return $this->suggestWithCacheKey($cacheKey, $daysLate, $memberHistory, $book);
    }

    /**
     * @param array<string, mixed> $memberHistory
     * Cache suggestion for 1 hour per loan ID.
     */
    public function suggestPenaltyForLoanId(int $loanId, int $daysLate, array $memberHistory, ?Book $book = null): array
    {
        $cacheKey = sprintf(
            'ai_penalty_suggestion_loan_%d_%s',
            $loanId,
            sha1($this->geminiModel . '|' . $this->openaiModel . '|' . $this->aiProvider)
        );

        return $this->suggestWithCacheKey($cacheKey, $daysLate, $memberHistory, $book);
    }

    public function fallbackSuggestion(int $daysLate): array
    {
        $amount = $this->calculateDefaultAmount($daysLate);

        return [
            'amount' => $amount,
            'reason' => 'Suggestion IA indisponible - montant calculé automatiquement.',
            'confidence' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $memberHistory
     */
    private function suggestWithCacheKey(string $cacheKey, int $daysLate, array $memberHistory, ?Book $book): array
    {
        $requestId = Uuid::uuid4()->toString();

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $cached = $item->get();
                if (\is_array($cached)) {
                    return $this->normalizeSuggestion($cached, $daysLate);
                }
            }
        } catch (CacheInvalidArgumentException|\Throwable $e) {
            $this->logger->error('AI penalty suggester cache read failed', [
                'request_id' => $requestId,
                'cache_key' => $cacheKey,
                'exception' => $e,
            ]);
        }

        $suggestion = $this->normalizeSuggestion(
            $this->suggestNow($requestId, $daysLate, $memberHistory, $book),
            $daysLate
        );

        $ttl = $suggestion['confidence'] > 0 ? self::SUCCESS_TTL_SECONDS : self::FAILURE_TTL_SECONDS;
        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($suggestion);
            $item->expiresAfter($ttl);
            $this->cache->save($item);
        } catch (CacheInvalidArgumentException|\Throwable $e) {
            $this->logger->error('AI penalty suggester cache write failed', [
                'request_id' => $requestId,
                'cache_key' => $cacheKey,
                'ttl' => $ttl,
                'exception' => $e,
            ]);
        }

        return $suggestion;
    }

    /**
     * @param array<string, mixed> $memberHistory
     */
    private function suggestNow(string $requestId, int $daysLate, array $memberHistory, ?Book $book): array
    {
        if ($daysLate <= 0) {
            return [
                'amount' => 0.0,
                'reason' => 'Aucune pénalité recommandée : aucun retard.',
                'confidence' => 90,
            ];
        }

        $providers = $this->providerPriority();
        $failures = [];

        foreach ($providers as $provider) {
            $result = match ($provider) {
                'gemini' => $this->callGemini($requestId, $daysLate, $memberHistory, $book),
                'openai' => $this->callOpenAi($requestId, $daysLate, $memberHistory, $book),
                default => $this->fallbackSuggestion($daysLate),
            };

            $normalized = $this->normalizeSuggestion($result, $daysLate);
            if (($normalized['confidence'] ?? 0) > 0) {
                return $normalized;
            }

            $failures[$provider] = $normalized['reason'] ?? 'indisponible';
        }

        if ($failures !== []) {
            $parts = [];
            foreach ($failures as $provider => $reason) {
                $parts[] = strtoupper((string) $provider) . ': ' . $this->stripUnavailablePrefix((string) $reason);
            }

            $fallback = $this->fallbackSuggestion($daysLate);
            $fallback['reason'] = 'Suggestion IA indisponible (' . implode(' ; ', $parts) . ') - montant calculé automatiquement.';
            return $fallback;
        }

        return $this->fallbackSuggestion($daysLate);
    }

    /**
     * @return array<int, 'gemini'|'openai'>
     */
    private function providerPriority(): array
    {
        $provider = strtolower(trim($this->aiProvider));

        return match ($provider) {
            'gemini' => ['gemini', 'openai'],
            'openai' => ['openai', 'gemini'],
            'auto', '' => ['gemini', 'openai'],
            default => ['gemini', 'openai'],
        };
    }

    /**
     * @param array<string, mixed> $memberHistory
     */
    private function callGemini(string $requestId, int $daysLate, array $memberHistory, ?Book $book): array
    {
        if (trim($this->geminiApiKey) === '') {
            $this->logger->error('Gemini API key missing', [
                'request_id' => $requestId,
                'provider' => 'gemini',
            ]);
            return $this->fallbackSuggestion($daysLate);
        }

        $configuredModel = $this->geminiModel !== '' ? $this->geminiModel : 'gemini-1.5-flash-latest';
        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->getSystemPrompt()],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $this->buildUserPrompt($daysLate, $memberHistory, $book)],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'maxOutputTokens' => 512,
                'responseMimeType' => 'application/json',
            ],
        ];

        $apiVersions = ['v1beta', 'v1'];
        $modelCandidates = $this->expandGeminiModelCandidates($configuredModel);

        foreach ($apiVersions as $apiVersion) {
            $cachedDiscovered = $this->getCachedDiscoveredGeminiModel($requestId, $apiVersion);
            if ($cachedDiscovered !== null) {
                $result = $this->callGeminiGenerateContent($requestId, $apiVersion, $cachedDiscovered, $payload);
                if (($result['confidence'] ?? 0) > 0) {
                    return $result;
                }
            }

            foreach ($modelCandidates as $modelCandidate) {
                $result = $this->callGeminiGenerateContent($requestId, $apiVersion, $modelCandidate, $payload);
                if (($result['confidence'] ?? 0) > 0) {
                    return $result;
                }

                if (($result['_gemini_try_next'] ?? false) === true) {
                    continue;
                }

                return $result;
            }

            $discovered = $this->discoverGeminiModel($requestId, $apiVersion);
            if ($discovered !== null && !in_array($discovered, $modelCandidates, true)) {
                $result = $this->callGeminiGenerateContent($requestId, $apiVersion, $discovered, $payload);
                if (($result['confidence'] ?? 0) > 0) {
                    return $result;
                }
            }
        }

        return $this->fallbackSuggestion($daysLate);
    }

    /**
     * @param array<string, mixed> $memberHistory
     */
    private function callOpenAi(string $requestId, int $daysLate, array $memberHistory, ?Book $book): array
    {
        if (trim($this->openaiApiKey) === '') {
            $this->logger->error('OpenAI API key missing', [
                'request_id' => $requestId,
                'provider' => 'openai',
            ]);
            return $this->fallbackSuggestion($daysLate);
        }

        $model = $this->openaiModel !== '' ? $this->openaiModel : 'gpt-4o-mini';
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $this->buildUserPrompt($daysLate, $memberHistory, $book)],
            ],
            'temperature' => 0.0,
            'max_tokens' => 200,
            'response_format' => ['type' => 'json_object'],
        ];

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->openaiApiKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => 15,
                    'max_duration' => 20,
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);

                if ($statusCode === 401 || $statusCode === 403) {
                    $this->logger->error('OpenAI auth error', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (clé OpenAI invalide).', 'confidence' => 0];
                }

                if ($statusCode === 429) {
                    $decoded = json_decode($body, true);
                    $errorCode = is_array($decoded) ? ($decoded['error']['code'] ?? null) : null;
                    if (is_string($errorCode) && $errorCode === 'insufficient_quota') {
                        $this->logger->error('OpenAI insufficient quota', [
                            'request_id' => $requestId,
                            'provider' => 'openai',
                            'model' => $model,
                            'status_code' => $statusCode,
                            'raw_response' => self::truncate($body),
                        ]);

                        return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (quota OpenAI insuffisant).', 'confidence' => 0];
                    }

                    $this->logger->error('OpenAI rate limited', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'raw_response' => self::truncate($body),
                    ]);
                    $this->sleepBackoff($attempt);
                    continue;
                }

                if ($statusCode >= 500) {
                    $this->logger->error('OpenAI server error', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'raw_response' => self::truncate($body),
                    ]);
                    $this->sleepBackoff($attempt);
                    continue;
                }

                if ($statusCode >= 400) {
                    $message = $this->extractProviderErrorMessage($body);
                    $this->logger->error('OpenAI client error', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'error_message' => $message,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (erreur API OpenAI).', 'confidence' => 0];
                }

                $json = json_decode($body, true);
                if (!\is_array($json)) {
                    $this->logger->error('OpenAI returned invalid JSON', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (réponse non exploitable).', 'confidence' => 0];
                }

                $text = $json['choices'][0]['message']['content'] ?? null;
                if (!\is_string($text) || trim($text) === '') {
                    $this->logger->error('OpenAI empty/unexpected response shape', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (réponse vide).', 'confidence' => 0];
                }

                $parsed = $this->extractJsonFromText($text);
                if ($parsed === null) {
                    $this->logger->error('OpenAI returned non-JSON content', [
                        'request_id' => $requestId,
                        'provider' => 'openai',
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                        'text' => self::truncate($text),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (réponse non exploitable).', 'confidence' => 0];
                }

                return $parsed;
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('OpenAI transport error', [
                    'request_id' => $requestId,
                    'provider' => 'openai',
                    'model' => $model,
                    'attempt' => $attempt,
                    'exception' => $e,
                ]);
                $this->sleepBackoff($attempt);
                continue;
            } catch (\Throwable $e) {
                $this->logger->error('OpenAI unexpected error', [
                    'request_id' => $requestId,
                    'provider' => 'openai',
                    'model' => $model,
                    'attempt' => $attempt,
                    'exception' => $e,
                ]);

                return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (erreur serveur).', 'confidence' => 0];
            }
        }

        return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (quota/limite atteinte).', 'confidence' => 0];
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un assistant de bibliothèque.
Tu dois renvoyer STRICTEMENT un unique objet JSON valide, sans Markdown, sans texte autour, sans phrase d'introduction.
Schéma obligatoire :
{
  "amount": number,
  "reason": "string",
  "confidence": 0-100
}
Règles :
- "amount" doit être un nombre en TND, avec 2 décimales max.
- "reason" doit être en français, courte, professionnelle, 1 phrase maximum.
- "reason" doit commencer par "Retard de X jours" (remplacer X) et peut ajouter un détail sur l'historique du membre.
- Ne jamais dépasser les règles de la bibliothèque fournies dans le contexte.
PROMPT;
    }

    /**
     * @param array<string, mixed> $memberHistory
     */
    private function buildUserPrompt(int $daysLate, array $memberHistory, ?Book $book): string
    {
        $rules = [
            'daily_rate_tnd' => round(max(0, $this->dailyRate), 2),
            'min_tnd' => round(max(0, $this->minAmount), 2),
            'max_tnd' => round(max(0, $this->maxAmount), 2),
        ];

        $bookContext = null;
        if ($book instanceof Book) {
            $bookContext = [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'category' => $book->getCategory()?->getName(),
                'status' => $book->getStatus(),
            ];
        }

        $context = [
            'days_late' => $daysLate,
            'member_history' => $memberHistory,
            'book' => $bookContext,
            'library_rules' => $rules,
        ];

        return "Tâche : suggérer un montant de pénalité et un motif.\n"
            . "Contexte (JSON) :\n"
            . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nRéponds STRICTEMENT en JSON selon le schéma.";
    }

    private function calculateDefaultAmount(int $daysLate): float
    {
        $raw = round(max(0, $daysLate) * max(0, $this->dailyRate), 2);
        $clamped = min(max($raw, $this->minAmount), $this->maxAmount);
        return round($clamped, 2);
    }

    /**
     * @param array<string, mixed> $suggestion
     * @return array{amount: float, reason: string, confidence: int}
     */
    private function normalizeSuggestion(array $suggestion, int $daysLate): array
    {
        $amountRaw = $suggestion['amount'] ?? null;
        $amount = is_numeric($amountRaw) ? (float) $amountRaw : $this->calculateDefaultAmount($daysLate);
        $amount = round($amount, 2);
        $amount = min(max($amount, 0.0), max($this->maxAmount, 0.0));

        $reason = trim((string) ($suggestion['reason'] ?? ''));
        if ($reason === '') {
            $reason = $this->fallbackSuggestion($daysLate)['reason'];
        }

        $confidenceRaw = $suggestion['confidence'] ?? 0;
        $confidence = is_numeric($confidenceRaw) ? (int) $confidenceRaw : 0;
        $confidence = max(0, min(100, $confidence));

        if ($confidence <= 0) {
            $fallback = $this->fallbackSuggestion($daysLate);
            return [
                'amount' => $fallback['amount'],
                'reason' => $fallback['reason'],
                'confidence' => 0,
            ];
        }

        $min = max(0.0, $this->minAmount);
        $max = max($min, $this->maxAmount);
        $amount = min(max($amount, $min), $max);

        if (!str_starts_with(strtolower($reason), 'retard de')) {
            $reason = sprintf('Retard de %d jours : %s', max(0, $daysLate), ltrim($reason, " \t\n\r\0\x0B:.-"));
        }

        return [
            'amount' => round($amount, 2),
            'reason' => $reason,
            'confidence' => $confidence,
        ];
    }

    private function extractJsonFromText(string $text): ?array
    {
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (\is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\\{.*\\})/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $sub = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($sub, true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function extractProviderErrorMessage(string $rawBody): ?string
    {
        $decoded = json_decode($rawBody, true);
        if (!\is_array($decoded)) {
            return null;
        }

        if (isset($decoded['error']['message']) && \is_string($decoded['error']['message'])) {
            return $decoded['error']['message'];
        }

        if (isset($decoded['message']) && \is_string($decoded['message'])) {
            return $decoded['message'];
        }

        return null;
    }

    private function sleepBackoff(int $attempt): void
    {
        $seconds = min(8, 1 << max(0, $attempt - 1));
        sleep($seconds);
    }

    private function stripUnavailablePrefix(string $reason): string
    {
        $reason = trim($reason);
        $prefix = 'Suggestion IA indisponible';
        if (str_starts_with($reason, $prefix)) {
            $reason = trim(substr($reason, strlen($prefix)));
            $reason = ltrim($reason, " \t\n\r\0\x0B:.-");
            $reason = trim($reason);
        }

        return $reason !== '' ? $reason : 'indisponible';
    }

    private static function truncate(string $value, int $max = 4000): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max) . '…';
    }

    /**
     * @return string[]
     */
    private function expandGeminiModelCandidates(string $model): array
    {
        $model = trim($model);
        if ($model === '') {
            $model = 'gemini-1.5-flash-latest';
        }

        $candidates = [$model];
        if ($model === 'gemini-1.5-flash') {
            $candidates[] = 'gemini-1.5-flash-latest';
        }
        if ($model === 'gemini-1.5-pro') {
            $candidates[] = 'gemini-1.5-pro-latest';
        }

        return array_values(array_unique($candidates));
    }

    private function normalizeGeminiPayloadForApiVersion(string $apiVersion, array $payload): array
    {
        if ($apiVersion !== 'v1') {
            return $payload;
        }

        $generationConfig = $payload['generationConfig'] ?? [];
        if (is_array($generationConfig)) {
            if (array_key_exists('maxOutputTokens', $generationConfig)) {
                $generationConfig['max_output_tokens'] = $generationConfig['maxOutputTokens'];
                unset($generationConfig['maxOutputTokens']);
            }
            if (array_key_exists('responseMimeType', $generationConfig)) {
                $generationConfig['response_mime_type'] = $generationConfig['responseMimeType'];
                unset($generationConfig['responseMimeType']);
            }
        } else {
            $generationConfig = [];
        }

        $normalized = [
            'contents' => $payload['contents'] ?? [],
            'generation_config' => $generationConfig,
        ];

        if (isset($payload['systemInstruction'])) {
            $normalized['system_instruction'] = $payload['systemInstruction'];
        }

        return $normalized;
    }

    private function callGeminiGenerateContent(string $requestId, string $apiVersion, string $model, array $payload): array
    {
        $payload = $this->normalizeGeminiPayloadForApiVersion($apiVersion, $payload);

        $url = sprintf(
            'https://generativelanguage.googleapis.com/%s/models/%s:generateContent',
            rawurlencode($apiVersion),
            rawurlencode($model)
        );

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'query' => ['key' => $this->geminiApiKey],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $payload,
                    'timeout' => 15,
                    'max_duration' => 20,
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getContent(false);

                if ($statusCode === 401 || $statusCode === 403) {
                    $this->logger->error('Gemini auth error', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (clé Gemini invalide).', 'confidence' => 0];
                }

                if ($statusCode === 404) {
                    $message = $this->extractProviderErrorMessage($body);
                    $this->logger->error('Gemini model not found or unsupported', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'error_message' => $message,
                        'raw_response' => self::truncate($body),
                    ]);

                    return [
                        'amount' => 0,
                        'reason' => 'Suggestion IA indisponible (modèle Gemini non supporté).',
                        'confidence' => 0,
                        '_gemini_try_next' => true,
                    ];
                }

                if ($statusCode === 429) {
                    $this->logger->error('Gemini rate limited', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'raw_response' => self::truncate($body),
                    ]);
                    $this->sleepBackoff($attempt);
                    continue;
                }

                if ($statusCode >= 500) {
                    $this->logger->error('Gemini server error', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'attempt' => $attempt,
                        'raw_response' => self::truncate($body),
                    ]);
                    $this->sleepBackoff($attempt);
                    continue;
                }

                if ($statusCode >= 400) {
                    $message = $this->extractProviderErrorMessage($body);
                    $this->logger->error('Gemini client error', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'error_message' => $message,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (erreur API Gemini).', 'confidence' => 0];
                }

                $json = json_decode($body, true);
                if (!\is_array($json)) {
                    $this->logger->error('Gemini returned invalid JSON', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (réponse non exploitable).', 'confidence' => 0];
                }

                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (!\is_string($text) || trim($text) === '') {
                    $this->logger->error('Gemini empty/unexpected response shape', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'raw_response' => self::truncate($body),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (réponse vide).', 'confidence' => 0];
                }

                $parsed = $this->extractJsonFromText($text);
                if ($parsed === null) {
                    $finishReason = $json['candidates'][0]['finishReason'] ?? null;
                    $this->logger->error('Gemini returned non-JSON content', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'model' => $model,
                        'status_code' => $statusCode,
                        'finish_reason' => $finishReason,
                        'raw_response' => self::truncate($body),
                        'text' => self::truncate((string) $text),
                    ]);

                    return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (réponse non exploitable).', 'confidence' => 0];
                }

                return $parsed;
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Gemini transport error', [
                    'request_id' => $requestId,
                    'provider' => 'gemini',
                    'api_version' => $apiVersion,
                    'model' => $model,
                    'attempt' => $attempt,
                    'exception' => $e,
                ]);
                $this->sleepBackoff($attempt);
                continue;
            } catch (\Throwable $e) {
                $this->logger->error('Gemini unexpected error', [
                    'request_id' => $requestId,
                    'provider' => 'gemini',
                    'api_version' => $apiVersion,
                    'model' => $model,
                    'attempt' => $attempt,
                    'exception' => $e,
                ]);

                return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (erreur serveur).', 'confidence' => 0];
            }
        }

        return ['amount' => 0, 'reason' => 'Suggestion IA indisponible (quota/limite atteinte).', 'confidence' => 0];
    }

    private function discoverGeminiModel(string $requestId, string $apiVersion): ?string
    {
        $cacheKey = 'ai_gemini_models_' . $apiVersion . '_' . sha1($this->geminiApiKey);

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $value = $item->get();
                return is_string($value) && $value !== '' ? $value : null;
            }
        } catch (CacheInvalidArgumentException|\Throwable $e) {
            $this->logger->error('Gemini model discovery cache read failed', [
                'request_id' => $requestId,
                'provider' => 'gemini',
                'api_version' => $apiVersion,
                'exception' => $e,
            ]);
        }

        $url = sprintf('https://generativelanguage.googleapis.com/%s/models', rawurlencode($apiVersion));

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => ['key' => $this->geminiApiKey],
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 10,
                'max_duration' => 15,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);

            if ($statusCode >= 400) {
                $this->logger->error('Gemini ListModels failed', [
                    'request_id' => $requestId,
                    'provider' => 'gemini',
                    'api_version' => $apiVersion,
                    'status_code' => $statusCode,
                    'raw_response' => self::truncate($body),
                ]);
                return null;
            }

            $decoded = json_decode($body, true);
            if (!is_array($decoded) || !isset($decoded['models']) || !is_array($decoded['models'])) {
                $this->logger->error('Gemini ListModels invalid response', [
                    'request_id' => $requestId,
                    'provider' => 'gemini',
                    'api_version' => $apiVersion,
                    'raw_response' => self::truncate($body),
                ]);
                return null;
            }

            $candidates = [];
            foreach ($decoded['models'] as $modelItem) {
                if (!is_array($modelItem)) {
                    continue;
                }

                $name = $modelItem['name'] ?? null;
                $methods = $modelItem['supportedGenerationMethods'] ?? null;
                if (!is_string($name) || $name === '' || !is_array($methods)) {
                    continue;
                }

                if (!in_array('generateContent', $methods, true)) {
                    continue;
                }

                $shortName = str_starts_with($name, 'models/') ? substr($name, strlen('models/')) : $name;
                $candidates[] = $shortName;
            }

            $pick = $this->pickBestGeminiModel($candidates);
            if ($pick !== null) {
                try {
                    $item = $this->cache->getItem($cacheKey);
                    $item->set($pick);
                    $item->expiresAfter(86400);
                    $this->cache->save($item);
                } catch (CacheInvalidArgumentException|\Throwable $e) {
                    $this->logger->error('Gemini model discovery cache write failed', [
                        'request_id' => $requestId,
                        'provider' => 'gemini',
                        'api_version' => $apiVersion,
                        'exception' => $e,
                    ]);
                }
            }

            return $pick;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Gemini ListModels transport error', [
                'request_id' => $requestId,
                'provider' => 'gemini',
                'api_version' => $apiVersion,
                'exception' => $e,
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Gemini ListModels unexpected error', [
                'request_id' => $requestId,
                'provider' => 'gemini',
                'api_version' => $apiVersion,
                'exception' => $e,
            ]);
            return null;
        }
    }

    private function getCachedDiscoveredGeminiModel(string $requestId, string $apiVersion): ?string
    {
        $cacheKey = 'ai_gemini_models_' . $apiVersion . '_' . sha1($this->geminiApiKey);

        try {
            $item = $this->cache->getItem($cacheKey);
            if (!$item->isHit()) {
                return null;
            }

            $value = $item->get();
            return is_string($value) && $value !== '' ? $value : null;
        } catch (CacheInvalidArgumentException|\Throwable $e) {
            $this->logger->error('Gemini discovered model cache read failed', [
                'request_id' => $requestId,
                'provider' => 'gemini',
                'api_version' => $apiVersion,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * @param string[] $models
     */
    private function pickBestGeminiModel(array $models): ?string
    {
        if ($models === []) {
            return null;
        }

        foreach ($models as $name) {
            $lower = strtolower($name);
            if (str_contains($lower, 'flash') && str_contains($lower, '1.5')) {
                return $name;
            }
        }

        foreach ($models as $name) {
            if (str_contains(strtolower($name), 'flash')) {
                return $name;
            }
        }

        return $models[0];
    }

    private function buildCacheKeyFromInputs(int $daysLate, array $memberHistory, ?Book $book): string
    {
        $payload = [
            'daysLate' => $daysLate,
            'history' => $memberHistory,
            'book' => $book?->getId(),
            'provider' => $this->aiProvider,
            'models' => [$this->geminiModel, $this->openaiModel],
            'rules' => [$this->dailyRate, $this->minAmount, $this->maxAmount],
        ];

        return 'ai_penalty_suggestion_' . sha1((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
