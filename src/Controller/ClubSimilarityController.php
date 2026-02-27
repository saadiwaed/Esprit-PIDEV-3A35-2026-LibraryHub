<?php

namespace App\Controller;

use App\Entity\Club;
use App\Service\ClubSimilarityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClubSimilarityController extends AbstractController
{
    #[Route('/club/{id}/similar', name: 'app_club_similar')]
    public function similar(
        int $id, 
        EntityManagerInterface $entityManager,
        ClubSimilarityService $similarityService
    ): Response {
        $club = $entityManager->getRepository(Club::class)->find($id);
        
        if (!$club) {
            throw $this->createNotFoundException('Club non trouvé');
        }

        // Obtenir les clubs similaires avec leurs scores
        $similarClubs = $similarityService->findSimilarClubs($id, 5);

        return $this->render('club/similar.html.twig', [
            'club' => $club,
            'similar_clubs' => $similarClubs
        ]);
    }
}