# Workshop : Les tests unitaires dans un projet Symfony (cas Loan)

Année Universitaire : 2025-2026  
Workshops Framework Symfony 6.4 — UP-Web  
Workshop : Les tests unitaires dans un projet Symfony (gestion des emprunts)

---

## Introduction

Dans le cadre d’un projet, le travail se déroule généralement en plusieurs phases : conception, développement (entités, CRUD, services), **phase de test**, puis livraison.

La phase de test intervient **après** la phase de développement. Son objectif est de **valider** et de **sécuriser** le code réalisé avant la livraison.

Il existe plusieurs types de tests dans un projet logiciel : tests unitaires, tests fonctionnels, tests d’intégration.

Dans ce workshop, nous nous concentrons sur **les tests unitaires**, car ils représentent la première étape de la phase de test : ils permettent de vérifier, de manière simple et isolée, la logique interne du code avant d’aborder des tests plus globaux.

 Les tests unitaires permettent de valider les règles métier, de sécuriser les évolutions et d’améliorer la qualité globale du projet.

---

## Première approche : création d’un test avec `make:test`

Symfony fournit un outil pour générer automatiquement la structure d’un test via la commande :

```bash
php bin/console make:test
```

Cette commande propose plusieurs types de tests.

Pour des tests unitaires, il faut choisir :

 **TestCase**

[Capture d’écran : choix du type de test dans la console]

Ensuite, on donne un nom à la classe de test, par exemple :

 `LoanValidatorTest`

[Capture d’écran : saisie du nom de classe `LoanValidatorTest`]

---

## PARTIE PRATIQUE

### 1. Règles métier à valider (entité `Loan`)

Pour la gestion des emprunts (`Loan`), on définit les règles métier suivantes :

1. La date d’échéance (`dueDate`) doit être **postérieure ou égale** à la date d’emprunt (`checkoutTime`)
2. Le nombre de renouvellements (`renewalCount`) ne peut pas être **négatif**

 Ces règles doivent être validées par des **tests unitaires**.

---

### 2. Organisation des dossiers

Pour une bonne organisation du projet, on adopte la structure suivante :

```
src/
└── Service/
    └── LoanValidator.php

tests/
└── Service/
    └── LoanValidatorTest.php
```

 Le fichier généré par `make:test` peut être déplacé dans `tests/Service/`.

---

### 3. Création du service métier (`LoanValidator.php`)

On crée un service métier chargé de valider un emprunt.

Fichier : `src/Service/LoanValidator.php`

```php
<?php

namespace App\Service;

use App\Entity\Loan;

final class LoanValidator
{
    public function validate(Loan $loan): bool
    {
        if (!$loan->getCheckoutTime() || !$loan->getDueDate()) {
            throw new \InvalidArgumentException('La date d’emprunt et la date d’échéance sont obligatoires.');
        }

        // Règle 1 : dueDate >= checkoutTime
        $checkoutDay = new \DateTimeImmutable($loan->getCheckoutTime()->format('Y-m-d'));
        $dueDay = new \DateTimeImmutable($loan->getDueDate()->format('Y-m-d'));

        if ($dueDay < $checkoutDay) {
            throw new \InvalidArgumentException('La date d’échéance doit être postérieure ou égale à la date d’emprunt.');
        }

        // Règle 2 : renewalCount >= 0
        if ($loan->getRenewalCount() < 0) {
            throw new \InvalidArgumentException('Le nombre de renouvellements ne peut pas être négatif.');
        }

        return true;
    }
}
```

 La validation de l’entité `Loan` repose sur une comparaison des dates (à la journée) et sur `InvalidArgumentException` pour signaler immédiatement toute donnée invalide.

---

### 4. Implémentation du test unitaire généré (`LoanValidatorTest.php`)

On implémente ensuite le test unitaire afin de vérifier :
- qu’un emprunt correct est accepté
- qu’une date d’échéance incorrecte est rejetée
- qu’un nombre de renouvellements négatif est rejeté

Fichier : `tests/Service/LoanValidatorTest.php`

```php
<?php

namespace App\Tests\Service;

use App\Entity\Loan;
use App\Service\LoanValidator;
use PHPUnit\Framework\TestCase;

final class LoanValidatorTest extends TestCase
{
    public function testValidLoan(): void
    {
        $loan = new Loan();
        $loan->setCheckoutTime(new \DateTime('2026-03-01 10:00:00'));
        $loan->setDueDate(new \DateTime('2026-03-01'));
        $loan->setRenewalCount(0);

        $validator = new LoanValidator();
        $this->assertTrue($validator->validate($loan));
    }

    public function testLoanWithInvalidDueDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $loan = new Loan();
        $loan->setCheckoutTime(new \DateTime('2026-03-02 10:00:00'));
        $loan->setDueDate(new \DateTime('2026-03-01'));
        $loan->setRenewalCount(0);

        (new LoanValidator())->validate($loan);
    }

    public function testLoanWithNegativeRenewalCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $loan = new Loan();
        $loan->setCheckoutTime(new \DateTime('2026-03-01 10:00:00'));
        $loan->setDueDate(new \DateTime('2026-03-10'));
        $loan->setRenewalCount(-1);

        (new LoanValidator())->validate($loan);
    }
}
```

 Cette classe de test vérifie automatiquement que le service `LoanValidator` valide un emprunt correct et rejette les données invalides conformément aux règles métier définies.

---

### 5. Exécution des tests

Pour exécuter les tests :

```bash
php bin/phpunit
```

Résultat attendu :

[Capture d’écran : résultat de l’exécution des tests]

 chaque point correspond à un test  
 `OK` indique que la logique métier est valide

```
OK (3 tests, 3 assertions)
```

---

## Conclusion

Les tests unitaires constituent la première étape de la phase de test. Ils permettent de valider la logique métier et de sécuriser le projet avant la livraison finale.

---

## Travail demandé aux étudiants

Chaque étudiant doit :

1. Choisir une entité de son projet
2. Identifier au moins deux règles métier
3. Créer un service métier correspondant
4. Générer un test avec `make:test`
5. Implémenter les tests unitaires
6. Vérifier l’exécution des tests avec :

```bash
php bin/phpunit
```

