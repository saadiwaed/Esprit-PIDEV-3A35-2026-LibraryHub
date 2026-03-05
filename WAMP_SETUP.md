# Configurer LibraryHub avec WAMP

Après avoir installé WAMP (sans XAMPP), voici quoi faire pour la base de données.

## 1. Démarrer WAMP

- Lancer WAMP (icône dans la barre des tâches).
- Attendre que l’icône soit **verte** (Apache + MySQL démarrés).

## 2. Créer la base de données

Votre `.env` utilise la base **`libreryhub`** (user: `root`, pas de mot de passe).

### Option A – phpMyAdmin (recommandé)

1. Clic sur l’icône WAMP → **phpMyAdmin**.
2. Onglet **SQL**.
3. Coller et exécuter :
   ```sql
   CREATE DATABASE IF NOT EXISTS libreryhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

### Option B – Tout le script (utilisateurs + base)

Si vous préférez tout exécuter (droits + base), coller le contenu du fichier **`fix-mysql-host.sql`** dans l’onglet SQL de phpMyAdmin et exécuter.

## 3. Créer les tables (migrations Doctrine)

Dans le dossier du projet, en PowerShell ou CMD :

```powershell
cd D:\thepull\LibraryHub
php bin/console doctrine:migrations:migrate --no-interaction
```

Répondre **yes** si on vous demande de confirmer.

## 4. Vérifier

- Ouvrir l’application (ex. `http://localhost/` ou l’URL de votre projet).
- Ou tester la connexion :  
  `php bin/console doctrine:query:sql "SELECT 1"`

## Si la version de MySQL est différente

Dans phpMyAdmin, regarder la version (ex. 8.0.31). Si ce n’est pas 8.0.32, adapter dans le `.env` :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/libreryhub?serverVersion=8.0.31&charset=utf8mb4"
```

(Remplacer `8.0.31` par la version affichée.)

---

## 5. Créer un compte administrateur (connexion admin)

Sur une base neuve, il n’y a aucun utilisateur avec le rôle admin. Procédure :

### Étape 1 – Insérer les rôles en SQL

Dans **phpMyAdmin**, sélectionner la base **`libreryhub`**, onglet **SQL**, puis exécuter le fichier **`database/seed_admin_roles.sql`** (ou coller son contenu) :

```sql
INSERT IGNORE INTO `role` (name, description) VALUES
('ROLE_ADMIN', 'Administrateur'),
('ROLE_MEMBER', 'Membre standard'),
('ROLE_LIBRARIAN', 'Bibliothécaire');
```

### Étape 2 – Créer le compte admin en ligne de commande

Dans le dossier du projet :

```powershell
php bin/console app:create-admin
```

Cela crée un admin avec **email** `admin@libraryhub.local` et **mot de passe** `admin123`.  
Pour choisir email et mot de passe :

```powershell
php bin/console app:create-admin votre@email.com VotreMotDePasse
```

### Étape 3 – Se connecter

Aller sur **/login** et se connecter avec l’email et le mot de passe de l’admin. Vous aurez alors accès aux routes réservées à **ROLE_ADMIN** (gestion des utilisateurs, des rôles, etc.).
