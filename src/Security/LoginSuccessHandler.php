<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new RedirectResponse('/');
        }
        
        $roles = $user->getRoles();

        // Check if user has admin or librarian role -> redirect to dashboard
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_LIBRARIAN', $roles)) {
            return new RedirectResponse($this->router->generate('app_home'));
        }

        // Default: redirect members to frontoffice
        return new RedirectResponse($this->router->generate('app_frontoffice'));
    }
}
