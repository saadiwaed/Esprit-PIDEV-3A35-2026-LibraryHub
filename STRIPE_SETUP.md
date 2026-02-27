# Paiement Stripe – LibraryHub (étape par étape)

## Résumé du flux

1. L’utilisateur choisit un plan (Mensuel 9,99 € ou Annuel 89,99 €).
2. Il clique sur **« Continuer vers le paiement »**.
3. Il est redirigé vers la **page de paiement Stripe (Checkout)**.
4. Après paiement réussi, il revient sur LibraryHub sur la page « Merci pour votre abonnement ».
5. Son compte devient **premium** (`is_premium = true`, accès complet).

---

## Étape 1 : Clés Stripe dans `.env.local`

Créez le fichier **`.env.local`** à la racine du projet (s’il n’existe pas) et ajoutez vos clés Stripe :

```env
###> stripe (abonnement) ###
STRIPE_SECRET_KEY=sk_test_...votre_clé_secrète_complète
STRIPE_PUBLISHABLE_KEY=pk_test_51T47vgCQuNLiWGci...votre_clé_publique_complète
###< stripe (abonnement) ###
```

- **STRIPE_SECRET_KEY** : obligatoire (clé secrète, côté serveur).
- **STRIPE_PUBLISHABLE_KEY** : optionnel pour le Checkout actuel (utile si vous ajoutez plus tard du JS Stripe côté client).

Vous pouvez laisser **STRIPE_PRICE_MONTHLY** et **STRIPE_PRICE_ANNUAL** vides dans `.env` : l’application crée alors automatiquement les prix Stripe (9,99 €/mois et 89,99 €/an) au premier passage au checkout et les met en cache dans `var/stripe_prices.json`.

Si vous préférez utiliser des prix déjà créés dans le tableau de bord Stripe, ajoutez dans `.env.local` :

```env
STRIPE_PRICE_MONTHLY=price_xxxxx
STRIPE_PRICE_ANNUAL=price_yyyyy
```

---

## Étape 2 : Base de données (colonne premium)

Exécutez la migration pour ajouter la colonne `is_premium` à la table `user` :

```bash
php bin/console doctrine:migrations:migrate
```

(Si vous n’utilisez pas les migrations, ajoutez manuellement la colonne : `ALTER TABLE user ADD is_premium TINYINT(1) DEFAULT 0 NOT NULL;`.)

---

## Étape 3 : Tester le flux

1. Démarrer le serveur (ex. `symfony serve` ou `php -S 127.0.0.1:8000 -t public`).
2. Se connecter avec un compte membre.
3. Aller sur **Abonnement** (menu ou `/abonnement`).
4. Choisir **Mensuel** ou **Annuel**, puis cliquer sur **« Continuer vers le paiement »**.
5. Vous êtes redirigé vers Stripe Checkout. En mode test, utilisez la carte : **4242 4242 4242 4242**, une date d’expiration future, un CVC quelconque.
6. Après paiement, Stripe vous renvoie sur LibraryHub ; le compte de l’utilisateur est passé en **premium** (`user.is_premium = true`).

---

## Utiliser le statut premium dans l’app

- Dans un contrôleur : `if ($this->getUser() && $this->getUser()->isPremium()) { ... }`
- Dans un template Twig : `{% if app.user.isPremium %} ... {% endif %}`
- Pour restreindre une route à les utilisateurs premium, vous pouvez créer un voter ou un attribut `IsGranted` basé sur une propriété/custom role dérivée de `isPremium()`.

---

## Variables d’environnement (résumé)

| Variable | Obligatoire | Description |
|----------|-------------|-------------|
| `STRIPE_SECRET_KEY` | Oui | Clé secrète Stripe (Dashboard → Développeurs → Clés API). |
| `STRIPE_PUBLISHABLE_KEY` | Non (pour Checkout actuel) | Clé publique Stripe. |
| `STRIPE_PRICE_MONTHLY` | Non | ID du prix mensuel (`price_...`). Si vide, créé automatiquement. |
| `STRIPE_PRICE_ANNUAL` | Non | ID du prix annuel (`price_...`). Si vide, créé automatiquement. |

Ne commitez jamais `.env.local` (il doit être dans `.gitignore`).
