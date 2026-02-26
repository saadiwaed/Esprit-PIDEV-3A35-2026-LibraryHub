# Forum AI Integration - Assistant de Redaction et Synthese

## Objectif
Implementer **L'integration de l'IA** dans le module Forum (Communities, Posts, Comments) via un assistant generatif visible pour l'utilisateur.

Fonctions IA ajoutees:
- Generation de texte pour **Community** (`purpose`, `description`, `rules`, `welcomeMessage`)
- Amelioration de brouillon pour **Post** (`title`, `content`)
- Suggestion de reponse pour **Comment**
- Synthese de fil de discussion pour **Post thread**

## Architecture

### Service chain
- `src/Controller/ForumAiAssistantController.php`
  - Expose les endpoints JSON de l'assistant IA.
- `src/Service/Forum/ForumAiAssistantService.php`
  - Orchestration metier, prompts, validation des sorties, fallback.
- `src/Service/Forum/LlmAiClient.php`
  - Client HTTP vers fournisseur LLM (endpoint configurable).
- `src/Service/Forum/ForumAiResult.php`
  - DTO resultat (`payload`, `usedAi`, `fallbackUsed`, `confidence`, `message`).

### Endpoints
- `POST /forum/ai/community/draft`
- `POST /forum/ai/post/draft`
- `POST /forum/ai/post/{id}/comment/suggest`
- `POST /forum/ai/post/{id}/summary`

## Configuration

Variables d'environnement:

```dotenv
FORUM_AI_ENABLED=1
FORUM_AI_PROVIDER="openai"
FORUM_AI_ENDPOINT="https://api.openai.com/v1/chat/completions"
FORUM_AI_API_KEY=""
FORUM_AI_MODEL="gpt-4o-mini"
FORUM_AI_TIMEOUT_SECONDS=8
FORUM_AI_TEMPERATURE=0.4
FORUM_AI_MAX_TOKENS=900
FORUM_AI_MIN_INPUT_CHARS=12
FORUM_AI_CONFIDENCE_THRESHOLD=0.55
FORUM_AI_MAX_CONTEXT_COMMENTS=15
```

## Fallback et robustesse
- Si IA indisponible (timeout, endpoint invalide, cle manquante): le module retourne une suggestion locale deterministic (fallback), sans bloquer le workflow utilisateur.
- Si la confiance retournee par le modele est sous le seuil: fallback applique.
- Le message de retour fallback precise le motif: `input_too_short`, `provider_unavailable`, `low_confidence`.
- En mode fallback de **Post**, le brouillon est maintenant retravaille dans un format structure (Contexte / Point principal / Questions) pour un rendu visible et utile.
- Logs techniques traces:
  - erreurs reseau/API
  - activation du fallback
  - latence et metadonnees provider/model
- Le contenu brut utilisateur n'est pas journalise.

## Frontend
- Script: `public/js/forum-ai-assistant.js`
- Integration UI dans:
  - formulaires Community (`templates/community/_form.html.twig`)
  - formulaires Post admin/front
  - page thread post front et admin (suggestion commentaire + synthese)

## Validation
- Test service: `tests/Service/Forum/ForumAiAssistantServiceTest.php`
- Cas verifies:
  - mode fallback quand fournisseur indisponible
  - resultat IA applique quand payload valide et confiance suffisante
