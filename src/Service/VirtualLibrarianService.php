<?php

namespace App\Service;

use App\Entity\User;

/**
 * Assistant "bibliothÃ©caire virtuel" simple.
 *
 * Utilise le mÃªme client LLM que l'assistant de forum, mais avec un prompt
 * adaptÃ© au contexte LibraryHub (prÃªts, abonnements, catalogue, clubs, etc.).
 */
final class VirtualLibrarianService
{
    public function __construct(
        private readonly VirtualLibrarianAiClient $aiClient,
    ) {
    }

    /**
     * Retourne une rÃ©ponse textuelle adaptÃ©e Ã  la question de l'utilisateur.
     *
     * @return array{
     *     ok: bool,
     *     answer: string,
     *     meta: array<string, mixed>
     * }
     */
    public function answer(string $question, ?User $user = null): array
    {
        $question = trim($question);
        if ($question === '') {
            return [
                'ok' => false,
                'answer' => 'Merci de formuler une question ou un besoin de lecture (par exemple : "Comment prolonger un prÃªt ?" ou "Je cherche des livres sur Symfony").',
                'meta' => ['usedAi' => false, 'reason' => 'empty_question'],
            ];
        }

        $profileLine = $this->buildProfileLine($user);

        // 1) RÃ©ponses locales rapides basÃ©es sur des rÃ¨gles simples (salutations uniquement)
        $ruleBased = $this->getRuleBasedAnswer($question, $user);
        if ($ruleBased !== null) {
            return [
                'ok' => true,
                'answer' => $ruleBased,
                'meta' => ['usedAi' => false, 'reason' => 'rule_based'],
            ];
        }

        $systemPrompt = <<<PROMPT
Tu es le bibliothÃ©caire virtuel de "LibraryHub", une bibliothÃ¨que en ligne moderne.
Ton rÃ´le:
- RÃ©pondre en FRANÃ‡AIS, de faÃ§on claire, polie et concise (3 Ã  6 phrases).
- Aider sur: prÃªts de livres, retards, pÃ©nalitÃ©s, abonnements (mensuel/annuel), clubs de lecture, Ã©vÃ©nements, recherche de livres.
- Quand c'est utile, proposer des suggestions de types de livres (sans inventer des titres existants dans la base).
- Ne JAMAIS parler d'API, de tokens, de JSON ou de dÃ©tails techniques.
- Si la question concerne un bug technique, invite Ã  contacter l'administration de la bibliothÃ¨que.
PROMPT;

        $userPrompt = sprintf(
            "%s\nQuestion de l'utilisateur:\n%s",
            $profileLine,
            $question
        );

        $payload = $this->aiClient->requestJson(
            'virtual_librarian',
            $systemPrompt . "\n\nRetourne un objet JSON {\"answer\": string}.",
            $userPrompt
        );

        $fallback = [
            'ok' => true,
            'answer' => "Je peux vous aider ! Dites-moi ce que vous cherchez (genre, sujet, niveau, durÃ©e) ou posez une question sur les prÃªts/abonnements.\n"
                . "Exemples :\n"
                . "- Â« Je dÃ©bute en Symfony, je veux un parcours de lecture Â»\n"
                . "- Â« Comment prolonger un prÃªt ? Â»\n"
                . "- Â« Comment sâ€™abonner (mensuel/annuel) ? Â»",
            'meta' => ['usedAi' => false, 'reason' => 'fallback_local'],
        ];

        if ($payload === null || !is_string($payload['answer'] ?? null)) {
            return $fallback;
        }

        $answer = trim((string) $payload['answer']);
        if ($answer === '') {
            return $fallback;
        }

        return [
            'ok' => true,
            'answer' => $answer,
            'meta' => [
                'usedAi' => true,
                'reason' => 'ai_response',
            ],
        ];
    }

    /**
     * RÃ©ponses locales trÃ¨s simples (salutations).
     * Toutes les autres questions passent par l'API externe.
     */
    private function getRuleBasedAnswer(string $question, ?User $user): ?string
    {
        $q = function_exists('mb_strtolower') ? mb_strtolower($question) : strtolower($question);

        // Salutations simples
        if (preg_match('/\b(bonjour|salut|bonsoir|hello)\b/u', $q)) {
            return "Bonjour ! Je suis le bibliothÃ©caire virtuel de LibraryHub. "
                . "Vous pouvez me demander comment prolonger un prÃªt, comprendre les abonnements "
                . "ou obtenir des idÃ©es de lecture.";
        }

        // Par dÃ©faut : pas de rÃ¨gle locale, on laisse la main au modÃ¨le externe
        return null;
    }

    private function buildProfileLine(?User $user): string
    {
        if (!$user instanceof User) {
            return "L'utilisateur n'est pas connectÃ©. Donne une rÃ©ponse gÃ©nÃ©rale adaptÃ©e Ã  tous les lecteurs.";
        }

        $roles = $user->getRoles();
        $roleLabel = 'membre';
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $roleLabel = 'administrateur';
        } elseif (in_array('ROLE_LIBRARIAN', $roles, true)) {
            $roleLabel = 'bibliothÃ©caire';
        }

        $premiumInfo = $user->isPremium()
            ? 'Son abonnement est premium : il a accÃ¨s complet au catalogue.'
            : 'Son abonnement est standard ou expirÃ©.';

        return sprintf(
            'Contexte utilisateur: %s connectÃ©(e) avec l\'email %s. %s',
            $roleLabel,
            $user->getEmail(),
            $premiumInfo
        );
    }
}


