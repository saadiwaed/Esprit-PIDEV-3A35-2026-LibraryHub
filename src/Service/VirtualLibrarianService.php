<?php

namespace App\Service;

use App\Entity\User;
use App\Service\Forum\LlmAiClient;

/**
 * Assistant "bibliothécaire virtuel" simple.
 *
 * Utilise le même client LLM que l'assistant de forum, mais avec un prompt
 * adapté au contexte LibraryHub (prêts, abonnements, catalogue, clubs, etc.).
 */
final class VirtualLibrarianService
{
    public function __construct(
        private readonly LlmAiClient $llmAiClient,
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

        // 1) Réponses locales rapides basées sur des règles simples
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

        $payload = $this->llmAiClient->requestJson(
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
     * Réponses "intelligentes" mais locales, sans appel HTTP externe.
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

        // Prêts et prolongations
        if (str_contains($q, 'prêt') || str_contains($q, 'pret') || str_contains($q, 'prolong')) {
            return "Pour gérer vos prêts sur LibraryHub, connectez-vous puis allez dans la section « Mes emprunts ». "
                . "Vous y verrez la date de retour prévue et, si les conditions le permettent, un bouton pour prolonger le prêt. "
                . "En général, un prêt peut être prolongé tant que le livre n’est pas déjà réservé par un autre membre.";
        }

        // Retards et pénalités
        if (str_contains($q, 'retard') || str_contains($q, 'amende') || str_contains($q, 'pénalité') || str_contains($q, 'penalite')) {
            return "En cas de retard, LibraryHub applique des pénalités journalières configurées par la bibliothèque. "
                . "Vous pouvez consulter le détail de vos pénalités éventuelles dans la section « Mes emprunts » ou « Mes pénalités ». "
                . "Plus vous rendez le livre tôt, moins la pénalité sera élevée.";
        }

        // Abonnements
        if (str_contains($q, 'abonnement') || str_contains($q, 'premium') || str_contains($q, 'mensuel') || str_contains($q, 'annuel')) {
            return "LibraryHub propose un abonnement mensuel et un abonnement annuel. "
                . "L’abonnement premium donne un accès étendu au catalogue et aux fonctionnalités avancées. "
                . "Vous pouvez voir les offres détaillées et souscrire depuis la page « Abonnement » du site.";
        }

        // Clubs de lecture
        if (str_contains($q, 'club') || str_contains($q, 'lecture') && str_contains($q, 'rejoindre')) {
            return "Pour rejoindre un club de lecture, allez dans la section « Club de Lecture » du frontoffice. "
                . "Vous y trouverez la liste des clubs disponibles et, si vous êtes connecté, un bouton pour demander à rejoindre un club ou en créer un nouveau.";
        }

        // Suggestions de livres générales
        if (str_contains($q, 'recommander') || str_contains($q, 'suggestion') || str_contains($q, 'idée de livre') || str_contains($q, 'idee de livre')) {
            return "Je peux vous proposer quelques pistes : "
                . "• Pour apprendre la programmation : cherchez des livres dans la catégorie Informatique / Développement.\n"
                . "• Pour la détente : explorez les romans contemporains et la littérature classique.\n"
                . "• Pour la recherche : utilisez le moteur de recherche par auteur, titre ou catégorie dans le catalogue.";
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

