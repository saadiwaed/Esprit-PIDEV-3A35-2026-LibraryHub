<?php

namespace App\Service;

use App\Entity\RenewalRequest;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AIRenewalSuggester
{
    private const SUCCESS_TTL_SECONDS = 1800; // 30 minutes
    private const FAILURE_TTL_SECONDS = 60; // avoid spamming provider on every refresh
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly int $maxRenewals = 3,
        private readonly string $aiProvider = 'auto',
        private readonly string $geminiApiKey = '',
        private readonly string $geminiModel = 'gemini-1.5-flash',
        private readonly string $openaiApiKey = '',
        private readonly string $openaiModel = 'gpt-4o-mini',
    ) {
    }

    /**
     * Always returns:
     *  - recommendation: 'approve'|'refuse'|'unknown'
     *  - reason: string (FR)
     *  - confidence: int 0-100
     */
    public function getSuggestion(RenewalRequest $renewalRequest): array
    {
        $requestId = Uuid::uuid4()->toString();
        $cacheKey = $this->buildCacheKey($renewalRequest);

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $cached = $item->get();
                if (\is_array($cached)) {
                    return $this->normalizeSuggestion($cached);
                }
            }
        } catch (CacheInvalidArgumentException|\Throwable $e) {
            $this->logger->error('AI suggester cache read failed', [
                'request_id' => $requestId,
                'rr_id' => $renewalRequest->getId(),
                'cache_key' => $cacheKey,
                'exception' => $e,
            ]);
        }

        $suggestion = $this->normalizeSuggestion($this->suggestNow($renewalRequest, $requestId));

        $ttl = $suggestion['recommendation'] === 'unknown'
            ? self::FAILURE_TTL_SECONDS
            : self::SUCCESS_TTL_SECONDS;

        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($suggestion);
            $item->expiresAfter($ttl);
            $this->cache->save($item);
        } catch (CacheInvalidArgumentException|\Throwable $e) {
            $this->logger->error('AI suggester cache write failed', [
                'request_id' => $requestId,
                'rr_id' => $renewalRequest->getId(),
                'cache_key' => $cacheKey,
                'ttl' => $ttl,
                'exception' => $e,
            ]);
        }

        return $suggestion;
    }

    private function suggestNow(RenewalRequest $renewalRequest, string $requestId): array
    {
        $loan = $renewalRequest->getLoan();
        if ($loan === null) {
            return $this->aiUnavailableResponse('Suggestion IA indisponible (emprunt introuvable).');
        }

        // Deterministic refusals: avoids unnecessary LLM calls and guarantees a precise cause.
        if ($loan->maxRenewalsReached($this->maxRenewals)) {
            return [
                'recommendation' => 'refuse',
                'reason' => sprintf(
                    'Refuser : %d renouvellements déjà effectués (maximum : %d).',
                    $loan->getRenewalCount(),
                    $this->maxRenewals
                ),
                'confidence' => 100,
            ];
        }

        if (!$loan->canBeRenewed()) {
            return [
                'recommendation' => 'refuse',
                'reason' => 'Refuser : l’emprunt n’est pas renouvelable (déjà retourné ou statut incompatible).',
                'confidence' => 95,
            ];
        }

        $providers = $this->providerPriority();
        $failures = [];
        foreach ($providers as $provider) {
            $result = match ($provider) {
                'gemini' => $this->callGemini($renewalRequest, $requestId),
                'openai' => $this->callOpenAi($renewalRequest, $requestId),
                default => $this->aiUnavailableResponse('Suggestion IA indisponible (provider non supporté).'),
            };

            $normalized = $this->normalizeSuggestion($result);
            if ($normalized['recommendation'] !== 'unknown') {
                return $normalized;
            }

            $failures[$provider] = $normalized['reason'];
        }

        if ($failures !== []) {
            $reasonParts = [];
            foreach ($failures as $provider => $reason) {
                $reasonParts[] = strtoupper((string) $provider) . ': ' . $this->stripUnavailablePrefix($reason);
            }

            return $this->aiUnavailableResponse('Suggestion IA indisponible (' . implode(' ; ', $reasonParts) . ').');
        }

        return $this->aiUnavailableResponse('Suggestion IA indisponible (erreur serveur).');
    }

    private function buildCacheKey(RenewalRequest $renewalRequest): string
    {
        $loan = $renewalRequest->getLoan();

        $keyPayload = [
            'rr' => $renewalRequest->getId(),
            'requestedAt' => $renewalRequest->getRequestedAt()?->format(\DateTimeInterface::ATOM),
            'notes' => $renewalRequest->getNotes(),
            'loan' => [
                'id' => $loan?->getId(),
                'due' => $loan?->getDueDate()?->format('Y-m-d'),
                'renewals' => $loan?->getRenewalCount(),
                'status' => $loan?->getStatus()?->name,
                'unpaid_penalties' => $loan ? $loan->getTotalUnpaidPenalty() : null,
            ],
            'provider' => strtolower(trim($this->aiProvider)),
            'models' => [
                'gemini' => $this->geminiModel,
                'openai' => $this->openaiModel,
            ],
            'maxRenewals' => $this->maxRenewals,
        ];

        $hash = sha1((string) json_encode($keyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return sprintf('ai_renewal_suggestion_rr_%d_%s', (int) ($renewalRequest->getId() ?? 0), $hash);
    }

    private function aiUnavailableResponse(string $reason): array
    {
        return [
            'recommendation' => 'unknown',
            'reason' => $reason,
            'confidence' => 0,
        ];
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

    private function callGemini(RenewalRequest $renewalRequest, string $requestId): array
    {
        if (trim($this->geminiApiKey) === '') {
            $this->logger->error('Gemini API key missing', [
                'request_id' => $requestId,
                'provider' => 'gemini',
                'rr_id' => $renewalRequest->getId(),
            ]);
            return $this->aiUnavailableResponse('Suggestion IA indisponible (clé GEMINI_API_KEY manquante).');
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
                        ['text' => $this->buildUserPrompt($renewalRequest)],
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
                $result = $this->callGeminiGenerateContent($renewalRequest, $requestId, $apiVersion, $cachedDiscovered, $payload);
                if (($result['recommendation'] ?? 'unknown') !== 'unknown') {
                    return $result;
                }
            }

            foreach ($modelCandidates as $modelCandidate) {
                $result = $this->callGeminiGenerateContent($renewalRequest, $requestId, $apiVersion, $modelCandidate, $payload);
                if (($result['recommendation'] ?? 'unknown') !== 'unknown') {
                    return $result;
                }

                if (($result['_gemini_try_next'] ?? false) === true) {
                    continue;
                }

                return $result;
            }

            $discovered = $this->discoverGeminiModel($requestId, $apiVersion);
            if ($discovered !== null && !in_array($discovered, $modelCandidates, true)) {
                $result = $this->callGeminiGenerateContent($renewalRequest, $requestId, $apiVersion, $discovered, $payload);
                if (($result['recommendation'] ?? 'unknown') !== 'unknown') {
                    return $result;
                }
            }
        }

        return $this->aiUnavailableResponse('Suggestion IA indisponible (Gemini indisponible).');
    }

    private function callOpenAi(RenewalRequest $renewalRequest, string $requestId): array
    {
        if (trim($this->openaiApiKey) === '') {
            $this->logger->error('OpenAI API key missing', [
                'request_id' => $requestId,
                'provider' => 'openai',
                'rr_id' => $renewalRequest->getId(),
            ]);
            return $this->aiUnavailableResponse('Suggestion IA indisponible (clé OPENAI_API_KEY manquante).');
        }

        $model = $this->openaiModel !== '' ? $this->openaiModel : 'gpt-4o-mini';
        $url = 'https://api.openai.com/v1/chat/completions';
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $this->buildUserPrompt($renewalRequest)],
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

                    return $this->aiUnavailableResponse('Suggestion IA indisponible (clé OpenAI invalide ou accès refusé).');
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

                        return $this->aiUnavailableResponse('Suggestion IA indisponible (quota OpenAI insuffisant).');
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

                    return $this->aiUnavailableResponse('Suggestion IA indisponible (erreur API OpenAI).');
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

                    return $this->aiUnavailableResponse('Suggestion IA indisponible (réponse non exploitable).');
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

                    return $this->aiUnavailableResponse('Suggestion IA indisponible (réponse vide).');
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

                    return $this->aiUnavailableResponse('Suggestion IA indisponible (réponse non exploitable).');
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

                return $this->aiUnavailableResponse('Suggestion IA indisponible (erreur serveur).');
            }
        }

        return $this->aiUnavailableResponse('Suggestion IA indisponible (quota/limite atteinte).');
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

    private function normalizeSuggestion(array $suggestion): array
    {
        $recommendation = strtolower(trim((string) ($suggestion['recommendation'] ?? 'unknown')));
        if (!\in_array($recommendation, ['approve', 'refuse', 'unknown'], true)) {
            $recommendation = 'unknown';
        }

        $reason = trim((string) ($suggestion['reason'] ?? ''));
        if ($reason === '') {
            $recommendation = 'unknown';
            $reason = 'Suggestion IA indisponible (réponse non exploitable).';
        }

        if ($recommendation === 'approve' && !str_starts_with(strtolower($reason), 'accepter')) {
            $reason = 'Accepter : ' . $reason;
        }
        if ($recommendation === 'refuse' && !str_starts_with(strtolower($reason), 'refuser')) {
            $reason = 'Refuser : ' . $reason;
        }

        $confidenceRaw = $suggestion['confidence'] ?? 0;
        $confidence = is_numeric($confidenceRaw) ? (int) $confidenceRaw : 0;
        $confidence = max(0, min(100, $confidence));
        if ($recommendation === 'unknown') {
            $confidence = 0;
        }

        return [
            'recommendation' => $recommendation,
            'reason' => $reason,
            'confidence' => $confidence,
        ];
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Vous êtes un assistant de bibliothèque.
Tu dois renvoyer STRICTEMENT un unique objet JSON valide, sans Markdown, sans balises, sans texte autour.
Schéma obligatoire :
{
  "recommendation": "approve" | "refuse",
  "reason": "string",
  "confidence": 0-100
}
Règles :
- "reason" doit être en français, courte, professionnelle, 1 phrase maximum.
- Si recommendation="refuse" : "reason" DOIT commencer par "Refuser : " et préciser la cause exacte avec chiffres si possible.
- Si recommendation="approve" : "reason" DOIT commencer par "Accepter : " et donner une raison positive.
- Ne jamais inventer des données absentes. Si une information est inconnue, l’ignorer.
PROMPT;
    }

    private function buildUserPrompt(RenewalRequest $renewalRequest): string
    {
        $loan = $renewalRequest->getLoan();
        $member = $renewalRequest->getMember();

        $context = [
            'renewal_request' => [
                'id' => $renewalRequest->getId(),
                'requested_at' => $renewalRequest->getRequestedAt()?->format(\DateTimeInterface::ATOM),
                'notes' => $renewalRequest->getNotes(),
                'status' => $renewalRequest->getStatus(),
            ],
            'member' => [
                'id' => method_exists($member, 'getId') ? $member->getId() : null,
                'name' => method_exists($member, 'getFullName')
                    ? $member->getFullName()
                    : trim((string) (($member?->getFirstName() ?? '') . ' ' . ($member?->getLastName() ?? ''))),
            ],
            'loan' => [
                'id' => $loan?->getId(),
                'status' => $loan?->getStatus()?->name,
                'checkout_time' => $loan?->getCheckoutTime()?->format(\DateTimeInterface::ATOM),
                'due_date' => $loan?->getDueDate()?->format('Y-m-d'),
                'renewal_count' => $loan?->getRenewalCount(),
                'max_renewals' => $this->maxRenewals,
                'overdue_days_now' => $loan?->getOverdueDays(new \DateTimeImmutable()),
                'total_unpaid_penalties_amount' => $loan?->getTotalUnpaidPenalty(),
                'loan_notes' => $loan?->getNotes(),
                'book_copy_id' => $loan?->getBookCopy()?->getId(),
            ],
        ];

        return "Décide si la demande de renouvellement doit être acceptée ou refusée.\n"
            . "Contexte (JSON) :\n"
            . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nRéponds STRICTEMENT en JSON selon le schéma.";
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

    private function callGeminiGenerateContent(
        RenewalRequest $renewalRequest,
        string $requestId,
        string $apiVersion,
        string $model,
        array $payload
    ): array {
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

                    return $this->aiUnavailableResponse('Suggestion IA indisponible (clé Gemini invalide ou accès refusé).');
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

                    $res = $this->aiUnavailableResponse('Gemini : modèle introuvable/non supporté (' . $model . ', ' . $apiVersion . ')');
                    $res['_gemini_try_next'] = true;
                    return $res;
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

                    return $this->aiUnavailableResponse('Gemini : erreur API.');
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

                    return $this->aiUnavailableResponse('Gemini : réponse non exploitable.');
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

                    return $this->aiUnavailableResponse('Gemini : réponse vide.');
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
                        'text' => self::truncate($text),
                    ]);

                    return $this->aiUnavailableResponse('Gemini : réponse non exploitable.');
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

                return $this->aiUnavailableResponse('Gemini : erreur serveur.');
            }
        }

        return $this->aiUnavailableResponse('Gemini : quota/limite atteinte.');
    }

    private function normalizeGeminiPayloadForApiVersion(string $apiVersion, array $payload): array
    {
        // v1beta currently accepts camelCase; v1 expects snake_case.
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
            foreach ($decoded['models'] as $model) {
                if (!is_array($model)) {
                    continue;
                }

                $name = $model['name'] ?? null;
                $methods = $model['supportedGenerationMethods'] ?? null;
                if (!is_string($name) || $name === '' || !is_array($methods)) {
                    continue;
                }

                if (!in_array('generateContent', $methods, true)) {
                    continue;
                }

                // ListModels returns "models/xxx" -> we want "xxx"
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

        $preferred = null;
        foreach ($models as $name) {
            $lower = strtolower($name);
            if (str_contains($lower, 'flash') && str_contains($lower, '1.5')) {
                $preferred = $name;
                break;
            }
        }

        if ($preferred === null) {
            foreach ($models as $name) {
                if (str_contains(strtolower($name), 'flash')) {
                    $preferred = $name;
                    break;
                }
            }
        }

        return $preferred ?? $models[0];
    }
}
