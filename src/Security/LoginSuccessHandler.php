<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user  = $token->getUser();
        $roles = $user instanceof UserInterface ? $user->getRoles() : [];

        // User has MFA enabled → store their ID in session and send back to
        // login page where the popup will open to ask for the TOTP code.
        // The real session/token is NOT yet fully established for them.
        if ($user instanceof User && $user->isMfaEnabled()) {
            // Invalidate the just-created security token so the user is NOT
            // considered logged in until they pass MFA.
            $request->getSession()->invalidate();
            $request->getSession()->set('mfa_pending_user_id', $user->getId());

            return new RedirectResponse(
                $this->router->generate('app_login') . '?mfa=1'
            );
        }

        // MFA not set up yet → force setup first
        if ($user instanceof User && !$user->isMfaEnabled()) {
            $request->getSession()->set('mfa_setup_user_id', $user->getId());
            return new RedirectResponse(
                $this->router->generate('app_mfa_setup', ['id' => $user->getId()])
            );
        }

        // Admin / Librarian → backoffice
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_LIBRARIAN', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_home'));
        }

        return new RedirectResponse($this->router->generate('app_frontoffice'));
    }
}