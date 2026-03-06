# Reconnaissance faciale LibraryHub – Guide pour la soutenance

Document de référence pour expliquer au jury l’intégration de la reconnaissance faciale (admin) dans LibraryHub.

---

## 1. Architecture générale

### Où se trouve le code

| Rôle | Fichiers |
|------|----------|
| **Backend (PHP)** | `src/Controller/Admin/FaceRegistrationController.php`, `src/Controller/Admin/FaceLoginController.php`, `src/Service/FaceRecognitionService.php` |
| **Sécurité** | `src/Security/AdminFaceUserChecker.php`, `src/Security/LoginSuccessHandler.php` |
| **Entité** | `src/Entity/User.php` (champ `faceDescriptor`) |
| **Frontend (JS)** | `public/js/admin-face-register.js`, `public/js/admin-face-login.js` |
| **Templates** | `templates/admin/face/register.html.twig`, `templates/admin/face/login.html.twig` |
| **Page de login** | `templates/security/login.html.twig` (lien « Connexion administrateur par reconnaissance faciale ») |
| **Config** | `config/packages/security.yaml`, `config/services.yaml`, `.env` (FACE_THRESHOLD) |
| **Migration BDD** | `migrations/Version20260225120000.php` (colonne `face_descriptor`) |

### Communication frontend ↔ backend

1. **Enregistrement**  
   - L’admin ouvre `/admin/face/register` (GET).  
   - Le JS charge face-api.js, active la webcam, calcule le descripteur, puis envoie **POST** `/admin/face/descriptor` avec le descripteur en JSON.  
   - Le contrôleur vérifie `ROLE_ADMIN`, enregistre le descripteur dans `User` et renvoie du JSON.

2. **Connexion**  
   - L’utilisateur ouvre `/admin/face-login` (GET).  
   - Le JS active la webcam, calcule le descripteur, puis envoie **POST** `/admin/face-login/recognize` avec le descripteur.  
   - Le backend compare à tous les admins ayant un `faceDescriptor`, renvoie un **token** + email si un admin correspond.  
   - Le JS affiche le formulaire email (pré-rempli) + mot de passe et envoie **POST** `/login` avec `_username`, `_password` et `face_login_token`.  
   - `AdminFaceUserChecker` vérifie le token en session avant d’accepter la connexion.

### Composants principaux

- **FaceRegistrationController** : page d’enregistrement + API POST pour sauvegarder le descripteur.  
- **FaceLoginController** : page de login biométrique + API POST `/recognize` pour identifier l’admin.  
- **FaceRecognitionService** : récupère les admins avec descripteur, calcule la distance euclidienne, retourne l’admin si distance ≤ seuil.  
- **AdminFaceUserChecker** : s’assure que, pour un login admin avec token, le token session correspond et est consommé.  
- **face-api.js** (côté client) : détection de visage et calcul du descripteur 128 floats.

---

## 2. Modèle d’IA utilisé

### Bibliothèque : face-api.js

- **Nom** : face-api.js (JavaScript, basé sur TensorFlow.js).  
- **Source** : https://github.com/justadudewhohacks/face-api.js  
- **Version utilisée** : 0.22.2 (CDN : `https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js`).

### Fonctionnement du modèle

1. **Détection du visage** : `TinyFaceDetector` + `faceLandmark68TinyNet` pour repérer le visage et les points de repère.  
2. **Descripteur facial** : `computeFaceDescriptor()` produit un **vecteur de 128 nombres flottants** qui représente le visage (embedding).  
3. **Comparaison** : côté serveur, on compare le descripteur envoyé avec ceux stockés en calculant la **distance euclidienne**. Si la distance est **inférieure ou égale au seuil** (ex. 0,43), on considère que c’est le même visage.

Formule utilisée dans `FaceRecognitionService` :

```php
// Distance euclidienne entre deux vecteurs de 128 floats
$distance = sqrt( sum( (a_i - b_i)² ) );
// Si distance <= 0.43 → match
```

### Où sont les modèles pré-entraînés

Les **poids des réseaux** (fichiers .json et .bin) ne sont **pas** dans le projet. Ils sont chargés depuis l’hébergement public du projet face-api.js :

- **URL** : `https://justadudewhohacks.github.io/face-api.js/models`  
- **Modèles utilisés** :  
  - `face_recognition_model` (descripteur 128D),  
  - `tiny_face_detector_model`,  
  - `face_landmark_68_tiny_model`.

Dans le code (ex. `admin-face-register.js`) :

```javascript
const modelPath = 'https://justadudewhohacks.github.io/face-api.js/models';
await faceapi.nets.faceRecognitionNet.loadFromUri(modelPath);
await faceapi.nets.tinyFaceDetector.loadFromUri(modelPath);
await faceapi.nets.faceLandmark68TinyNet.loadFromUri(modelPath);
```

Pour un déploiement offline, on peut télécharger ces fichiers et les mettre dans `public/models/` puis utiliser `modelPath = '/models'`.

### Pourquoi c’est un vrai modèle d’IA

- Les modèles sont des **réseaux de neurones** pré-entraînés (TensorFlow.js).  
- Ils transforment une image de visage en un **vecteur de 128 dimensions** (embedding) qui capture l’identité faciale.  
- La similarité entre deux visages est mesurée par une **distance géométrique** (euclidienne) entre ces vecteurs.  
- Aucune règle métier codée à la main sur les pixels : tout passe par l’apprentissage du réseau (reconnaissance faciale par deep learning).

---

## 3. Logique d’enregistrement du visage (admin)

### Accès à la page

- L’admin se connecte en classique (email + mot de passe).  
- Dans le **backoffice** : sidebar (`templates/home/sidebar.html.twig`) ou menu profil (`templates/home/navbar.html.twig`) avec le lien **« Reconnaissance faciale »** / **« Sécurité biométrique »** vers `path('admin_face_register')` → `/admin/face/register`.

### Déroulement

1. **Activation de la webcam**  
   - Clic sur « Activer la webcam ».  
   - Le JS appelle `navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })`.  
   - Le flux est affiché dans `<video id="face-video">`.  
   - Les modèles face-api.js sont chargés depuis l’URL ci-dessus.

2. **Génération du descripteur (côté client)**  
   - Clic sur « Enregistrer mon visage ».  
   - `faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true)` pour détecter un visage.  
   - `faceapi.computeFaceDescriptor(video)` retourne un descripteur (Float32Array).  
   - Conversion en tableau JSON : `JSON.stringify(Array.from(descriptor))`.

3. **Envoi au serveur**  
   - **Route** : `POST /admin/face/descriptor`  
   - **Corps** : `FormData` avec une clé `descriptor` (chaîne JSON du tableau de 128 floats).  
   - **Contrôleur** : `FaceRegistrationController::saveDescriptor()`.

4. **Stockage dans User**  
   - Le contrôleur récupère l’utilisateur connecté, vérifie `ROLE_ADMIN`.  
   - Il parse le descripteur (JSON → tableau), puis le convertit en chaîne `"v1,v2,...,v128"` pour stockage.  
   - `$user->setFaceDescriptor($descriptor)` puis `$entityManager->flush()`.  
   - En base : colonne `user.face_descriptor` (LONGTEXT), remplie avec cette chaîne.

Extrait côté contrôleur :

```php
// FaceRegistrationController.php
$descriptor = $request->request->get('descriptor');
// ...
$user->setFaceDescriptor($descriptor);
$entityManager->persist($user);
$entityManager->flush();
```

---

## 4. Logique de connexion par reconnaissance faciale

### Du bouton sur /login jusqu’au backoffice

1. Sur **`/login`** : lien « Connexion administrateur par reconnaissance faciale » → `path('admin_face_login_page')` → **GET** `/admin/face-login`.  
2. Page **face-login** : instructions, boutons « Activer la webcam » et « Scanner mon visage », zone vidéo, formulaire email/mot de passe masqué au départ.

### Étape 1 : scan et comparaison

1. L’utilisateur active la webcam (même principe qu’en enregistrement).  
2. Clic sur « Scanner mon visage » : le JS détecte le visage, calcule le descripteur, envoie **POST** `/admin/face-login/recognize` avec ce descripteur (JSON).  
3. **FaceLoginController::recognize()** :  
   - Décode le descripteur en tableau de floats.  
   - Appelle `$this->faceRecognition->findMatchingAdmin($probe)`.  
4. **FaceRecognitionService::findMatchingAdmin()** :  
   - Récupère tous les utilisateurs avec `ROLE_ADMIN` et `faceDescriptor` non null.  
   - Pour chaque admin, décode `face_descriptor` en tableau de floats.  
   - Calcule la distance euclidienne entre le descripteur reçu et chaque descripteur stocké.  
   - Retient l’admin avec la **plus petite distance**.  
   - Si cette distance est **≤ seuil** (0,43) → retourne cet utilisateur ; sinon retourne null.  
5. Si un admin est trouvé : le contrôleur génère un **token** (`bin2hex(random_bytes(32))`), le met en **session** avec l’id de l’utilisateur :  
   - `$session->set('face_login_token', $token)`  
   - `$session->set('face_login_user_id', $user->getId())`  
   - Réponse JSON : `isSuccessful`, `message`, `token`, `email`.

### Étape 2 : formulaire mot de passe

1. Le JS reçoit la réponse, pré-remplit l’email (lecture seule), met le token dans un champ caché `face_login_token`, affiche le formulaire et masque l’étape visage.  
2. L’utilisateur saisit son **mot de passe** et soumet le formulaire.  
3. Le formulaire fait **POST** vers `app_login` (même URL que le login classique) avec :  
   - `_username` (email),  
   - `_password`,  
   - `_csrf_token`,  
   - `face_login_token`.

### Étape 3 : validation et connexion

1. Symfony authentifie l’utilisateur (email + mot de passe) via le firewall.  
2. Avant d’accepter la connexion, le **UserChecker** `AdminFaceUserChecker` est appelé.  
3. **AdminFaceUserChecker::checkPreAuth()** :  
   - Ne fait rien si l’utilisateur n’a pas `ROLE_ADMIN` ou si la route n’est pas `app_login`.  
   - Si la requête ne contient pas `face_login_token`, le login est considéré comme classique → on laisse passer.  
   - Si `face_login_token` est présent : comparaison avec `$session->get('face_login_token')` et vérification que `$session->get('face_login_user_id')` correspond à l’utilisateur connecté.  
   - Si tout est bon : suppression du token en session (`face_login_token`, `face_login_user_id`) pour éviter la réutilisation.  
   - Si le token est invalide ou expiré : exception « La validation par reconnaissance faciale a expiré. Veuillez recommencer. »  
4. Une fois la connexion réussie, `LoginSuccessHandler` redirige l’admin vers le dashboard (`app_home`).

---

## 5. Sécurité

### Protection des routes

- **Enregistrement** : `FaceRegistrationController` est protégé par `#[IsGranted('ROLE_ADMIN')]` et préfixe `#[Route('/admin/face', name: 'admin_face_')]`. Seul un admin connecté peut ouvrir la page et appeler `POST /admin/face/descriptor`.  
- **Connexion biométrique** : `GET /admin/face-login` et `POST /admin/face-login/recognize` sont en **PUBLIC_ACCESS** dans `security.yaml` pour permettre l’accès sans être connecté.  
- Le reste du backoffice reste protégé par rôles (ROLE_ADMIN, ROLE_LIBRARIAN, etc.).

### Limitation des abus

- Pas de rate-limiting spécifique dans le code fourni ; on peut en ajouter (ex. Symfony RateLimiter) sur `/admin/face-login/recognize`.  
- Le token de session est **à usage unique** : après un login réussi, il est supprimé.  
- Le token est **lié à l’utilisateur** : `face_login_user_id` doit correspondre à l’utilisateur chargé par email/mot de passe.  
- Aucune **image** n’est stockée, uniquement le descripteur (128 floats), ce qui limite le risque en cas de fuite de données.

### Seuil de similarité

- **Paramètre** : `app.face.threshold` dans `config/services.yaml`, valeur lue depuis `%env(float:FACE_THRESHOLD)%`.  
- **Fichier** : `.env` → `FACE_THRESHOLD=0.43`.  
- **Utilisation** : dans `FaceRecognitionService`, le constructeur reçoit `$threshold` ; un admin n’est retourné que si `$bestDistance <= $this->threshold`.  
- **Valeur 0,43** : courante pour face-api.js (équilibre entre faux positifs et faux négatifs) ; on peut la modifier dans `.env` pour durcir ou assouplir la reconnaissance.

---

## 6. Démonstration pour le jury

### Fichiers à montrer (ordre logique)

1. **`src/Entity/User.php`** : propriété `$faceDescriptor` et getter/setter.  
2. **`migrations/Version20260225120000.php`** : ajout de la colonne `face_descriptor`.  
3. **`src/Service/FaceRecognitionService.php`** : `findMatchingAdmin()`, distance euclidienne, seuil.  
4. **`src/Controller/Admin/FaceRegistrationController.php`** : route GET register, route POST descriptor, vérification ROLE_ADMIN.  
5. **`src/Controller/Admin/FaceLoginController.php`** : route recognize, génération du token, enregistrement en session.  
6. **`src/Security/AdminFaceUserChecker.php`** : vérification du token à la soumission du formulaire de login.  
7. **`public/js/admin-face-register.js`** : chargement des modèles, webcam, `computeFaceDescriptor`, envoi POST.  
8. **`public/js/admin-face-login.js`** : même chose pour le login + affichage du formulaire email/mot de passe avec token.  
9. **`templates/security/login.html.twig`** : lien vers `admin_face_login_page`.  
10. **`templates/home/sidebar.html.twig`** (ou navbar) : lien vers `admin_face_register`.

### Points forts à expliquer

- **Séparation des responsabilités** : service dédié (FaceRecognitionService), contrôleurs admin, UserChecker pour la 2e étape.  
- **Pas d’image en base** : uniquement un vecteur de 128 floats, conforme au RGPD/privacy.  
- **Double facteur** : visage + mot de passe pour les admins qui passent par la reconnaissance faciale.  
- **Modèle d’IA réel** : face-api.js, réseaux de neurones, descripteur 128D, comparaison par distance.

### Test rapide à faire devant le jury

1. Se connecter en admin (email + mot de passe).  
2. Aller dans le backoffice → « Reconnaissance faciale » (sidebar ou profil) → Activer la webcam → Enregistrer le visage.  
3. Se déconnecter.  
4. Sur `/login`, cliquer sur « Connexion administrateur par reconnaissance faciale ».  
5. Activer la webcam → Scanner le visage → vérifier que le formulaire email/mot de passe s’affiche avec l’email pré-rempli.  
6. Saisir le mot de passe → Se connecter → vérifier la redirection vers le dashboard.

---

## 7. Résumé simple à réciter au jury

- « La reconnaissance faciale est réservée aux **administrateurs** : ils peuvent s’enregistrer depuis le backoffice et se connecter en deux étapes, visage puis mot de passe. »  
- « Côté **navigateur**, on utilise la librairie **face-api.js** (réseaux de neurones en JavaScript) pour détecter le visage et calculer un **descripteur de 128 nombres** ; aucun enregistrement d’image, uniquement ce vecteur. »  
- « Ce descripteur est **envoyé au serveur** : en enregistrement il est stocké dans la table `user` (colonne `face_descriptor`), et en connexion il est **comparé** à ceux de tous les admins via une **distance euclidienne** ; si la distance est sous un **seuil** (0,43), on considère que c’est le même visage. »  
- « Après une reconnaissance réussie, le serveur génère un **token temporaire** en session et renvoie l’email de l’admin ; l’utilisateur saisit alors son **mot de passe** sur le formulaire classique. Un **UserChecker** vérifie que le token présent dans la requête correspond bien à la session avant d’accepter la connexion, puis le token est supprimé pour éviter toute réutilisation. »  
- « Les **routes d’enregistrement** sont protégées par `ROLE_ADMIN` ; la page de **login biométrique** est publique pour permettre la première étape, mais l’accès au backoffice reste protégé par le mot de passe et le contrôle du token. »

Bonne soutenance.
