<?php

namespace App\Service\Forum;

use Psr\Log\LoggerInterface;

final class ForumAiAssistantService
{
    public function __construct(
        private readonly LlmAiClient $llmAiClient,
        private readonly LoggerInterface $logger,
        private readonly int $minInputChars,
        private readonly float $confidenceThreshold,
        private readonly int $maxContextComments
    ) {
    }

    public function generateCommunityDraft(
        ?string $name,
        ?string $purpose,
        ?string $description = null,
        ?string $rules = null,
        ?string $welcomeMessage = null
    ): ForumAiResult {
        $normalizedName = $this->normalizeText($name, 120);
        $normalizedPurpose = $this->normalizeText($purpose, 280);
        $normalizedDescription = $this->normalizeText($description, 1800);
        $normalizedRules = $this->normalizeText($rules, 2800);
        $normalizedWelcomeMessage = $this->normalizeText($welcomeMessage, 800);

        $fallbackPayload = $this->buildCommunityFallback(
            $normalizedName,
            $normalizedPurpose,
            $normalizedDescription,
            $normalizedRules,
            $normalizedWelcomeMessage
        );

        if ($this->signalLength([
            $normalizedName,
            $normalizedPurpose,
            $normalizedDescription,
            $normalizedRules,
            $normalizedWelcomeMessage,
        ]) < $this->minInputChars) {
            return $this->fallbackResult('community_draft', $fallbackPayload, 'input_too_short');
        }

        $systemPrompt = <<<PROMPT
Tu es un assistant de redaction pour un forum communautaire.
Retourne uniquement un objet JSON valide avec exactement ces cles:
- purpose: string
- description: string
- rules: string
- welcomeMessage: string
- confidence: number entre 0 et 1
Style: francais professionnel, clair, respectueux.
Interdit: markdown, texte hors JSON.
PROMPT;

        $userPrompt = sprintf(
            "Nom: %s\nObjectif: %s\nDescription actuelle: %s\nRegles actuelles: %s\nMessage de bienvenue actuel: %s",
            $normalizedName !== '' ? $normalizedName : '(vide)',
            $normalizedPurpose !== '' ? $normalizedPurpose : '(vide)',
            $normalizedDescription !== '' ? $normalizedDescription : '(vide)',
            $normalizedRules !== '' ? $normalizedRules : '(vide)',
            $normalizedWelcomeMessage !== '' ? $normalizedWelcomeMessage : '(vide)'
        );

        $payload = $this->llmAiClient->requestJson('community_draft', $systemPrompt, $userPrompt);
        if ($payload === null) {
            return $this->fallbackResult('community_draft', $fallbackPayload, 'provider_unavailable');
        }

        $candidate = [
            'purpose' => $this->normalizeText($payload['purpose'] ?? null, 280),
            'description' => $this->normalizeText($payload['description'] ?? null, 1800),
            'rules' => $this->normalizeText($payload['rules'] ?? null, 2800),
            'welcomeMessage' => $this->normalizeText($payload['welcomeMessage'] ?? null, 800),
        ];

        if ($candidate['purpose'] === '') {
            $candidate['purpose'] = $fallbackPayload['purpose'];
        }
        if ($candidate['description'] === '') {
            $candidate['description'] = $fallbackPayload['description'];
        }
        if ($candidate['rules'] === '') {
            $candidate['rules'] = $fallbackPayload['rules'];
        }
        if ($candidate['welcomeMessage'] === '') {
            $candidate['welcomeMessage'] = $fallbackPayload['welcomeMessage'];
        }

        $confidence = $this->extractConfidence($payload, 0.72);
        if ($confidence < $this->confidenceThreshold) {
            return $this->fallbackResult('community_draft', $fallbackPayload, 'low_confidence', $confidence);
        }

        return new ForumAiResult(
            $candidate,
            true,
            false,
            $confidence,
            'Suggestion IA generee pour la communaute.'
        );
    }

    public function improvePostDraft(?string $title, ?string $content, ?string $communityName = null): ForumAiResult
    {
        $normalizedTitle = $this->normalizeText($title, 255);
        $normalizedContent = $this->normalizeText($content, 9000);
        $normalizedCommunityName = $this->normalizeText($communityName, 120);

        $fallbackPayload = $this->buildPostFallback($normalizedTitle, $normalizedContent, $normalizedCommunityName);
        if ($this->signalLength([$normalizedTitle, $normalizedContent]) < $this->minInputChars) {
            return $this->fallbackResult('post_draft', $fallbackPayload, 'input_too_short');
        }

        $systemPrompt = <<<PROMPT
Tu es un assistant de redaction de post de forum.
Retourne uniquement un objet JSON valide avec exactement ces cles:
- title: string
- content: string
- confidence: number entre 0 et 1
Contraintes:
- title <= 255 caracteres
- content structure en paragraphes clairs et constructifs
- pas de markdown exotique
- pas de texte hors JSON
PROMPT;

        $userPrompt = sprintf(
            "Communaute: %s\nTitre actuel: %s\nContenu actuel: %s",
            $normalizedCommunityName !== '' ? $normalizedCommunityName : '(non specifiee)',
            $normalizedTitle !== '' ? $normalizedTitle : '(vide)',
            $normalizedContent !== '' ? $normalizedContent : '(vide)'
        );

        $payload = $this->llmAiClient->requestJson('post_draft', $systemPrompt, $userPrompt);
        if ($payload === null) {
            return $this->fallbackResult('post_draft', $fallbackPayload, 'provider_unavailable');
        }

        $candidate = [
            'title' => $this->normalizeText($payload['title'] ?? null, 255),
            'content' => $this->normalizeText($payload['content'] ?? null, 9000),
        ];

        if ($candidate['title'] === '') {
            $candidate['title'] = $fallbackPayload['title'];
        }
        if ($candidate['content'] === '') {
            $candidate['content'] = $fallbackPayload['content'];
        }

        $confidence = $this->extractConfidence($payload, 0.7);
        if ($confidence < $this->confidenceThreshold) {
            return $this->fallbackResult('post_draft', $fallbackPayload, 'low_confidence', $confidence);
        }

        $this->logger->info('Forum AI post draft result prepared.', [
            'source' => 'ai',
            'content_changed' => $candidate['content'] !== $normalizedContent,
            'title_changed' => $candidate['title'] !== $normalizedTitle,
            'content_length' => $this->stringLength($candidate['content']),
        ]);

        return new ForumAiResult(
            $candidate,
            true,
            false,
            $confidence,
            'Suggestion IA generee pour le post.'
        );
    }

    /**
     * @param array<int, string> $commentSnippets
     */
    public function suggestComment(
        ?string $postTitle,
        ?string $postContent,
        array $commentSnippets = [],
        ?string $draft = null
    ): ForumAiResult {
        $normalizedPostTitle = $this->normalizeText($postTitle, 255);
        $normalizedPostContent = $this->normalizeText($postContent, 2000);
        $normalizedDraft = $this->normalizeText($draft, 2000);
        $normalizedComments = $this->normalizeStringList($commentSnippets, $this->maxContextComments, 280);

        $fallbackPayload = $this->buildCommentFallback(
            $normalizedPostTitle,
            $normalizedDraft,
            count($normalizedComments)
        );

        if ($this->signalLength([$normalizedPostTitle, $normalizedPostContent, $normalizedDraft]) < $this->minInputChars) {
            return $this->fallbackResult('comment_suggest', $fallbackPayload, 'input_too_short');
        }

        $systemPrompt = <<<PROMPT
Tu es un assistant qui propose une reponse constructive pour un forum.
Retourne uniquement un objet JSON valide avec exactement ces cles:
- suggestion: string
- confidence: number entre 0 et 1
Contraintes:
- ton cordial, respectueux, utile
- suggestion concise (max ~1200 caracteres)
- pas de texte hors JSON
PROMPT;

        $userPrompt = sprintf(
            "Titre du post: %s\nContenu du post: %s\nCommentaires recents: %s\nBrouillon utilisateur: %s",
            $normalizedPostTitle !== '' ? $normalizedPostTitle : '(vide)',
            $normalizedPostContent !== '' ? $normalizedPostContent : '(vide)',
            $normalizedComments !== [] ? implode(' || ', $normalizedComments) : '(aucun)',
            $normalizedDraft !== '' ? $normalizedDraft : '(vide)'
        );

        $payload = $this->llmAiClient->requestJson('comment_suggest', $systemPrompt, $userPrompt);
        if ($payload === null) {
            return $this->fallbackResult('comment_suggest', $fallbackPayload, 'provider_unavailable');
        }

        $candidateSuggestion = $this->normalizeText($payload['suggestion'] ?? null, 1200);
        if ($candidateSuggestion === '') {
            $candidateSuggestion = $fallbackPayload['suggestion'];
        }

        $confidence = $this->extractConfidence($payload, 0.69);
        if ($confidence < $this->confidenceThreshold) {
            return $this->fallbackResult('comment_suggest', $fallbackPayload, 'low_confidence', $confidence);
        }

        return new ForumAiResult(
            ['suggestion' => $candidateSuggestion],
            true,
            false,
            $confidence,
            'Suggestion IA generee pour le commentaire.'
        );
    }

    /**
     * @param array<int, string> $commentSnippets
     */
    public function summarizeThread(?string $postTitle, ?string $postContent, array $commentSnippets = []): ForumAiResult
    {
        $normalizedTitle = $this->normalizeText($postTitle, 255);
        $normalizedContent = $this->normalizeText($postContent, 3000);
        $normalizedComments = $this->normalizeStringList($commentSnippets, $this->maxContextComments, 280);

        $fallbackPayload = $this->buildThreadSummaryFallback(
            $normalizedTitle,
            $normalizedContent,
            count($normalizedComments)
        );

        if ($this->signalLength([$normalizedTitle, $normalizedContent]) < $this->minInputChars) {
            return $this->fallbackResult('thread_summary', $fallbackPayload, 'input_too_short');
        }

        $systemPrompt = <<<PROMPT
Tu es un assistant de synthese de discussion de forum.
Retourne uniquement un objet JSON valide avec exactement ces cles:
- summary: string
- keyPoints: array de strings
- disagreements: array de strings
- openQuestions: array de strings
- confidence: number entre 0 et 1
Contraintes:
- langage neutre et clair
- informations factuelles basees sur le contexte fourni
- pas de texte hors JSON
PROMPT;

        $userPrompt = sprintf(
            "Titre du post: %s\nContenu du post: %s\nCommentaires recents: %s",
            $normalizedTitle !== '' ? $normalizedTitle : '(vide)',
            $normalizedContent !== '' ? $normalizedContent : '(vide)',
            $normalizedComments !== [] ? implode(' || ', $normalizedComments) : '(aucun)'
        );

        $payload = $this->llmAiClient->requestJson('thread_summary', $systemPrompt, $userPrompt);
        if ($payload === null) {
            return $this->fallbackResult('thread_summary', $fallbackPayload, 'provider_unavailable');
        }

        $candidate = [
            'summary' => $this->normalizeText($payload['summary'] ?? null, 1400),
            'keyPoints' => $this->normalizeListField($payload['keyPoints'] ?? [], 5, 220),
            'disagreements' => $this->normalizeListField($payload['disagreements'] ?? [], 5, 220),
            'openQuestions' => $this->normalizeListField($payload['openQuestions'] ?? [], 5, 220),
        ];

        if ($candidate['summary'] === '') {
            $candidate['summary'] = $fallbackPayload['summary'];
        }
        if ($candidate['keyPoints'] === []) {
            $candidate['keyPoints'] = $fallbackPayload['keyPoints'];
        }
        if ($candidate['openQuestions'] === []) {
            $candidate['openQuestions'] = $fallbackPayload['openQuestions'];
        }

        $confidence = $this->extractConfidence($payload, 0.66);
        if ($confidence < $this->confidenceThreshold) {
            return $this->fallbackResult('thread_summary', $fallbackPayload, 'low_confidence', $confidence);
        }

        return new ForumAiResult(
            $candidate,
            true,
            false,
            $confidence,
            'Synthese IA du fil generee.'
        );
    }

    /**
     * @param array<int, string> $values
     */
    private function signalLength(array $values): int
    {
        $total = 0;
        foreach ($values as $value) {
            $total += $this->stringLength($value);
        }

        return $total;
    }

    private function normalizeText(?string $value, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($this->stringLength($text) > $maxLength) {
            $text = $this->stringSlice($text, $maxLength);
        }

        return $text;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function normalizeStringList(array $values, int $maxItems, int $maxLengthPerItem): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $clean = $this->normalizeText($value, $maxLengthPerItem);
            if ($clean === '') {
                continue;
            }

            $normalized[] = $clean;
            if (count($normalized) >= $maxItems) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeListField(mixed $value, int $maxItems, int $maxLengthPerItem): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n]+/u', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $clean = $this->normalizeText($item, $maxLengthPerItem);
            if ($clean === '') {
                continue;
            }

            $items[] = $clean;
            if (count($items) >= $maxItems) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractConfidence(array $payload, float $default): float
    {
        $raw = $payload['confidence'] ?? null;
        if (!is_numeric($raw)) {
            return $this->clampConfidence($default);
        }

        return $this->clampConfidence((float) $raw);
    }

    private function clampConfidence(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function fallbackResult(string $feature, array $payload, string $reason, float $confidence = 0.45): ForumAiResult
    {
        $clampedConfidence = $this->clampConfidence($confidence);
        $this->logger->info('Forum AI fallback mode used.', [
            'feature' => $feature,
            'reason' => $reason,
            'confidence' => $clampedConfidence,
            'payload_keys' => array_keys($payload),
            'payload_content_length' => isset($payload['content']) && is_string($payload['content'])
                ? $this->stringLength($payload['content'])
                : null,
        ]);

        $message = match ($reason) {
            'input_too_short' => 'Mode fallback actif: saisissez un texte plus detaille pour lancer la generation IA.',
            'provider_unavailable' => 'Mode fallback actif: fournisseur IA indisponible (timeout, erreur API ou quota).',
            'low_confidence' => 'Mode fallback actif: resultat IA juge peu fiable.',
            default => 'Mode fallback actif: suggestion locale fournie.',
        };

        return new ForumAiResult(
            $payload,
            false,
            true,
            $clampedConfidence,
            $message
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildCommunityFallback(
        string $name,
        string $purpose,
        string $description,
        string $rules,
        string $welcomeMessage
    ): array {
        $topic = $purpose !== '' ? $purpose : ($name !== '' ? $name : 'la lecture et les discussions');
        $safeName = $name !== '' ? $name : 'cette communaute';
        $finalPurpose = $purpose !== '' ? $purpose : sprintf(
            'Creer un espace de discussion constructif autour de %s.',
            $safeName
        );

        $finalDescription = $description !== '' ? $description : sprintf(
            '%s est un espace de discussion dedie a %s. Les membres partagent des recommandations, des retours d experience et des ressources utiles.',
            $safeName,
            $topic
        );

        $finalRules = $rules !== '' ? $rules : implode("\n", [
            '- Respecter les autres membres en toutes circonstances.',
            '- Argumenter avec des exemples concrets et rester sur le sujet.',
            '- Eviter le spam, la publicite agressive et les contenus hors theme.',
        ]);

        $finalWelcomeMessage = $welcomeMessage !== '' ? $welcomeMessage : sprintf(
            'Bienvenue dans %s. Presentez-vous, partagez vos objectifs et lancez votre premiere discussion.',
            $safeName
        );

        return [
            'purpose' => $finalPurpose,
            'description' => $finalDescription,
            'rules' => $finalRules,
            'welcomeMessage' => $finalWelcomeMessage,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildPostFallback(string $title, string $content, string $communityName): array
    {
        $subject = $communityName !== '' ? $communityName : 'cette communaute';
        $normalizedTitle = $title !== '' ? $title : sprintf('Discussion: %s', $subject);
        $fallbackTitle = $this->fallbackPostTitle($normalizedTitle);
        $fallbackContent = $this->fallbackPostContent($content, $subject);

        return [
            'title' => $fallbackTitle,
            'content' => $fallbackContent,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildCommentFallback(string $postTitle, string $draft, int $commentCount): array
    {
        if ($draft !== '') {
            return ['suggestion' => $draft];
        }

        $topic = $postTitle !== '' ? '"' . $postTitle . '"' : 'ce sujet';
        $contextHint = $commentCount > 0
            ? 'Les retours deja partages montrent plusieurs points de vue interessants.'
            : 'Le fil vient de commencer et votre avis peut orienter la discussion.';

        return [
            'suggestion' => sprintf(
                'Merci pour votre partage sur %s. %s Pour avancer, je proposerais de preciser les priorites et d illustrer avec un exemple concret.',
                $topic,
                $contextHint
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildThreadSummaryFallback(string $title, string $content, int $commentCount): array
    {
        $safeTitle = $title !== '' ? $title : 'Sujet sans titre';
        $contentSnippet = $content !== ''
            ? $this->stringSlice($content, 220)
            : 'Le post introduit un sujet de discussion general.';

        return [
            'summary' => sprintf(
                'Le fil "%s" traite principalement de: %s',
                $safeTitle,
                $contentSnippet
            ),
            'keyPoints' => [
                'Le post initial definit le cadre de la discussion.',
                sprintf('%d commentaire(s) ont ete pris en compte pour la synthese.', $commentCount),
            ],
            'disagreements' => [],
            'openQuestions' => [
                'Quelles actions concretes peuvent etre appliquees a court terme ?',
                'Quels exemples supplementaires pourraient clarifier le sujet ?',
            ],
        ];
    }

    private function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function stringSlice(string $value, int $maxLength): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength);
        }

        return substr($value, 0, $maxLength);
    }

    private function fallbackPostTitle(string $title): string
    {
        $clean = $this->normalizeText($title, 220);
        if ($clean !== '') {
            return $this->normalizeText($clean, 255);
        }

        return 'Discussion ouverte';
    }

    private function fallbackPostContent(string $content, string $subject): string
    {
        $cleanContent = $this->normalizeText($content, 2200);
        if ($cleanContent === '') {
            return implode("\n\n", [
                sprintf('Je lance une discussion autour de %s.', $subject),
                'Pour enrichir le sujet, vous pouvez partager:',
                "- Une oeuvre qui vous a marque.\n- Une idee forte retenue de cette oeuvre.\n- Une recommandation pour la communaute.",
            ]);
        }

        $baseContent = $this->removeFallbackAppendix($cleanContent);
        if ($baseContent === '') {
            $baseContent = sprintf('Je lance une discussion autour de %s.', $subject);
        }

        $baseQuestion = $this->extractMainQuestion($baseContent);
        $questionTwo = $this->buildFollowUpQuestion($baseContent);
        $questionThree = 'Avez-vous une recommandation a partager avec la communaute ?';

        $questionList = [$questionTwo, $questionThree];
        if ($baseQuestion === '') {
            array_unshift($questionList, sprintf(
                'Quelle oeuvre liee a %s vous a le plus marque, et pourquoi ?',
                $subject
            ));
        }

        $questionList = array_values(array_unique($questionList));

        return implode("\n\n", [
            $baseContent,
            'Pour aller plus loin, vous pouvez reagir a:',
            '- ' . implode("\n- ", $questionList),
        ]);
    }

    private function extractMainQuestion(string $content): string
    {
        if (strpos($content, '?') === false) {
            return '';
        }

        $candidates = preg_split('/\?/u', $content) ?: [];
        if ($candidates === []) {
            return '';
        }

        $beforeQuestion = trim((string) end($candidates));
        if ($beforeQuestion === '' && count($candidates) >= 2) {
            $beforeQuestion = trim((string) $candidates[count($candidates) - 2]);
        }

        if ($beforeQuestion === '') {
            return '';
        }

        $start = max(
            strrpos($beforeQuestion, '.'),
            strrpos($beforeQuestion, '!'),
            strrpos($beforeQuestion, ':')
        );

        if ($start !== false) {
            $beforeQuestion = trim(substr($beforeQuestion, $start + 1));
        }

        if ($beforeQuestion === '') {
            return '';
        }

        return rtrim($beforeQuestion, "?.! \t\n\r\0\x0B") . ' ?';
    }

    private function buildFollowUpQuestion(string $content): string
    {
        $lower = strtolower($content);
        if (str_contains($lower, 'technolog')) {
            return 'Quelle technologie imaginee dans cette oeuvre vous semble la plus credible aujourd hui ?';
        }

        if (str_contains($lower, 'roman') || str_contains($lower, 'film') || str_contains($lower, 'livre')) {
            return 'Qu est-ce qui vous a le plus marque dans cette oeuvre (idee, univers, personnages) ?';
        }

        return 'Quel exemple concret illustre le mieux votre point de vue ?';
    }

    private function removeFallbackAppendix(string $content): string
    {
        $clean = trim($content);
        if ($clean === '') {
            return '';
        }

        $legacyContextPattern = '/contexte\s*:\s*(.*?)\s*point\s+principal\s*:/uis';
        if (preg_match($legacyContextPattern, $clean, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if ($candidate !== '') {
                $clean = $candidate;
            }
        }

        $markerPatterns = [
            '/\bPour\s+enrichir\s+la\s+discussion,\s+vous\s+pouvez\s+r\w*pondre\s+\S+\s*:.*/uis',
            '/\bPour\s+aller\s+plus\s+loin,\s+vous\s+pouvez\s+r\w*agir\s+\S+\s*:.*/uis',
            '/\bPour\s+enrichir\s+le\s+sujet,\s+vous\s+pouvez\s+partager\s*:.*/uis',
        ];

        foreach ($markerPatterns as $pattern) {
            $updated = preg_replace($pattern, '', $clean, 1);
            if (!is_string($updated)) {
                continue;
            }

            $clean = trim($updated);
        }

        return $clean;
    }
}
