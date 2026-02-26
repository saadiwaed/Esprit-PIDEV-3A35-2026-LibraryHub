<?php

namespace App\Controller;

use App\Entity\DefiPersonel;
use App\Entity\User;
use App\Form\DefiPersonelType;
use App\Repository\DefiPersonelRepository;
use App\Repository\JournalLectureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CitationService;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/defis')]
final class DefiPersonelController extends AbstractController
{
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    // ===========================================
    // FRONT OFFICE - Liste des défis
    // ===========================================
    #[Route('/', name: 'app_front_defi_index', methods: ['GET'])]
    public function frontIndex(
        Request $request,
        DefiPersonelRepository $defiPersonelRepository,
        JournalLectureRepository $journalLectureRepository,
        CitationService $citationService,
        PaginatorInterface $paginator
    ): Response {
        // ✅ UTILISATEUR CONNECTÉ
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        $userId = $user->getId();
        
        // 🔍 PARAMÈTRES DE RECHERCHE
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'date_fin_asc');
        $filterType = $request->query->get('type', '');
        $filterStatus = $request->query->get('statut', '');
        $filterDifficulte = $request->query->get('difficulte', '');
        
        // 📊 METTRE À JOUR LES STATUTS
        $tousLesDefis = $defiPersonelRepository->findBy(['user_id' => $userId]);
        
        foreach ($tousLesDefis as $defi) {
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
            
            if ($progression >= $defi->getObjectif()) {
                $defi->setStatut('Terminé');
            } elseif ($defi->getStatut() !== 'Abandonné') {
                $defi->setStatut('En cours');
            }
        }
        
        $defiPersonelRepository->getEntityManager()->flush();
        
        // 📚 CONSTRUIRE LA REQUÊTE
        $queryBuilder = $defiPersonelRepository->createQueryBuilder('d')
            ->where('d.user_id = :userId')
            ->setParameter('userId', $userId);
        
        if (!empty($search)) {
            $queryBuilder->andWhere('d.titre LIKE :search OR d.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        if (!empty($filterType)) {
            $queryBuilder->andWhere('d.type_defi = :type')
                ->setParameter('type', $filterType);
        }
        
        if (!empty($filterStatus)) {
            $queryBuilder->andWhere('d.statut = :statut')
                ->setParameter('statut', $filterStatus);
        }
        
        if (!empty($filterDifficulte)) {
            $queryBuilder->andWhere('d.difficulte = :difficulte')
                ->setParameter('difficulte', $filterDifficulte);
        }
        
        switch ($sortBy) {
            case 'titre_asc': $queryBuilder->orderBy('d.titre', 'ASC'); break;
            case 'titre_desc': $queryBuilder->orderBy('d.titre', 'DESC'); break;
            case 'date_fin_asc': $queryBuilder->orderBy('d.date_fin', 'ASC'); break;
            case 'date_fin_desc': $queryBuilder->orderBy('d.date_fin', 'DESC'); break;
            case 'difficulte_desc': $queryBuilder->orderBy('d.difficulte', 'DESC'); break;
            case 'difficulte_asc': $queryBuilder->orderBy('d.difficulte', 'ASC'); break;
            case 'progression_desc': $queryBuilder->orderBy('d.progression', 'DESC'); break;
            case 'progression_asc': $queryBuilder->orderBy('d.progression', 'ASC'); break;
            default: $queryBuilder->orderBy('d.date_fin', 'ASC');
        }
        
        // ✅ PAGINATION
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            6
        );
        
        $defisFiltres = $pagination->getItems();
        
        $defisActifs = [];
        $defisTermines = [];
        
        foreach ($defisFiltres as $defi) {
            if ($defi->getStatut() === 'Terminé') {
                $defisTermines[] = $defi;
            } else {
                $defisActifs[] = $defi;
            }
        }
        
        // 📊 STATISTIQUES GLOBALES
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
        
        $totalObjectifs = 0;
        $totalProgression = 0;
        
        foreach ($tousLesDefis as $defi) {
            $totalObjectifs += $defi->getObjectif();
            $totalProgression += $defi->getProgression() ?? 0;
        }
        
        $types = ['Quantitatif', 'Thématique', 'Découverte'];
        $statuts = ['En cours', 'Terminé', 'Abandonné'];
        $difficultes = [1, 2, 3, 4, 5];
        
        $citation = $citationService->getCitationAleatoire();
        $citationMotivation = $citationService->getCitationMotivation();

        return $this->render('frontoffice/defi/index.html.twig', [
            'defis' => $pagination,
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

    // ===========================================
    // FRONT OFFICE - Nouveau défi
    // ===========================================
    #[Route('/new', name: 'app_front_defi_new', methods: ['GET', 'POST'])]
    public function frontNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $defiPersonel = new DefiPersonel();
        $form = $this->createForm(DefiPersonelType::class, $defiPersonel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $defiPersonel->setUserId($user->getId());
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
    #[Route('/{id}', name: 'app_front_defi_show', methods: ['GET'])]
    public function frontShow(
        DefiPersonel $defiPersonel,
        JournalLectureRepository $journalLectureRepository,
        CitationService $citationService
    ): Response {
        $user = $this->getUser();
        
        if (!$user || $defiPersonel->getUserId() != $user->getId()) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à ce défi.');
        }

        $journaux = $journalLectureRepository->findBy(
            ['defi' => $defiPersonel],
            ['date_lecture' => 'DESC']
        );
        
        $progression = 0;
        
        if ($defiPersonel->getUnite() === 'Livres') {
            $progression = count($journaux);
        } elseif ($defiPersonel->getUnite() === 'Pages') {
            $progression = array_sum(array_column($journaux, 'page_lues'));
        } elseif ($defiPersonel->getUnite() === 'Heures') {
            $progression = array_sum(array_column($journaux, 'duree_minutes')) / 60;
        }
        
        $objectif = $defiPersonel->getObjectif();
        $reste = max(0, $objectif - $progression);
        $pourcentage = $objectif > 0 ? min(100, round(($progression / $objectif) * 100)) : 0;
        
        $dateFin = $defiPersonel->getDateFin();
        $aujourdhui = new \DateTime();
        $joursRestants = $aujourdhui->diff($dateFin)->days;
        $rythmeRecommande = $joursRestants > 0 ? round($reste / $joursRestants, 1) : 0;

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
    // FRONT OFFICE - Édition défi
    // ===========================================
    #[Route('/{id}/edit', name: 'app_front_defi_edit', methods: ['GET', 'POST'])]
    public function frontEdit(
        Request $request,
        DefiPersonel $defiPersonel,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        if (!$user || $defiPersonel->getUserId() != $user->getId()) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à ce défi.');
        }

        $originalUserId = $defiPersonel->getUserId();
        $originalCreatedAt = $defiPersonel->getCreatedAt();

        $form = $this->createForm(DefiPersonelType::class, $defiPersonel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $defiPersonel->setUserId($originalUserId);
            $defiPersonel->setCreatedAt($originalCreatedAt);
            
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
    public function frontDelete(
        Request $request,
        DefiPersonel $defiPersonel,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        if (!$user || $defiPersonel->getUserId() != $user->getId()) {
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