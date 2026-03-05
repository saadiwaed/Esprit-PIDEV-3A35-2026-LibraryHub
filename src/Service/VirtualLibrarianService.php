<?php

namespace App\Service;

use App\Entity\User;

/**
 * Assistant "bibliothécaire virtuel" simple.
 *
 * Utilise le même client LLM que l'assistant de forum, mais avec un prompt
 * adapté au contexte LibraryHub (prêts, abonnements, catalogue, clubs, etc.).
 */
final class VirtualLibrarianService
{
    public function __construct(
        private readonly VirtualLibrarianAiClient $aiClient,
    ) {
    }

    /**
     * Retourne une réponse textuelle adaptée à la question de l'utilisateur.
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
                'answer' => 'Merci de formuler une question ou un besoin de lecture (par exemple : "Comment prolonger un prêt ?" ou "Je cherche des livres sur Symfony").',
                'meta' => ['usedAi' => false, 'reason' => 'empty_question'],
            ];
        }

        $profileLine = $this->buildProfileLine($user);

        // 1) Réponses locales rapides basées sur des règles simples (salutations uniquement)
        $ruleBased = $this->getRuleBasedAnswer($question, $user);
        if ($ruleBased !== null) {
            return [
                'ok' => true,
                'answer' => $ruleBased,
                'meta' => ['usedAi' => false, 'reason' => 'rule_based'],
            ];
        }

        $systemPrompt = <<<PROMPT
Tu es le bibliothécaire virtuel de "LibraryHub", une bibliothèque en ligne moderne.
Ton rôle:
- Répondre en FRANÇAIS, de façon claire, polie et concise (3 à 6 phrases).
- Aider sur: prêts de livres, retards, pénalités, abonnements (mensuel/annuel), clubs de lecture, événements, recherche de livres.
- Quand c'est utile, proposer des suggestions de types de livres (sans inventer des titres existants dans la base).
- Ne JAMAIS parler d'API, de tokens, de JSON ou de détails techniques.
- Si la question concerne un bug technique, invite à contacter l'administration de la bibliothèque.
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
            'answer' => "Je peux vous aider ! Dites-moi ce que vous cherchez (genre, sujet, niveau, durée) ou posez une question sur les prêts/abonnements.\n"
                . "Exemples :\n"
                . "- « Je débute en Symfony, je veux un parcours de lecture »\n"
                . "- « Comment prolonger un prêt ? »\n"
                . "- « Comment s’abonner (mensuel/annuel) ? »",
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
     * Réponses locales très simples (salutations).
     * Toutes les autres questions passent par l'API externe.
     */
    private function getRuleBasedAnswer(string $question, ?User $user): ?string
    {
        $q = function_exists('mb_strtolower') ? mb_strtolower($question) : strtolower($question);

        // Salutations simples
        if (preg_match('/\b(bonjour|salut|bonsoir|hello)\b/u', $q)) {
            return "Bonjour ! Je suis le bibliothécaire virtuel de LibraryHub. "
                . "Vous pouvez me demander comment prolonger un prêt, comprendre les abonnements "
                . "ou obtenir des idées de lecture.";
        }

        // Par défaut : pas de règle locale, on laisse la main au modèle externe
        return null;
    }

    private function buildProfileLine(?User $user): string
    {
        if (!$user instanceof User) {
            return "L'utilisateur n'est pas connecté. Donne une réponse générale adaptée à tous les lecteurs.";
        }

        $roles = $user->getRoles();
        $roleLabel = 'membre';
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $roleLabel = 'administrateur';
        } elseif (in_array('ROLE_LIBRARIAN', $roles, true)) {
            $roleLabel = 'bibliothécaire';
        }

        $premiumInfo = $user->isPremium()
            ? 'Son abonnement est premium : il a accès complet au catalogue.'
            : 'Son abonnement est standard ou expiré.';

        return sprintf(
            'Contexte utilisateur: %s connecté(e) avec l\'email %s. %s',
            $roleLabel,
            $user->getEmail() ?? 'inconnu',
            $premiumInfo
        );
    }
}

