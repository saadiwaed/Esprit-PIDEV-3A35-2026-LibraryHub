<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/face', name: 'admin_face_')]
final class FaceRegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['GET'])]
    public function registerPage(): Response
    {
        return $this->render('admin/face/register.html.twig');
    }

    #[Route('/descriptor', name: 'save_descriptor', methods: ['POST'])]
    public function saveDescriptor(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User || !$user->hasRole('ROLE_ADMIN')) {
            return new JsonResponse(['isSuccessful' => false, 'message' => 'Accès refusé.'], 403);
        }

        $descriptor = $request->request->get('descriptor');

        if ($descriptor === null || $descriptor === '') {
            return new JsonResponse(['isSuccessful' => false, 'message' => 'Descripteur manquant.'], 400);
        }

        // Si le front envoie un tableau JSON, on le convertit en chaîne
        if (is_string($descriptor) && str_starts_with(trim($descriptor), '[')) {
            $decoded = json_decode($descriptor, true);
            if (!is_array($decoded)) {
                return new JsonResponse(['isSuccessful' => false, 'message' => 'Format de descripteur invalide.'], 400);
            }
            $descriptor = implode(',', array_map('strval', $decoded));
        }

        $user->setFaceDescriptor($descriptor);
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'isSuccessful' => true,
            'message' => 'Descripteur facial enregistré pour votre compte administrateur.',
        ]);
    }
}

