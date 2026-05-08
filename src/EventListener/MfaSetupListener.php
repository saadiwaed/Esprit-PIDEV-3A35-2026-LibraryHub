<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class MfaSetupListener
{
    private const ALLOWED_ROUTES = [
        'app_register',
        'app_login',
        'app_logout',
        'app_mfa_setup',
        'app_mfa_verify', // must be accessible without being logged in
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        if ($this->isRouteAllowed($route)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Logged-in user with MFA not configured → force setup
        if (!$user->isMfaEnabled()) {
            $url = $this->router->generate('app_mfa_setup', ['id' => $user->getId()]);
            $event->setResponse(new RedirectResponse($url));
        }

        // NOTE: We no longer check mfa_verified here.
        // When mfa_enabled=1, LoginSuccessHandler invalidates the session and
        // stores only mfa_pending_user_id — the user is NOT authenticated.
        // So $token->getUser() will never be a fully logged-in MFA user
        // unless they already passed /mfa/verify and were re-authenticated.
    }

    private function isRouteAllowed(?string $route): bool
    {
        if ($route === null) {
            return true;
        }
        foreach (self::ALLOWED_ROUTES as $allowed) {
            if (str_starts_with($route, $allowed)) {
                return true;
            }
        }
        return false;
    }
}