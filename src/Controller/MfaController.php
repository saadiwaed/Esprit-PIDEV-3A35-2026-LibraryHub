<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MfaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MfaController extends AbstractController
{
    public function __construct(
        private readonly UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        private readonly FormLoginAuthenticator $formLoginAuthenticator,
    ) {}

    #[Route('/mfa/setup/{id}', name: 'app_mfa_setup', requirements: ['id' => '\d+'])]
    public function setup(
        User $user,
        MfaService $mfaService,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Block access if another logged-in user tries to access someone else's MFA setup
        $currentUser = $this->getUser();
        if ($currentUser && $user->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas configurer le MFA d\'un autre utilisateur.');
        }

        // If MFA already enabled, no need to set up again
        if ($user->isMfaEnabled()) {
            return $this->redirectToRoute('app_home');
        }

        // Generate a new secret each GET — keep it in the form as a hidden field
        // so the same secret is used when POST comes in
        $secret = $request->request->get('secret') ?? $mfaService->generateSecret();
        $qrCodeSvg = $mfaService->generateQrCode($user->getEmail(), $secret);

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');

            if ($mfaService->verifyCode($secret, $code ?? '')) {
                $backupCodes = $mfaService->generateBackupCodes();

                $user->setMfaSecret($secret);
                $user->setMfaEnabled(true);
                $user->setBackupCodes(implode(',', $backupCodes));

                $em->flush();

                $this->addFlash('success', '🎉 MFA activé avec succès ! Vous êtes maintenant connecté.');

                // Log the user in now that MFA is confirmed
                return $this->userAuthenticator->authenticateUser(
                    $user,
                    $this->formLoginAuthenticator,
                    $request
                ) ?? $this->redirectToRoute('app_home');
            } else {
                $this->addFlash('error', 'Code MFA invalide. Veuillez réessayer.');
            }
        }

        return $this->render('mfa/setup.html.twig', [
            'user'      => $user,
            'qrCodeSvg' => $qrCodeSvg,
            'secret'    => $secret,
        ]);
    }
}