<?php

namespace App\Service\Forum;

use Psr\Log\LoggerInterface;

final class ForumContentModerationService
{
    /**
     * Local hard-block rules for explicit insults/threats.
     * These are applied before API scoring to guarantee minimum protection.
     *
     * @var array<int, string>
     */
    private const HARD_BLOCK_PATTERNS = [
        // Existing English patterns
        '/\byou are an idiot\b/i',
        '/\byou(\'| a)?re\s+(such\s+an?\s+)?idiot\b/i',
        '/\bidiot\b/i',
        '/\bstupid\b/i',
        '/\bmoron\b/i',
        '/\bdumb\b/i',
        '/\bworst thing ever\b/i',
        '/\bgo to hell\b/i',
        '/\bkill yourself\b/i',

        // Added English profanity/abuse patterns
        '/\basshole\b/i',
        '/\bjerk\b/i',
        '/\bbastard\b/i',
        '/\bson of a bitch\b/i',
        '/\bmotherfucker\b/i',
        '/\bpiece of shit\b/i',
        '/\bfuck(?:ing)?\b/i',
        '/\bfuck (?:you|off)\b/i',
        '/\bshit(?:ty)?\b/i',
        '/\bcunt\b/i',
        '/\bprick\b/i',
        '/\bretard(?:ed)?\b/i',
        '/\byou suck\b/i',
        '/\bshut up\b/i',
        '/\bi hate you\b/i',
        '/\byou are trash\b/i',

        // Existing French patterns
        '/\bconnard(e)?\b/i',
        '/\bta gueule\b/i',
        '/\bferme ta gueule\b/i',
        '/\bva te faire\b/i',
        '/\bsale (con|connard|pute)\b/i',
        '/\bimbecile\b/i',
        '/\bcretin\b/i',

        // Added French profanity/abuse patterns
        '/\bcon(ne)?\b/i',
        '/\bsalope\b/i',
        '/\bpute\b/i',
        '/\benfoire\b/i',
        '/\bencule\b/i',
        '/\bfdp\b/i',
        '/\bta mere\b/i',
        '/\bnique ta mere\b/i',
        '/\bfils de pute\b/i',
        '/\bva te faire foutre\b/i',
        '/\bferme-la\b/i',
        '/\bgros(se)? merde\b/i',
        '/\babruti\b/i',
        '/\bdebile\b/i',
        '/\bordure\b/i',
    ];

    private float $toxicityThreshold;

    public function __construct(
        private readonly PerspectiveApiClient $perspectiveApiClient,
        private readonly LoggerInterface $logger,
        float $toxicityThreshold
    ) {
        $this->toxicityThreshold = max(0.0, min(1.0, $toxicityThreshold));
    }

    public function moderate(string $content, string $label): ForumContentModerationResult
    {
        $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
        if ($plainText === '') {
            return new ForumContentModerationResult(false, true, 0.0, '');
        }

        if ($this->matchesHardBlockPattern($plainText)) {
            $this->logger->warning('Forum content blocked by local hard-block moderation.', [
                'label' => $label,
            ]);

            return new ForumContentModerationResult(
                true,
                false,
                null,
                sprintf(
                    'Votre %s contient un langage inapproprie. Merci de reformuler avant publication.',
                    $label
                )
            );
        }

        $toxicityScore = $this->perspectiveApiClient->analyzeToxicity($plainText);
        if ($toxicityScore === null) {
            return new ForumContentModerationResult(
                false,
                false,
                null,
                'Moderation automatique indisponible pour le moment. Le contenu est accepte sans verification externe.'
            );
        }

        if ($toxicityScore >= $this->toxicityThreshold) {
            $message = sprintf(
                'Votre %s semble contenir un langage inapproprie (score de toxicite: %.2f). Merci de reformuler.',
                $label,
                $toxicityScore
            );

            $this->logger->info('Forum content blocked by Perspective API moderation.', [
                'label' => $label,
                'toxicity_score' => $toxicityScore,
                'threshold' => $this->toxicityThreshold,
            ]);

            return new ForumContentModerationResult(true, true, $toxicityScore, $message);
        }

        return new ForumContentModerationResult(false, true, $toxicityScore, '');
    }

    private function matchesHardBlockPattern(string $content): bool
    {
        foreach (self::HARD_BLOCK_PATTERNS as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }
}
