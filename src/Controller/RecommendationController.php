<?php

namespace App\Controller;

use App\Service\AIRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RecommendationController extends AbstractController
{
    #[Route('/recommandations', name: 'app_recommendations')]
    #[IsGranted('ROLE_USER')]
    public function index(AIRecommendationService $aiService): Response
    {
        $user = $this->getUser();
        
        // Vérifier l'état du service IA
        $aiAvailable = $aiService->isAvailable();
        
        // Obtenir les recommandations
        $recommendedClubs = $aiService->getClubRecommendations($user, 6);
        
        return $this->render('recommendation/clubs.html.twig', [
            'clubs' => $recommendedClubs,
            'ai_available' => $aiAvailable,
            'user' => $user
        ]);
    }
}