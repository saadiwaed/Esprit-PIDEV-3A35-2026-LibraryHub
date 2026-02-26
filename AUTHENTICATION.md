# Authentication System - LibraryHub

## Overview
This project uses **Symfony's native session-based authentication** (Form Login) - NO JWT, NO FOSUserBundle, NO AdminBundle!

## How It Works

### 1. Homepage for Visitors (`/`)
- **Public access** - No login required
- Visitors can explore the LibraryHub frontoffice
- Clear "Connexion" and "S'inscrire" buttons in the navigation
- Beautiful landing page showcasing the library features

### 2. Registration (`/register`)
- New users can register with: firstName, lastName, email, password
- Password is automatically hashed using Symfony's password hasher
- New users start with status `PENDING` (admin must activate them)
- After registration, users are redirected to the login page

### 3. Login (`/login`)
- Users login with **email** and **password**
- CSRF protection is enabled automatically
- Optional "Remember Me" checkbox (stays logged in for 1 week)

### 4. Role-Based Redirects (Automatic)
After successful login, users are redirected based on their roles:

#### Admin/Librarian Dashboard (`/dashboard`)
- If user has `ROLE_ADMIN` or `ROLE_LIBRARIAN` → redirected to `/dashboard`
- Full access to:
  - User management (`/user`)
  - Role management (`/role`) - ROLE_ADMIN only
  - Reading profiles (`/reading-profile`)
  - Clubs, Events, Reading Challenges

#### Member Homepage (`/`)
- If user has only `ROLE_MEMBER` or `ROLE_USER` → redirected to `/` (homepage)
- Access to public features and member-specific content

### 5. Access Control
Configured in `config/packages/security.yaml`:

```yaml
access_control:
    # Public routes (accessible to everyone, even guests)
    - { path: ^/$, roles: PUBLIC_ACCESS }
    - { path: ^/login, roles: PUBLIC_ACCESS }
    - { path: ^/register, roles: PUBLIC_ACCESS }
    
    # Admin and Librarian routes (dashboard, user management, etc.)
    - { path: ^/user, roles: [ROLE_ADMIN, ROLE_LIBRARIAN] }
    - { path: ^/role, roles: ROLE_ADMIN }
    - { path: ^/reading-profile, roles: [ROLE_ADMIN, ROLE_LIBRARIAN] }
    - { path: ^/club, roles: [ROLE_ADMIN, ROLE_LIBRARIAN] }
    - { path: ^/event, roles: [ROLE_ADMIN, ROLE_LIBRARIAN] }
    - { path: ^/reading-challenge, roles: [ROLE_ADMIN, ROLE_LIBRARIAN] }
    - { path: ^/dashboard, roles: ROLE_USER }
    
    # Everything else requires authentication
    - { path: ^/, roles: ROLE_USER }
```

### 6. Logout (`/logout`)
- Click "Se déconnecter" in the navbar dropdown
- Session is destroyed
- User is redirected to login page

## Files Created/Modified

### New Files:
1. `src/Controller/RegistrationController.php` - Handles user registration
2. `src/Controller/SecurityController.php` - Handles login/logout
3. `src/Form/RegistrationFormType.php` - Registration form
4. `src/Security/LoginSuccessHandler.php` - Role-based redirect logic
5. `templates/registration/register.html.twig` - Registration page
6. `templates/security/login.html.twig` - Login page

### Modified Files:
1. `config/packages/security.yaml` - Complete authentication configuration
2. `templates/home/navbar.html.twig` - Logout link + user info display (dashboard)
3. `templates/frontoffice/base.html.twig` - Login/register buttons + user info display (frontoffice)
4. `src/Controller/HomeController.php` - Dashboard route changed to `/dashboard`
5. `src/Controller/FrontofficeController.php` - Homepage route changed to `/`

## Testing the System

### Step 1: Create Roles (if not already done)
Go to `/dashboard` (you'll need to login first) and create these roles:
- `ROLE_ADMIN` - Administrator
- `ROLE_LIBRARIAN` - Librarian  
- `ROLE_MEMBER` - Member

### Step 2: Create Admin User
- Go to `/user/new`
- Create a user and assign `ROLE_ADMIN` role

### Step 3: Test as Visitor (No Login)
1. Go to http://127.0.0.1:8000/
2. You should see the frontoffice homepage without being logged in
3. Click "Connexion" or "S'inscrire" buttons in the navigation

### Step 4: Test Registration
1. Click "S'inscrire" or go to http://127.0.0.1:8000/register
2. Fill in the form and register
3. You should be redirected to login page

### Step 5: Test Login
1. Go to http://127.0.0.1:8000/login
2. Login with your credentials:
   - Admin/Librarian → redirected to `/dashboard`
   - Member → redirected to `/` (homepage with user menu)

### Step 6: Test Logout
1. Click on your avatar in the top right
2. Click "Se déconnecter"
3. You should be redirected to login page

## Routes Overview
- `/` - Homepage (public, frontoffice)
- `/register` - Registration page (public)
- `/login` - Login page (public)
- `/logout` - Logout (authenticated users)
- `/dashboard` - Admin dashboard (ROLE_ADMIN, ROLE_LIBRARIAN)
- `/user` - User management (ROLE_ADMIN, ROLE_LIBRARIAN)
- `/role` - Role management (ROLE_ADMIN only)
- `/reading-profile` - Reading profiles (ROLE_ADMIN, ROLE_LIBRARIAN)
- `/club` - Clubs management (ROLE_ADMIN, ROLE_LIBRARIAN)
- `/event` - Events management (ROLE_ADMIN, ROLE_LIBRARIAN)
- `/reading-challenge` - Reading challenges (ROLE_ADMIN, ROLE_LIBRARIAN)

## Security Features
✅ Session-based authentication (more secure for web apps than JWT)
✅ CSRF protection on login and forms
✅ Password hashing (bcrypt/argon2)
✅ Remember Me functionality
✅ Role-based access control
✅ Automatic redirect based on user role
✅ Server-side validation only (no HTML5 validation)
✅ Public homepage accessible without login
✅ Clear navigation for visitors and logged-in users

## Why This is Better Than JWT
- ✅ **Simpler** - No token management, no key generation issues
- ✅ **More secure** for web apps - Session cookies are httpOnly
- ✅ **Native Symfony** - Uses built-in security component
- ✅ **Better UX** - No token expiration issues
- ✅ **Remember Me** - Easy to implement
- ✅ **Public pages** - Easy to mix public and private content
