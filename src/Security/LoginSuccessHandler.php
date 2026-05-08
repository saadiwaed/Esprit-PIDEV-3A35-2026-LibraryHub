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
    public function __construct(private readonly RouterInterface $router) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user  = $token->getUser();
        $roles = $user instanceof UserInterface ? $user->getRoles() : [];

        // MFA enabled → invalidate session, store pending user ID, redirect to verify
        if ($user instanceof User && $user->isMfaEnabled()) {
            $userId = $user->getId();
            $request->getSession()->invalidate();
            $request->getSession()->set('mfa_pending_user_id', $userId);

            return new RedirectResponse($this->router->generate('app_mfa_verify'));
            // ↑ Changed: go directly to /mfa/verify (not login?mfa=1)
        }

        

        // Admin / Librarian → backoffice
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_LIBRARIAN', $roles, true)) {
            return new RedirectResponse($this->router->generate('app_home'));
        }

        return new RedirectResponse($this->router->generate('app_frontoffice'));
    }
}