<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MfaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MfaVerifyController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface   $tokenStorage,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[Route('/mfa/verify', name: 'app_mfa_verify', methods: ['GET', 'POST'])]
    public function verify(
        MfaService $mfaService,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $session = $request->getSession();
        $userId  = $session->get('mfa_pending_user_id');

        // No pending MFA → back to login
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->find($userId);

        if (!$user || !$user->isMfaEnabled()) {
            $session->remove('mfa_pending_user_id');
            return $this->redirectToRoute('app_login');
        }

        // ── AJAX POST from the modal ─────────────────────────────────────────
        if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
            $data  = json_decode($request->getContent(), true);
            $code  = trim((string) ($data['code'] ?? ''));
            $valid = false;

            if ($this->verifyBackupCode($user, $code)) {
                $em->flush();
                $valid = true;
            } elseif ($mfaService->verifyCode($user->getMfaSecret(), $code)) {
                $valid = true;
            }

            if ($valid) {
                $session->remove('mfa_pending_user_id');

                // Manually create the security token and store it
                // (avoids calling LoginSuccessHandler again which would loop)
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                $this->tokenStorage->setToken($token);
                $session->set('_security_main', serialize($token));

                // Dispatch interactive login event (needed for remember_me etc.)
                $this->eventDispatcher->dispatch(
                    new InteractiveLoginEvent($request, $token),
                    'security.interactive_login'
                );

                // Decide redirect based on roles (mirrors LoginSuccessHandler logic)
                $roles = $user->getRoles();
                if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_LIBRARIAN', $roles, true)) {
                    $redirectUrl = $this->generateUrl('app_home');
                } else {
                    $redirectUrl = $this->generateUrl('app_frontoffice');
                }

                return new JsonResponse(['success' => true, 'redirect' => $redirectUrl]);
            }

            return new JsonResponse(
                ['success' => false, 'message' => 'Code invalide. Veuillez réessayer.'],
                422
            );
        }

        // ── GET: render the verify page ──────────────────────────────────────
        return $this->render('mfa/verify.html.twig');
    }

    private function verifyBackupCode(User $user, string $inputCode): bool
    {
        $raw = $user->getBackupCodes();
        if (!$raw) {
            return false;
        }

        $codes     = array_filter(explode(',', $raw));
        $inputNorm = strtoupper(str_replace(['-', ' '], '', $inputCode));

        foreach ($codes as $i => $stored) {
            if (hash_equals(strtoupper(str_replace('-', '', $stored)), $inputNorm)) {
                unset($codes[$i]);
                $user->setBackupCodes(implode(',', $codes));
                return true;
            }
        }

        return false;
    }
}