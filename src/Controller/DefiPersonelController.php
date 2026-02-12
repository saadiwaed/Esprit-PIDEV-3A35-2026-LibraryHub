<?php
/*

namespace App\Controller;

use App\Entity\DefiPersonel;
use App\Form\DefiPersonelType;
use App\Repository\DefiPersonelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/defi/personel')]
final class DefiPersonelController extends AbstractController
{
    #[Route(name: 'app_defi_personel_index', methods: ['GET'])]
    public function index(DefiPersonelRepository $defiPersonelRepository): Response
    {
        return $this->render('defi_personel/index.html.twig', [
            'defi_personels' => $defiPersonelRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_defi_personel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $defiPersonel = new DefiPersonel();
        $form = $this->createForm(DefiPersonelType::class, $defiPersonel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($defiPersonel);
            $entityManager->flush();

            return $this->redirectToRoute('app_defi_personel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('defi_personel/new.html.twig', [
            'defi_personel' => $defiPersonel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_defi_personel_show', methods: ['GET'])]
    public function show(DefiPersonel $defiPersonel): Response
    {
        return $this->render('defi_personel/show.html.twig', [
            'defi_personel' => $defiPersonel,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_defi_personel_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DefiPersonel $defiPersonel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DefiPersonelType::class, $defiPersonel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_defi_personel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('defi_personel/edit.html.twig', [
            'defi_personel' => $defiPersonel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_defi_personel_delete', methods: ['POST'])]
    public function delete(Request $request, DefiPersonel $defiPersonel, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$defiPersonel->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($defiPersonel);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_defi_personel_index', [], Response::HTTP_SEE_OTHER);
    }
}
    */




namespace App\Controller;

use App\Entity\DefiPersonel;
use App\Form\DefiPersonelType;
use App\Repository\DefiPersonelRepository;
use App\Repository\JournalLectureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CitationService;




#[Route('/defis')]
final class DefiPersonelController extends AbstractController
{
    // ===========================================
    // FRONT OFFICE - Liste des défis
    // ===========================================
   /* #[Route('/', name: 'app_front_defi_index', methods: ['GET'])]
    public function frontIndex(DefiPersonelRepository $defiPersonelRepository, JournalLectureRepository $journalLectureRepository): Response
    {
        $userId = 1;
        $defis = $defiPersonelRepository->findBy(['user_id' => $userId], ['date_fin' => 'ASC']);
        
        $defisActifs = [];
        $defisTermines = [];
        
        foreach ($defis as $defi) {
            // Calcul automatique de la progression
            $journaux = $journalLectureRepository->findBy(['defi' => $defi]);
            
            if ($defi->getUnite() === 'Livres') {
                $progression = count($journaux);
            } elseif ($defi->getUnite() === 'Pages') {
                $progression = array_sum(array_column($journaux, 'pages_lues'));
            } elseif ($defi->getUnite() === 'Heures') {
                $progression = array_sum(array_column($journaux, 'duree_minutes')) / 60;
            } else {
                $progression = 0;
            }
            
            $defi->setProgression($progression);
            
            // Vérifier si le défi est terminé
            if ($progression >= $defi->getObjectif()) {
                $defi->setStatut('Terminé');
                $defisTermines[] = $defi;
            } else {
                $defisActifs[] = $defi;
            }
        }

        return $this->render('frontoffice/defi/index.html.twig', [
            'defis' => $defis,
            'defis_actifs' => $defisActifs,
            'defis_termines' => $defisTermines,
            'total_defis' => count($defis),
        ]);
    }

    // ===========================================
    // FRONT OFFICE - Nouveau défi
    // ===========================================
    */#[Route('/new', name: 'app_front_defi_new', methods: ['GET', 'POST'])]
    public function frontNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $defiPersonel = new DefiPersonel();
        $form = $this->createForm(DefiPersonelType::class, $defiPersonel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $defiPersonel->setUserId(1);
            $defiPersonel->setCreatedAt(new \DateTimeImmutable());
            $defiPersonel->setProgression(0);
            $defiPersonel->setStatut('En cours');
            
            $entityManager->persist($defiPersonel);
            $entityManager->flush();

            $this->addFlash('success', '✅ Défi créé avec succès !');
            return $this->redirectToRoute('app_front_defi_index');
        }

        return $this->render('frontoffice/defi/new.html.twig', [
            'defi' => $defiPersonel,
            'form' => $form,
        ]);
    }

    // ===========================================
    // FRONT OFFICE - Détail défi
    // ===========================================
    /*#[Route('/{id}', name: 'app_front_defi_show', methods: ['GET'])]
    public function frontShow(DefiPersonel $defiPersonel, JournalLectureRepository $journalLectureRepository): Response
    {
        if ($defiPersonel->getUserId() != 1) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à ce défi.');
        }

        $journaux = $journalLectureRepository->findBy(['defi' => $defiPersonel], ['date_lecture' => 'DESC']);
        
        // Calcul progression
        $progression = 0;
        if ($defiPersonel->getUnite() === 'Livres') {
            $progression = count($journaux);
        } elseif ($defiPersonel->getUnite() === 'Pages') {
            $progression = array_sum(array_column($journaux, 'pages_lues'));
        } elseif ($defiPersonel->getUnite() === 'Heures') {
            $progression = array_sum(array_column($journaux, 'duree_minutes')) / 60;
        }

        return $this->render('frontoffice/defi/show.html.twig', [
            'defi' => $defiPersonel,
            'journaux' => $journaux,
            'progression' => $progression,
            'pourcentage' => ($progression / $defiPersonel->getObjectif() * 100),
        ]);
    }*/

   // ===========================================
// FRONT OFFICE - Liste des défis avec RECHERCHE, TRI, FILTRES
// ===========================================
#[Route('/', name: 'app_front_defi_index', methods: ['GET'])]
public function frontIndex(
    Request $request,
    DefiPersonelRepository $defiPersonelRepository,
    JournalLectureRepository $journalLectureRepository,
        CitationService $citationService  // ✅ AJOUTER ICI

): Response {
    $userId = 1;
    
    // 🔍 RÉCUPÉRER LES PARAMÈTRES DE RECHERCHE ET TRI
    $search = $request->query->get('search', '');
    $sortBy = $request->query->get('sort', 'date_fin_asc');
    $filterType = $request->query->get('type', '');
    $filterStatus = $request->query->get('statut', '');
    $filterDifficulte = $request->query->get('difficulte', '');
    
    // ===========================================
    // 🚨 1. D'ABORD : METTRE À JOUR TOUS LES STATUTS
    // ===========================================
    $tousLesDefis = $defiPersonelRepository->findBy(['user_id' => $userId]);
    
    foreach ($tousLesDefis as $defi) {
        // 📊 CALCULER LA PROGRESSION RÉELLE
        $lecturesAssociees = $journalLectureRepository->findBy(['defi' => $defi]);
        
        $progression = 0;
        $unite = $defi->getUnite();
        
        if ($unite === 'Livres') {
            $progression = count($lecturesAssociees);
        } elseif ($unite === 'Pages') {
            $totalPages = 0;
            foreach ($lecturesAssociees as $lecture) {
                $totalPages += $lecture->getPageLues() ?? 0;
            }
            $progression = $totalPages;
        } elseif ($unite === 'Heures') {
            $totalMinutes = 0;
            foreach ($lecturesAssociees as $lecture) {
                $totalMinutes += $lecture->getDureeMinutes() ?? 0;
            }
            $progression = $totalMinutes / 60;
        }
        
        $defi->setProgression($progression);
        
        // 🏆 METTRE À JOUR LE STATUT
        if ($progression >= $defi->getObjectif()) {
            $defi->setStatut('Terminé');
        } elseif ($defi->getStatut() === 'Abandonné') {
            // Garder le statut 'Abandonné'
        } else {
            $defi->setStatut('En cours');
        }
    }
    
    // 💾 SAUVEGARDER LES STATUTS MIS À JOUR
    $defiPersonelRepository->getEntityManager()->flush();
    
    // ===========================================
    // 🚨 2. ENSUITE : APPLIQUER LES FILTRES
    // ===========================================
    $queryBuilder = $defiPersonelRepository->createQueryBuilder('d')
        ->where('d.user_id = :userId')
        ->setParameter('userId', $userId);
    
    // 🔍 RECHERCHE PAR TITRE OU DESCRIPTION
    if (!empty($search)) {
        $queryBuilder->andWhere('d.titre LIKE :search OR d.description LIKE :search')
            ->setParameter('search', '%' . $search . '%');
    }
    
    // 🎯 FILTRE PAR TYPE
    if (!empty($filterType)) {
        $queryBuilder->andWhere('d.type_defi = :type')
            ->setParameter('type', $filterType);
    }
    
    // 📊 FILTRE PAR STATUT - ✅ CORRIGÉ !
    if (!empty($filterStatus)) {
        $queryBuilder->andWhere('d.statut = :statut')
            ->setParameter('statut', $filterStatus);
    }
    
    // ⚡ FILTRE PAR DIFFICULTÉ
    if (!empty($filterDifficulte)) {
        $queryBuilder->andWhere('d.difficulte = :difficulte')
            ->setParameter('difficulte', $filterDifficulte);
    }
    
    // 📊 TRI
    switch ($sortBy) {
        case 'titre_asc':
            $queryBuilder->orderBy('d.titre', 'ASC');
            break;
        case 'titre_desc':
            $queryBuilder->orderBy('d.titre', 'DESC');
            break;
        case 'date_fin_asc':
            $queryBuilder->orderBy('d.date_fin', 'ASC');
            break;
        case 'date_fin_desc':
            $queryBuilder->orderBy('d.date_fin', 'DESC');
            break;
        case 'objectif_asc':
            $queryBuilder->orderBy('d.objectif', 'ASC');
            break;
        case 'objectif_desc':
            $queryBuilder->orderBy('d.objectif', 'DESC');
            break;
        case 'difficulte_asc':
            $queryBuilder->orderBy('d.difficulte', 'ASC');
            break;
        case 'difficulte_desc':
            $queryBuilder->orderBy('d.difficulte', 'DESC');
            break;
        case 'progression_asc':
            $queryBuilder->orderBy('d.progression', 'ASC');
            break;
        case 'progression_desc':
            $queryBuilder->orderBy('d.progression', 'DESC');
            break;
        default:
            $queryBuilder->orderBy('d.date_fin', 'ASC');
    }
    
    $defisFiltres = $queryBuilder->getQuery()->getResult();
    
    // ===========================================
    // 🚨 3. ENFIN : SÉPARER ACTIFS ET TERMINÉS
    // ===========================================
    $defisActifs = [];
    $defisTermines = [];
    
    foreach ($defisFiltres as $defi) {
        if ($defi->getStatut() === 'Terminé') {
            $defisTermines[] = $defi;
        } else {
            $defisActifs[] = $defi;
        }
    }
    
    // ===========================================
    // 📊 STATISTIQUES GLOBALES
    // ===========================================
    $totalDefis = count($tousLesDefis);
    $totalActifs = 0;
    $totalTermines = 0;
    
    foreach ($tousLesDefis as $defi) {
        if ($defi->getStatut() === 'Terminé') {
            $totalTermines++;
        } else {
            $totalActifs++;
        }
    }
    
    // 📊 STATISTIQUES SUPPLÉMENTAIRES
    $totalObjectifs = array_sum(array_column($tousLesDefis, 'objectif'));
    $totalProgression = array_sum(array_column($tousLesDefis, 'progression'));
    
    // 📊 LISTES POUR LES FILTRES
    $types = ['Quantitatif', 'Thématique', 'Découverte'];
    $statuts = ['En cours', 'Terminé', 'Abandonné'];
    $difficultes = [1, 2, 3, 4, 5];
    
    // 🐛 DEBUG - Vérifions les valeurs
    dump([
        'filterStatus' => $filterStatus,
        'defis_filtres_count' => count($defisFiltres),
        'defis_actifs_count' => count($defisActifs),
        'defis_termines_count' => count($defisTermines),
        'total_defis_bdd' => $totalDefis,
        'total_actifs_bdd' => $totalActifs,
        'total_termines_bdd' => $totalTermines,
    ]);

     // ✅ RÉCUPÉRER LES CITATIONS
    $citation = $citationService->getCitationAleatoire();
    $citationMotivation = $citationService->getCitationMotivation();

    return $this->render('frontoffice/defi/index.html.twig', [
        'defis' => $defisFiltres,
        'defis_actifs' => $defisActifs,
        'defis_termines' => $defisTermines,
        'total_defis' => $totalDefis,
        'total_actifs' => $totalActifs,
        'total_termines' => $totalTermines,
        'total_objectifs' => $totalObjectifs,
        'total_progression' => $totalProgression,
        'search' => $search,
        'sortBy' => $sortBy,
        'filterType' => $filterType,
        'filterStatus' => $filterStatus,
        'filterDifficulte' => $filterDifficulte,
        'types' => $types,
        'statuts' => $statuts,
        'difficultes' => $difficultes,
         'citation' => $citation,
        'citationMotivation' => $citationMotivation,
    ]);
}

        // ✅ 3. DÉTAIL D'UN DÉFI (AVEC PROGRESSION)
    // ===========================================
    #[Route('/{id}', name: 'app_front_defi_show', methods: ['GET'])]
    public function frontShow(DefiPersonel $defiPersonel, JournalLectureRepository $journalLectureRepository,CitationService $citationService): Response
    {
        // Vérification sécurité
        if ($defiPersonel->getUserId() != 1) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à ce défi.');
        }

        // 📚 RÉCUPÉRER TOUTES LES LECTURES ASSOCIÉES
        $journaux = $journalLectureRepository->findBy(
            ['defi' => $defiPersonel],
            ['date_lecture' => 'DESC']
        );
        
        // 🎯 CALCULER LA PROGRESSION
        $progression = 0;
        
        if ($defiPersonel->getUnite() === 'Livres') {
            // ✅ 1 lecture = 1 livre
            $progression = count($journaux);
        } elseif ($defiPersonel->getUnite() === 'Pages') {
            $progression = array_sum(array_column($journaux, 'page_lues'));
        } elseif ($defiPersonel->getUnite() === 'Heures') {
            $progression = array_sum(array_column($journaux, 'duree_minutes')) / 60;
        }
        
        // 📊 CALCULS POUR LES CONSEILS INTELLIGENTS
        $objectif = $defiPersonel->getObjectif();
        $reste = max(0, $objectif - $progression);
        $pourcentage = $objectif > 0 ? min(100, round(($progression / $objectif) * 100)) : 0;
        
        $dateFin = $defiPersonel->getDateFin();
        $aujourdhui = new \DateTime();
        $joursRestants = $aujourdhui->diff($dateFin)->days;
        $rythmeRecommande = $joursRestants > 0 ? round($reste / $joursRestants, 1) : 0;

        // ✅ CITATION PERSONNALISÉE
    if ($defiPersonel->getStatut() === 'Terminé') {
        $citation = $citationService->getCitationMotivation();
    } else {
        $citation = $citationService->getCitationLecture();
    }

        return $this->render('frontoffice/defi/show.html.twig', [
            'defi' => $defiPersonel,
            'journaux' => $journaux,
            'progression' => $progression,
            'reste' => $reste,
            'pourcentage' => $pourcentage,
            'jours_restants' => $joursRestants,
            'rythme_recommande' => $rythmeRecommande,
            'total_lectures' => count($journaux),
                    'citation' => $citation

        ]);
    }

    // ===========================================

    // ===========================================
    // FRONT OFFICE - Édition défi
    // ===========================================
    #[Route('/{id}/edit', name: 'app_front_defi_edit', methods: ['GET', 'POST'])]
    public function frontEdit(Request $request, DefiPersonel $defiPersonel, EntityManagerInterface $entityManager): Response
{
    if ($defiPersonel->getUserId() != 1) {
        throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à ce défi.');
    }

    // ✅ EMPÊCHER LA MODIFICATION DES CHAMPS SYSTÈME
    $originalUserId = $defiPersonel->getUserId();
    $originalCreatedAt = $defiPersonel->getCreatedAt();
    $originalProgression = $defiPersonel->getProgression();

    $form = $this->createForm(DefiPersonelType::class, $defiPersonel);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // ✅ RESTAURER LES VALEURS SYSTÈME (non modifiables)
        $defiPersonel->setUserId($originalUserId);
        $defiPersonel->setCreatedAt($originalCreatedAt);
        
        // ✅ NE PAS MODIFIER LA PROGRESSION MANUELLEMENT
        // Elle sera recalculée automatiquement plus tard
        
        $entityManager->flush();
        $this->addFlash('success', '✅ Défi modifié avec succès !');
        return $this->redirectToRoute('app_front_defi_show', ['id' => $defiPersonel->getId()]);
    }

    return $this->render('frontoffice/defi/edit.html.twig', [
        'defi' => $defiPersonel,
        'form' => $form,
    ]);
}
    // ===========================================
    // FRONT OFFICE - Suppression défi
    // ===========================================
    #[Route('/{id}', name: 'app_front_defi_delete', methods: ['POST'])]
    public function frontDelete(Request $request, DefiPersonel $defiPersonel, EntityManagerInterface $entityManager): Response
    {
        if ($defiPersonel->getUserId() != 1) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à ce défi.');
        }

        if ($this->isCsrfTokenValid('delete'.$defiPersonel->getId(), $request->request->get('_token'))) {
            $entityManager->remove($defiPersonel);
            $entityManager->flush();
            $this->addFlash('success', '✅ Défi supprimé avec succès !');
        }

        return $this->redirectToRoute('app_front_defi_index');
    }
}
