<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifie, pour les administrateurs, que la reconnaissance faciale a bien été effectuée
 * avant d'autoriser la connexion par mot de passe.
 */
final class AdminFaceUserChecker implements UserCheckerInterface
{
    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->hasRole('ROLE_ADMIN')) {
            // 2FA facultatif uniquement pour les administrateurs
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        // On applique la règle uniquement sur la soumission du formulaire de login
        if ($request->attributes->get('_route') !== 'app_login') {
            return;
        }

        // Si aucun token n'est envoyé, on laisse passer : login classique email + mot de passe.
        $submittedToken = $request->request->get('face_login_token');
        if ($submittedToken === null || $submittedToken === '') {
            return;
        }
        
        $session = $request->getSession();

       

        $sessionToken = $session->get('face_login_token');
        $sessionUserId = $session->get('face_login_user_id');
        
        if (
            !is_string($sessionToken)
            || !is_int($sessionUserId)
            || $sessionToken !== $submittedToken
            || $sessionUserId !== $user->getId()
        ) {
            throw new CustomUserMessageAccountStatusException(
                'La validation par reconnaissance faciale a expiré. Veuillez recommencer.'
            );
        }
        $sessionCreated = $session->get('face_login_created');

if (time() - $sessionCreated > 60) {
    throw new CustomUserMessageAccountStatusException(
        'Face validation expired.'
    );
}

        // Une fois vérifié, on invalide le token pour ce login
        $session->remove('face_login_token');
        $session->remove('face_login_user_id');
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Rien à faire après authentification.
    }
}

