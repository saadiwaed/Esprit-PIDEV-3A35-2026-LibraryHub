# Année Universitaire : 2025-2026
## Analyse statique du code avec PHPStan dans un projet Symfony (LIBRARYHUB)

---

## Objectifs

L’objectif de cet atelier est d’introduire l’outil **PHPStan** afin d’analyser statiquement le code PHP, **détecter les erreurs potentielles** et **améliorer la qualité du code** sans exécuter l’application.

À la fin de cet atelier, l’étudiant sera capable de :

● Installer et configurer PHPStan  
● Analyser un projet Symfony existant  
● Interpréter les erreurs détectées  
● Corriger des problèmes de typage et de logique simples  
● Ajuster le niveau d’analyse de PHPStan  

---

## Étude de cas

On considère l’application **LIBRARYHUB**, développée avec **Symfony 6.4**, déjà fonctionnelle, disposant :

● d’une connexion à la base de données,  
● des contrôleurs, services et entités existants,  
● d’un module “gestion des emprunts / loans” (Loan, Penalty, LoanRequest, Member, Book).

L’objectif est d’intégrer **PHPStan** afin d’améliorer la qualité et la robustesse du code, notamment sur la logique métier liée aux emprunts (retards, pénalités, demandes).

 PHPStan permet de repérer des erreurs de typage (retour manquant, paramètre non typé), des variables non définies, des appels à des méthodes inexistantes, des propriétés non initialisées, etc.

[Capture d’écran : aperçu du module “loans” dans LIBRARYHUB]

---

## Partie 1 : Installation de PHPStan

Installer PHPStan en tant que dépendance de développement et vérifier son installation.

> composer require --dev phpstan/phpstan

[Capture d’écran : installation de PHPStan via Composer]

Vérifier que PHPStan est bien installé :

> vendor/bin/phpstan version

[Capture d’écran : version de PHPStan affichée]

---

## Partie 2 : Première analyse du projet

Lancer une première analyse du code source **sans configuration**.

> vendor/bin/phpstan analyse src

Observer les erreurs et avertissements affichés.

PHPStan analyse le code et signale notamment :

● les variables non définies,  
● les types incorrects,  
● les appels de méthodes inexistantes,  
● les retours de fonctions incohérents,  
● les propriétés non initialisées.  

 À ce stade, on ne corrige pas tout : on **observe** les types d’erreurs qui reviennent le plus souvent dans un projet Symfony.

[Capture d’écran : premières erreurs PHPStan sur `src/`]

---

## Partie 3 : Création du fichier de configuration (`phpstan.neon`)

Créer manuellement un fichier nommé `phpstan.neon` à la racine du projet, puis ajouter la configuration suivante :

```neon
parameters:
    level: 5
    paths:
        - src
```

Cette configuration indique :

● un niveau d’analyse intermédiaire,  
● l’analyse du dossier `src`.  

[Capture d’écran : création du fichier `phpstan.neon` à la racine]

---

## Partie 4 : Analyse avec configuration

Relancer l’analyse PHPStan en utilisant le fichier de configuration.

> vendor/bin/phpstan analyse

Comparer les résultats avec l’analyse précédente.

 Avec un fichier de configuration, l’analyse devient plus stable et plus facile à reproduire (mêmes réglages, même périmètre).

[Capture d’écran : analyse avec `phpstan.neon`]

---

## Partie 5 : Augmentation du niveau d’analyse

Augmenter le niveau pour détecter davantage de problèmes.

Dans le fichier `phpstan.neon`, modifier le niveau :

```neon
parameters:
    level: 8
    paths:
        - src
```

Relancer l’analyse :

> vendor/bin/phpstan analyse

Observer l’augmentation du nombre et de la précision des erreurs détectées.

 Plus le niveau est élevé, plus PHPStan est strict (il vous aide à “verrouiller” le code sur le plan du typage).

[Capture d’écran : plus d’erreurs au niveau 8]

---

## Partie 6 : Correction d’erreurs courantes (exemples avant / après)

Corriger les erreurs PHPStan les plus fréquentes, telles que :

● absence de type de retour,  
● paramètres non typés,  
● accès à des valeurs nulles,  
● propriétés non initialisées,  
● appels de méthodes inexistantes.  

### Exemple concret (LIBRARYHUB / entité `Loan`)

Dans LIBRARYHUB, l’entité `Loan` contient une logique de retard (jours de retard).

**Exemple volontairement incorrect** (pour illustrer le type d’erreur détectée par PHPStan) :

```php
public function getDaysLate() { /* ... */ }
```

**Correction attendue** :

```php
public function getDaysLate(): int { /* ... */ }
```

 PHPStan peut signaler l’absence de type de retour (et, selon le niveau, vous inciter à typer les paramètres et les propriétés).

[Capture d’écran : erreur PHPStan “missing return type” puis disparition après correction]

Relancer PHPStan après chaque correction :

> vendor/bin/phpstan analyse

---

## Partie 7 : Analyse ciblée (contrôleurs et services)

Analyser uniquement certaines parties du projet (contrôleurs et services) :

> vendor/bin/phpstan analyse src/Controller  
> vendor/bin/phpstan analyse src/Service  

Corriger les erreurs liées à :

● l’injection de dépendances,  
● les types de retour des méthodes,  
● les paramètres manquants / non typés.  

### Exemple d’erreur fréquente en contrôleur

Dans un contrôleur Symfony, un paramètre non typé peut réduire la précision de l’analyse.

**Exemple (à éviter)** :

```php
public function show($id) { /* ... */ }
```

**Correction attendue** :

```php
public function show(int $id) { /* ... */ }
```

 PHPStan aide à repérer rapidement ces zones “floues” (typage manquant) et à améliorer la robustesse.

[Capture d’écran : analyse ciblée et correction progressive]

---

## Partie 8 : Ignorer certaines erreurs

Configurer PHPStan pour ignorer temporairement certaines erreurs spécifiques.

Dans le fichier `phpstan.neon`, ajouter une règle `ignoreErrors` :

```neon
parameters:
    ignoreErrors:
        - '#Call to an undefined method#'
```

Relancer l’analyse pour vérifier l’effet :

> vendor/bin/phpstan analyse

 L’objectif n’est pas d’ignorer définitivement : c’est une solution temporaire (par exemple pendant une migration/refactor).

[Capture d’écran : diminution des erreurs après ignoreErrors]

---

## Conclusion

L’analyse statique avec PHPStan permet d’améliorer la qualité du code **sans exécution**. Dans un projet Symfony comme **LIBRARYHUB**, elle apporte un gain immédiat sur :

● la fiabilité des types,  
● la détection précoce d’erreurs,  
● la maintenance et l’évolution du code (contrats plus clairs).  

 PHPStan s’inscrit naturellement dans la phase de test/qualité après le développement, et complète les tests unitaires.

---

## Travail demandé aux étudiants

Chaque étudiant doit :

1. Installer PHPStan dans son projet Symfony
2. Lancer une première analyse sur `src/`
3. Créer un fichier `phpstan.neon` (niveau 5)
4. Relancer l’analyse avec la configuration
5. Augmenter le niveau (ex : niveau 8) et observer les différences
6. Corriger au moins 2 erreurs courantes (type de retour, paramètres non typés, null, etc.)
7. Lancer une analyse ciblée sur `src/Controller` et `src/Service`
8. Ajouter une règle `ignoreErrors` pour une erreur (temporaire) et vérifier l’impact

Commande d’exécution :

> vendor/bin/phpstan analyse

Résultat attendu (exemple, si le périmètre analysé ne contient plus d’erreurs) :

```
[OK] No errors
```

