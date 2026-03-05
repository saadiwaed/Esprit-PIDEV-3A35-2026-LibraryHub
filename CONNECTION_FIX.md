# Corriger : "Host 'localhost' is not allowed to connect to this MySQL/MariaDB server"

Même avec `-h 127.0.0.1`, le serveur refuse la connexion. Il faut **démarrer MySQL/MariaDB en mode réparation** (`skip-grant-tables`), puis créer les utilisateurs `root@localhost` et `root@127.0.0.1`.

**Avec WAMP** : après installation, MySQL accepte en général `root@localhost` (sans mot de passe). Il suffit souvent de créer la base `libreryhub` depuis phpMyAdmin ou d’exécuter `fix-mysql-host.sql` (voir Méthode 2). Si l’erreur « Host not allowed » apparaît, suivre la Méthode 1 en utilisant les chemins WAMP ci‑dessous.

---

## Chemins selon l’installation

|           | XAMPP                    | WAMP (64 bit)                          |
|-----------|--------------------------|----------------------------------------|
| MySQL bin | `C:\xampp\mysql\bin`     | `C:\wamp64\bin\mysql\mysql8.x.x\bin` * |
| Config    | `C:\xampp\mysql\bin\my.ini` | `C:\wamp64\bin\mysql\mysql8.x.x\bin\my.ini` * |

\* Remplacer `mysql8.x.x` par votre version (ex. `mysql8.0.31`). Vérifier dans `C:\wamp64\bin\mysql\`.

---

## Méthode 1 : Mode skip-grant-tables (recommandé si la connexion est refusée)

### 1. Arrêter MySQL/MariaDB
- **XAMPP** : Panneau de contrôle → **Stop** à côté de MySQL.
- **WAMP** : Clic sur l’icône WAMP → MySQL → **Stop Service**.

### 2. Activer skip-grant-tables
Ouvrir le fichier de configuration avec un éditeur de texte (en **Administrateur** si nécessaire) :

- **XAMPP** : `C:\xampp\mysql\bin\my.ini`
- **WAMP** : `C:\wamp64\bin\mysql\mysql8.x.x\bin\my.ini` (adapter la version)
- Chercher la section **`[mysqld]`** et ajouter **juste en dessous** la ligne :
  ```ini
  skip-grant-tables
  ```
  Enregistrer et fermer.

### 3. Redémarrer MySQL
- **XAMPP** : **Start** à côté de MySQL.
- **WAMP** : Clic sur l’icône WAMP → MySQL → **Start Service**.

### 4. Se connecter et exécuter les commandes
Ouvrir **PowerShell** (ou CMD) et aller dans le dossier du projet :
```powershell
cd D:\thepull\LibraryHub
```

**Option A – Exécuter le fichier SQL avec cmd** (PowerShell : le `<` ne marche qu’avec `cmd /c`) :

XAMPP :
```powershell
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 < fix-mysql-host.sql"
```

WAMP (adapter `mysql8.0.xx` selon votre version) :
```powershell
cmd /c "C:\wamp64\bin\mysql\mysql8.0.31\bin\mysql.exe -u root -h 127.0.0.1 < fix-mysql-host.sql"
```

**Option B – Coller les commandes dans le client :**
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1
```
(WAMP : utiliser le chemin `C:\wamp64\bin\mysql\mysql8.x.x\bin\mysql.exe`.)
Dans le prompt `MariaDB [(none)]>` coller puis Entrée :
```sql
FLUSH PRIVILEGES;
CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
CREATE DATABASE IF NOT EXISTS libreryhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit
```

### 5. Désactiver skip-grant-tables
1. Arrêter MySQL (XAMPP ou WAMP).
2. Ouvrir à nouveau le `my.ini`, **supprimer** la ligne `skip-grant-tables` sous `[mysqld]`, enregistrer.
3. Redémarrer MySQL.

### 6. Tester
- **phpMyAdmin** : ouvrir dans le navigateur (XAMPP : http://localhost/phpmyadmin — WAMP : clic sur l’icône WAMP → phpMyAdmin).
- Ou en ligne de commande : `C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1` (adapter le chemin pour WAMP).

---

## Méthode 2 : Si la connexion avec -h 127.0.0.1 fonctionne déjà

Exécuter le script depuis **CMD** (Invite de commandes, pas PowerShell pour le `<`) :
```cmd
cd D:\thepull\LibraryHub
C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 < fix-mysql-host.sql
```
(WAMP : remplacer par le chemin `C:\wamp64\bin\mysql\mysql8.x.x\bin\mysql.exe`.)

Ou en **PowerShell** (sans `<`) :
```powershell
Get-Content fix-mysql-host.sql | C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1
```

---

## Anciennes versions MariaDB (sans `CREATE USER IF NOT EXISTS`)

Remplacer les commandes par :

```sql
FLUSH PRIVILEGES;
CREATE USER 'root'@'localhost' IDENTIFIED BY '';
CREATE USER 'root'@'127.0.0.1' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```
