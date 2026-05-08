<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[IsGranted('ROLE_MEMBER')]
class MfaToggleController extends AbstractController
{
   #[Route('/mfa/toggle', name: 'app_mfa_toggle', methods: ['POST'])]
public function toggle(
    Request $request,
    EntityManagerInterface $em,
    TokenStorageInterface $tokenStorage,
): Response {
    if (!$this->isCsrfTokenValid('mfa_toggle', $request->request->get('_token'))) {
        throw $this->createAccessDeniedException('Token CSRF invalide.');
    }

    /** @var User $user */
    $user = $this->getUser();

    if ($user->isMfaEnabled()) {
    $user->setMfaEnabled(false);
    $user->setMfaSecret(null);
    $user->setBackupCodes(null);
    $em->flush();

    $this->addFlash('warning', '🔓 Double authentification désactivée. Votre compte est moins protégé.');

   
    return $this->redirectToRoute('app_frontoffice'); // /accueil — accessible to ROLE_MEMBER

    } else {
        $user->setMfaEnabled(false);
        $user->setMfaSecret(null);
        $user->setBackupCodes(null);
        $em->flush();

        return $this->redirectToRoute('app_mfa_setup', ['id' => $user->getId()]);
    }
}
#[Route('/mfa/backup-codes/download', name: 'app_mfa_backup_codes_download', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
public function downloadBackupCodes(): Response
{
    /** @var User $user */
    $user = $this->getUser();

    $codes = $user->getBackupCodes();

    if (!$codes) {
        $this->addFlash('warning', 'Aucun code de secours disponible.');
        return $this->redirectToRoute('app_frontoffice');
    }

    $formatted = implode("\n", explode(',', $codes));
    $content = "Codes de secours MFA\n";
    $content .= "====================\n\n";
    $content .= $formatted . "\n\n";
    $content .= "Gardez ces codes en lieu sûr.\n";
    $content .= "Chaque code ne peut être utilisé qu'une seule fois.\n";

    return new Response($content, 200, [
        'Content-Type'        => 'text/plain',
        'Content-Disposition' => 'attachment; filename="backup-codes.txt"',
    ]);
}
}