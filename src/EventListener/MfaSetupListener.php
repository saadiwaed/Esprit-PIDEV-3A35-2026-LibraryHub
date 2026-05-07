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
    // Routes that are always accessible without MFA (whitelist)
    private const ALLOWED_ROUTES = [
        'app_register',
        'app_login',
        'app_logout',
        'app_mfa_setup',
        '_wdt',       // Symfony web debug toolbar
        '_profiler',  // Symfony profiler
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RouterInterface $router,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        // Only handle the main request, not sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        // Allow whitelisted routes without any check
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

        // If user is logged in but MFA is not enabled → force MFA setup
        if (!$user->isMfaEnabled()) {
            $url = $this->router->generate('app_mfa_setup', ['id' => $user->getId()]);
            $event->setResponse(new RedirectResponse($url));
        }
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