<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\VirtualLibrarianService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/assistant', name: 'app_virtual_assistant_')]
final class VirtualLibrarianController extends AbstractController
{
    #[Route('/ask', name: 'ask', methods: ['POST'])]
    public function ask(Request $request, VirtualLibrarianService $virtualLibrarianService): JsonResponse
    {
        $data = [];
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            // payload invalide, on laisse data = []
        }

        $question = is_string($data['question'] ?? null) ? trim($data['question']) : '';
        /** @var User|null $user */
        $user = $this->getUser();

        $result = $virtualLibrarianService->answer($question, $user instanceof User ? $user : null);

        return $this->json($result, $result['ok'] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_BAD_REQUEST);
    }
}

