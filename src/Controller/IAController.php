<?php

namespace App\Controller;

use App\Service\PollinationsService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ia')]
class IAController extends AbstractController
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    #[Route('/analyse', name: 'app_ia_analyse')]
    public function analyserHabitudes(PollinationsService $iaService): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $analyse = $iaService->analyserHabitudesLecture($user);
        
        return $this->render('frontoffice/ia/analyse.html.twig', [
            'analyse' => $analyse
        ]);
    }
    
    #[Route('/conseil-defi/{id}', name: 'app_ia_conseil_defi')]
    public function conseilDefi($id, PollinationsService $iaService): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $defi = $this->doctrine->getRepository(\App\Entity\DefiPersonel::class)->find($id);
        
        if (!$defi || $defi->getUserId() != $user->getId()) {
            throw $this->createAccessDeniedException('Défi non trouvé');
        }
        
        $conseils = $iaService->genererConseilsDefi($user, $defi);
        
        return $this->render('frontoffice/ia/conseil_defi.html.twig', [
            'defi' => $defi,
            'conseils' => $conseils
        ]);
    }
}