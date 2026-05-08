<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetController extends AbstractController
{
    // ──────────────────────────────────────────────────────────────
    // TOKEN HELPERS
    // The token is a self-contained signed string:
    //   base64(json{email, exp})  +  "."  +  HMAC-SHA256(payload, APP_SECRET)
    // Nothing is stored in the DB or on disk.
    // ──────────────────────────────────────────────────────────────

    private function generateSignedToken(string $email): string
    {
        $payload   = base64_encode(json_encode([
            'email' => $email,
            'exp'   => time() + 3600,   // valid for 1 hour
        ]));

        $signature = hash_hmac('sha256', $payload, $this->getParameter('kernel.secret'));

        return $payload . '.' . $signature;
    }

    /**
     * Returns the decoded payload array on success, or null if the token
     * is malformed, tampered with, or expired.
     */
    private function verifySignedToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        // Constant-time comparison to prevent timing attacks
        $expected = hash_hmac('sha256', $payload, $this->getParameter('kernel.secret'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);

        if (!is_array($data) || empty($data['email']) || empty($data['exp'])) {
            return null;
        }

        // Check expiry
        if ($data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    // ──────────────────────────────────────────────────────────────
    // STEP 1 : Show & handle "forgot password" form
    // ──────────────────────────────────────────────────────────────

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $user  = $userRepository->findOneBy(['email' => $email]);

            // Always show success to avoid user enumeration attacks
            if ($user) {
                $token    = $this->generateSignedToken($email);
                $resetUrl = $this->generateUrl(
                    'app_reset_password',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $mailer->send(
                    (new Email())
                        ->from('noreply@libraryhub.com')
                        ->to($user->getEmail())
                        ->subject('Réinitialisation de votre mot de passe — LibraryHub')
                        ->html(
                            '<p>Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>'
                            . '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>'
                            . '<p><a href="' . $resetUrl . '">Cliquez ici pour réinitialiser votre mot de passe</a></p>'
                            . '<p>Ce lien est valable <strong>1 heure</strong>.</p>'
                            . '<p>Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.</p>'
                        )
                );
            }

            $this->addFlash(
                'success',
                'Si un compte existe avec cet e-mail, un lien de réinitialisation vous a été envoyé.'
            );

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    // ──────────────────────────────────────────────────────────────
    // STEP 2 : Show & handle the new-password form
    // ──────────────────────────────────────────────────────────────

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Verify signature + expiry — no DB lookup needed
        $data = $this->verifySignedToken($token);

        if (!$data) {
            $this->addFlash('danger', 'Ce lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Find the user by the email embedded in the token
        $user = $userRepository->findOneBy(['email' => $data['email']]);
        if (!$user) {
            $this->addFlash('danger', 'Aucun compte associé à ce lien.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword     = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (strlen($newPassword) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'Les mots de passe ne correspondent pas.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            // Only the password column is updated — nothing else touched
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }
}