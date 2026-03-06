# Intégration Stripe – Abonnements LibraryHub (guide professionnel)

Document de référence pour présenter l’intégration des paiements et abonnements Stripe dans LibraryHub de manière claire et professionnelle (soutenance, rapport, entretien).

---

## 1. Contexte et objectif métier

**Objectif** : Proposer aux membres de LibraryHub un **abonnement premium** (mensuel ou annuel) pour débloquer un accès complet au catalogue et aux fonctionnalités avancées, avec paiement en ligne sécurisé et renouvellement automatique.

**Choix technique** : **Stripe** comme prestataire de paiement (PCI-DSS, SCA, gestion des abonnements récurrents). L’application ne traite jamais les numéros de carte ; tout passe par l’API Stripe et la page de paiement hébergée par Stripe (Checkout).

---

## 2. Architecture de l’intégration

### Composants impliqués

| Composant | Rôle |
|----------|------|
| **MembershipController** | Affiche la page tarifs, crée la session Stripe Checkout, gère les retours succès/annulation et active le compte premium. |
| **StripeConfigService** | Centralise la clé secrète Stripe, les Price ID (mensuel/annuel) et, si besoin, la création des prix via l’API avec cache local. |
| **Entité User** | Champ `isPremium` (booléen) pour marquer l’accès premium après un paiement réussi. |
| **Templates** | Page abonnement (`membership/index.html.twig`), succès (`success.html.twig`), annulation (`cancel.html.twig`). |
| **Configuration** | `.env` / `.env.local` : `STRIPE_SECRET_KEY`, `STRIPE_PRICE_MONTHLY`, `STRIPE_PRICE_ANNUAL` (optionnel si création automatique). |

### Flux de données (schéma)

```
[Membre] → /abonnement (choix du plan)
    → Clic « Continuer vers le paiement »
    → GET /abonnement/checkout/{plan} (monthly | annual)
    → Backend : création Stripe Checkout Session (mode subscription)
    → Redirection 302 vers Stripe (session.url)
[Membre paie sur Stripe]
    → Stripe redirige vers success_url avec ?session_id=...
    → GET /abonnement/success?plan=...&session_id=...
    → Backend : retrieve(session_id), vérification payment_status === 'paid'
    → Mise à jour User.isPremium = true
    → Affichage page « Merci pour votre abonnement »
```

En cas d’annulation sur Stripe : redirection vers `/abonnement/annule` (page « Annulation »).

---

## 3. Concepts Stripe utilisés

### Checkout Session (mode subscription)

- **Stripe Checkout** : page de paiement hébergée par Stripe (formulaire carte, 3D Secure, etc.). L’application ne gère pas les champs carte.
- **Mode `subscription`** : abonnement récurrent (mensuel ou annuel). Stripe gère les renouvellements et les échecs de paiement.
- **Line items** : une ligne par produit/prix. Ici une seule ligne : le `price` (Price ID) et `quantity: 1`.

### Price ID

- Dans Stripe, un **Price** est associé à un **Product** et définit le montant, la devise et l’intervalle (month/year).
- Les Price ID sont soit configurés dans le Dashboard Stripe et passés via `.env` (`STRIPE_PRICE_MONTHLY`, `STRIPE_PRICE_ANNUAL`), soit créés à la volée par `StripeConfigService` via l’API et mis en cache dans `var/stripe_prices.json`.

### Metadata

- **Session** : `metadata` avec `plan` (monthly|annual) et `user_id` pour identifier l’utilisateur côté retour `success`. Ainsi, même après redirection, on sait quel compte activer en premium.
- **Subscription** : `metadata.plan` pour tracer le type d’offre côté Stripe (facturation, analytics).

### Sécurité et conformité

- **Clé secrète** : utilisée uniquement côté serveur (jamais exposée au navigateur). Stockée dans `.env` / `.env.local`.
- **Clé publique (publishable)** : prévue pour un usage front (ex. Stripe.js / Elements) si on ajoute plus tard un formulaire carte sur le site ; dans le flux actuel, tout passe par Checkout, donc la clé secrète suffit pour créer la session.
- **PCI-DSS** : en déléguant la saisie carte à Stripe Checkout, l’application ne traite pas de données sensibles et simplifie la conformité.

---

## 4. Parcours détaillé (côté code)

### 4.1 Affichage de la page abonnement

- **Route** : `GET /abonnement` → `app_membership_index`.
- **Contrôleur** : `MembershipController::index()` → rendu `membership/index.html.twig`.
- **Template** : deux offres (Mensuelle 9,99 €/mois, Annuelle 89,99 €/an), bouton « Continuer vers le paiement » qui pointe vers `app_membership_checkout` avec le plan sélectionné (monthly ou annual). Si l’utilisateur n’est pas connecté, le bouton propose de se connecter d’abord.

### 4.2 Création de la session Checkout et redirection

- **Route** : `GET /abonnement/checkout/{plan}` avec `plan` = `monthly` ou `annual`.
- **Sécurité** : `$this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY')` — seuls les utilisateurs connectés peuvent accéder au checkout.
- **Contrôleur** :
  1. Vérification que Stripe est configuré (`StripeConfigService::isConfigured()`).
  2. Récupération du Price ID pour le plan (`getPriceId($plan)`).
  3. Construction des URLs de retour : `success_url` (avec `{CHECKOUT_SESSION_ID}` remplacé par Stripe) et `cancel_url`.
  4. Création de la session Stripe :
     - `mode => 'subscription'`
     - `line_items` : un seul élément avec le `price` (Price ID) et `quantity => 1`
     - `customer_email` : email de l’utilisateur connecté
     - `metadata` : `plan`, `user_id`
     - `subscription_data.metadata` : `plan`
  5. En cas de succès : `return new RedirectResponse($session->url)` → l’utilisateur est envoyé sur la page Stripe.
  6. En cas d’erreur (config, API) : message flash d’erreur et redirection vers `/abonnement`.

### 4.3 Retour après paiement réussi

- **Route** : `GET /abonnement/success?plan=...&session_id=...`.
- **Contrôleur** : `MembershipController::success()` :
  1. Vérification de l’authentification.
  2. Si `session_id` présent et Stripe configuré : `StripeSession::retrieve($sessionId)`.
  3. Si `payment_status === 'paid'` et `metadata->user_id` présent : chargement de l’utilisateur, vérification que c’est bien l’utilisateur connecté, puis `$user->setIsPremium(true)` et `flush()`.
  4. Rendu de la page de remerciement avec le libellé du plan (mensuel ou annuel).

Cette approche « optimiste » sur la page success permet d’afficher une confirmation même si l’API Stripe est momentanément indisponible ; en production, on peut compléter avec un **webhook** `checkout.session.completed` pour garantir la mise à jour du statut premium même si l’utilisateur ferme la page avant le retour.

### 4.4 Annulation

- **Route** : `GET /abonnement/annule` → `app_membership_cancel`.
- Simple affichage d’une page indiquant que l’opération a été annulée (pas de débit).

---

## 5. Service StripeConfigService

- **Rôle** : Fournir la clé secrète et les Price ID (mensuel / annuel) sans dupliquer la logique dans le contrôleur.
- **Configuration** : clé secrète et Price ID injectés via `config/services.yaml` (variables d’environnement).
- **Fallback** : si `STRIPE_PRICE_MONTHLY` ou `STRIPE_PRICE_ANNUAL` sont vides, le service peut créer les prix via `Price::create()` (montants : 9,99 €/mois et 89,99 €/an) et les enregistrer dans un fichier de cache `var/stripe_prices.json` pour les réutiliser aux requêtes suivantes.
- **Gestion d’erreurs** : en cas d’échec API (ex. clé invalide), `getLastErrorMessage()` permet d’afficher un message d’erreur explicite à l’utilisateur (ex. « Impossible de récupérer le prix Stripe »).

---

## 6. Modèle de données et impact métier

- **User.isPremium** : booléen en base (migration `Version20260224120000` : colonne `is_premium`). Mis à `true` après un paiement réussi sur la page success (et éventuellement par webhook si ajouté).
- **Usage** : le reste de l’application (ex. assistant virtuel, catalogue) peut conditionner l’accès aux fonctionnalités premium en testant `$user->isPremium()` (voir par ex. `VirtualLibrarianService`).

Aucune donnée de carte bancaire ni token de carte n’est stocké dans l’application ; Stripe conserve les moyens de paiement et gère les prélèvements récurrents.

---

## 7. Sécurité et bonnes pratiques

- **Authentification** : checkout et success réservés aux utilisateurs connectés (`IS_AUTHENTICATED_FULLY`).
- **Vérification sur success** : on n’active le premium que si `metadata->user_id` correspond à l’utilisateur actuellement connecté, pour éviter qu’un utilisateur A réutilise l’URL de succès d’un utilisateur B.
- **Clé secrète** : uniquement dans des fichiers non versionnés (`.env.local`) et jamais exposée au client.
- **HTTPS** : obligatoire en production pour les redirections Stripe et la sécurité des cookies de session.

---

## 8. Fichiers à montrer (démo / soutenance)

1. **`src/Controller/MembershipController.php`** — Routes et logique checkout / success / cancel.
2. **`src/Service/StripeConfigService.php`** — Centralisation clé et Price ID, fallback création de prix.
3. **`src/Entity/User.php`** — Propriété `isPremium` et getters/setters.
4. **`config/services.yaml`** — Paramètres Stripe (clé, Price ID) et injection dans le service.
5. **`templates/membership/index.html.twig`** — Page tarifs et lien vers checkout avec plan dynamique (JS).
6. **`templates/membership/success.html.twig`** — Page de confirmation après paiement.
7. **`.env` / `.env.local`** (sans afficher la clé) — Variables `STRIPE_SECRET_KEY`, `STRIPE_PRICE_*`.

---

## 9. Résumé professionnel à dire au jury

- « L’abonnement premium LibraryHub est géré via **Stripe** : le membre choisit une offre (mensuelle ou annuelle) sur la page **Abonnement**, puis est **redirigé vers Stripe Checkout** pour saisir sa carte. L’application ne traite jamais les données bancaires, ce qui simplifie la conformité PCI-DSS. »
- « Côté backend, un **MembershipController** crée une **Checkout Session** en mode **subscription** avec le Price ID correspondant au plan, l’email du membre et des **metadata** (plan, user_id) pour identifier le compte au retour. Après paiement, Stripe redirige vers notre URL de succès avec un **session_id** ; on récupère la session via l’API, on vérifie le statut payé et le user_id, puis on **active le compte premium** (`User.isPremium = true`). »
- « Les **Price ID** et la **clé secrète** sont injectés par configuration (`.env`) ; un **StripeConfigService** centralise cette config et peut, en fallback, créer les prix via l’API et les mettre en cache pour éviter de les recréer à chaque requête. »
- « L’accès au checkout et à la page de succès est réservé aux **utilisateurs connectés** ; sur la page de succès, on ne met à jour le premium que si l’utilisateur connecté correspond au `user_id` stocké dans la session Stripe, pour éviter les abus. »

Tu peux utiliser ce document comme base pour ton explication orale ou pour une section « Paiement et abonnements » dans ton rapport de projet.
