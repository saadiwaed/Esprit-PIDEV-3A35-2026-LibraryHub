# Erreur Aria "Read page with wrong checksum"

Lors de l’exécution du script SQL, l’erreur **"Got error 176 Read page with wrong checksum from storage engine Aria"** indique un problème avec les tables système de MariaDB (souvent la base `mysql`). **Avec WAMP (MySQL)** cette erreur n’apparaît en général pas.

## À faire (XAMPP / MariaDB)

### 1. Arrêter MySQL
- **XAMPP** : Panneau de contrôle → Stop MySQL.
- **WAMP** : Icône WAMP → MySQL → Stop Service.

### 2. Réparer les tables (en ligne de commande)

**PowerShell** (préfixer par `.\` depuis le dossier bin) :
```powershell
cd C:\xampp\mysql\bin
.\mysqlcheck.exe -u root -h 127.0.0.1 --repair --all-databases
```

**WAMP** (adapter la version mysql8.x.x) :
```powershell
cd C:\wamp64\bin\mysql\mysql8.0.31\bin
.\mysqlcheck.exe -u root -h 127.0.0.1 --repair --all-databases
```

Si une erreur apparaît, tenter : `.\mysql_upgrade.exe -u root -h 127.0.0.1`

### 3. Redémarrer MySQL puis exécuter le script

```cmd
cd D:\thepull\LibraryHub
C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 < fix-mysql-host.sql
```
(WAMP : utiliser le chemin `C:\wamp64\bin\mysql\mysql8.x.x\bin\mysql.exe`.)

### 4. Si le problème continue

- Sauvegarder le dossier `data` de MySQL puis réparer / réinstaller le module MySQL (voir CONNECTION_FIX.md), **ou**
- Passer à **WAMP** (MySQL) : pas d’Aria, configuration souvent plus simple pour `root@localhost`.

---

**Rappel :** après correction, **retirer** la ligne `skip-grant-tables` du `my.ini` et redémarrer MySQL.
