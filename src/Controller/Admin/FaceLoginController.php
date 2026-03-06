<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\FaceRecognitionService;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin/face-login', name: 'admin_face_login_')]
final class FaceLoginController extends AbstractController
{
    public function __construct(
        private FaceRecognitionService $faceRecognition,
    ) {
    }

    #[Route('', name: 'page', methods: ['GET'])]
    public function page(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_frontoffice');
        }

        return $this->render('admin/face/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Étape 1 : reconnaissance faciale, renvoie un token de session + email si un admin est reconnu.
     *
     * @throws RandomException
     */
    #[Route('/recognize', name: 'recognize', methods: ['POST'])]
    public function recognize(Request $request, SessionInterface $session): JsonResponse
    {
        $descriptor = $request->request->get('descriptor');

        if ($descriptor === null || $descriptor === '') {
            return new JsonResponse(['isSuccessful' => false, 'message' => 'Descripteur manquant.'], 400);
        }

        $decoded = is_string($descriptor) ? json_decode($descriptor, true) : $descriptor;
        if (!is_array($decoded) || count($decoded) === 0) {
            return new JsonResponse(['isSuccessful' => false, 'message' => 'Descripteur invalide.'], 400);
        }

        $probe = array_map('floatval', $decoded);

        $user = $this->faceRecognition->findMatchingAdmin($probe);

        if (!$user instanceof User) {
            return new JsonResponse([
                'isSuccessful' => false,
                'message' => 'Aucun administrateur correspondant trouvé.',
            ], 404);
        }

        $token = bin2hex(random_bytes(32));
        $session->set('face_login_token', $token);
        $session->set('face_login_user_id', $user->getId());

        return new JsonResponse([
            'isSuccessful' => true,
            'message' => 'Visage reconnu. Veuillez saisir votre mot de passe pour vous connecter.',
            'token' => $token,
            'email' => $user->getEmail(),
        ]);
    }
}

