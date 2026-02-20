<?php

namespace App\Controller;

use App\Entity\JournalLecture;
use App\Entity\User; // ✅ IMPORT AJOUTÉ
use App\Form\JournalLectureType;
use App\Repository\JournalLectureRepository;
use App\Repository\DefiPersonelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry; // ✅ IMPORT AJOUTÉ
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\CitationService;

#[Route('/journal')]
final class JournalLectureController extends AbstractController
{
    private $doctrine; // ✅ PROPRIÉTÉ AJOUTÉE

    // ✅ CONSTRUCTEUR AJOUTÉ
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }
    
    // ===========================================
    // FRONT OFFICE - Liste des journaux
    // ===========================================
    #[Route('/', name: 'app_front_journal_index', methods: ['GET'])]
    public function frontIndex(
        Request $request,
        JournalLectureRepository $journalLectureRepository,
        CitationService $citationService
    ): Response {
        // ✅ CORRECTION : Utilisation de $this->doctrine au lieu de $this->getDoctrine()
        $user = $this->doctrine->getRepository(User::class)->find(1);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur ID=1 non trouvé. Créez-le d\'abord.');
        }
        
        // 🔍 RÉCUPÉRER LES PARAMÈTRES DE RECHERCHE ET TRI
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'date_desc');
        $filterLieu = $request->query->get('lieu', '');
        $filterNote = $request->query->get('note', '');
        
        // 📚 CONSTRUIRE LA REQUÊTE AVEC LES CRITÈRES
        $queryBuilder = $journalLectureRepository->createQueryBuilder('j')
            ->where('j.user = :user')
            ->setParameter('user', $user);
        
        // 🔍 RECHERCHE PAR TITRE OU RÉSUMÉ
        if (!empty($search)) {
            $queryBuilder->andWhere('j.titre LIKE :search OR j.resume LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        // 📍 FILTRE PAR LIEU
        if (!empty($filterLieu)) {
            $queryBuilder->andWhere('j.lieu = :lieu')
                ->setParameter('lieu', $filterLieu);
        }
        
        // ⭐ FILTRE PAR NOTE
        if (!empty($filterNote)) {
            $queryBuilder->andWhere('j.note_perso = :note')
                ->setParameter('note', $filterNote);
        }
        
        // 📊 TRI
        switch ($sortBy) {
            case 'date_asc':
                $queryBuilder->orderBy('j.date_lecture', 'ASC');
                break;
            case 'date_desc':
                $queryBuilder->orderBy('j.date_lecture', 'DESC');
                break;
            case 'duree_asc':
                $queryBuilder->orderBy('j.duree_minutes', 'ASC');
                break;
            case 'duree_desc':
                $queryBuilder->orderBy('j.duree_minutes', 'DESC');
                break;
            case 'note_asc':
                $queryBuilder->orderBy('j.note_perso', 'ASC');
                break;
            case 'note_desc':
                $queryBuilder->orderBy('j.note_perso', 'DESC');
                break;
            case 'pages_asc':
                $queryBuilder->orderBy('j.page_lues', 'ASC');
                break;
            case 'pages_desc':
                $queryBuilder->orderBy('j.page_lues', 'DESC');
                break;
            default:
                $queryBuilder->orderBy('j.date_lecture', 'DESC');
        }
        
        $journalLectures = $queryBuilder->getQuery()->getResult();
        
        // 📊 STATISTIQUES POUR TOUTES LES LECTURES
        $allLectures = $journalLectureRepository->findBy(['user' => $user]);
        $totalLectures = count($allLectures);
        $totalPages = array_sum(array_column($allLectures, 'page_lues'));
        $totalMinutes = array_sum(array_column($allLectures, 'duree_minutes'));
        
        // 📍 LISTE DES LIEUX UNIQUES POUR LE FILTRE
        $lieux = $journalLectureRepository->createQueryBuilder('j')
            ->select('DISTINCT j.lieu')
            ->where('j.user = :user')
            ->andWhere('j.lieu IS NOT NULL')
            ->andWhere('j.lieu != :empty')
            ->setParameter('user', $user)
            ->setParameter('empty', '')
            ->orderBy('j.lieu', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $citation = $citationService->getCitationLecture();

        return $this->render('frontoffice/journal/index.html.twig', [
            'journal_lectures' => $journalLectures,
            'total_lectures' => $totalLectures,
            'total_pages' => $totalPages,
            'total_minutes' => $totalMinutes,
            'search' => $search,
            'sortBy' => $sortBy,
            'filterLieu' => $filterLieu,
            'filterNote' => $filterNote,
            'lieux' => $lieux,
            'notes' => [1, 2, 3, 4, 5],
            'citation' => $citation,
        ]);
    }

    // ===========================================
    // FRONT OFFICE - Nouvelle entrée
    // ===========================================
    #[Route('/new', name: 'app_front_journal_new', methods: ['GET', 'POST'])]
    public function frontNew(
        Request $request,
        EntityManagerInterface $entityManager,
        DefiPersonelRepository $defiRepository
    ): Response {
        // ✅ CORRECTION
        $user = $this->doctrine->getRepository(User::class)->find(1);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur ID=1 non trouvé.');
        }

        $journalLecture = new JournalLecture();
        $form = $this->createForm(JournalLectureType::class, $journalLecture);
        
        // PRÉ-SÉLECTIONNER UN DÉFI DEPUIS L'URL
        $defiId = $request->query->get('defi');
        if ($defiId) {
            $defi = $defiRepository->find($defiId);
            // ✅ Vérification avec getUserId() pour compatibilité
            if ($defi && $defi->getUserId() == $user->getId()) {
                $journalLecture->setDefi($defi);
            }
        }
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $journalLecture->setUser($user);
            $journalLecture->setCreatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($journalLecture);
            $entityManager->flush();
            
            // MISE À JOUR DE LA PROGRESSION DU DÉFI ASSOCIÉ
            if ($journalLecture->getDefi()) {
                $defi = $journalLecture->getDefi();
                $lecturesAssociees = $defiRepository->find($defi->getId())->getJournaux();
                $nombreLectures = count($lecturesAssociees);
                
                if ($defi->getUnite() === 'Livres') {
                    $defi->setProgression($nombreLectures);
                } elseif ($defi->getUnite() === 'Pages') {
                    $totalPages = array_sum(array_column($lecturesAssociees, 'page_lues'));
                    $defi->setProgression($totalPages);
                } elseif ($defi->getUnite() === 'Heures') {
                    $totalMinutes = array_sum(array_column($lecturesAssociees, 'duree_minutes'));
                    $defi->setProgression($totalMinutes / 60);
                }
                
                if ($defi->getProgression() >= $defi->getObjectif()) {
                    $defi->setStatut('Terminé');
                    $this->addFlash('success', '🎉 FÉLICITATIONS ! Vous avez terminé le défi : ' . $defi->getTitre());
                }
                
                $entityManager->flush();
            }

            $this->addFlash('success', '✅ Lecture ajoutée avec succès !');
            return $this->redirectToRoute('app_front_journal_index');
        }

        // RÉCUPÉRER LES DÉFIS EN COURS
        $defisEnCours = $defiRepository->findBy(
            ['user_id' => $user->getId(), 'statut' => 'En cours'],
            ['date_fin' => 'ASC']
        );

        return $this->render('frontoffice/journal/new.html.twig', [
            'journal_lecture' => $journalLecture,
            'form' => $form,
            'defis' => $defisEnCours
        ]);
    }

    // ===========================================
    // FRONT OFFICE - Détail
    // ===========================================
    #[Route('/{id}', name: 'app_front_journal_show', methods: ['GET'])]
    public function frontShow(JournalLecture $journalLecture): Response
    {
        // ✅ CORRECTION
        $user = $this->doctrine->getRepository(User::class)->find(1);

        if ($journalLecture->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à cette entrée.');
        }

        return $this->render('frontoffice/journal/show.html.twig', [
            'journal_lecture' => $journalLecture,
        ]);
    }

    // ===========================================
    // FRONT OFFICE - Édition
    // ===========================================
    #[Route('/{id}/edit', name: 'app_front_journal_edit', methods: ['GET', 'POST'])]
    public function frontEdit(
        Request $request,
        JournalLecture $journalLecture,
        EntityManagerInterface $entityManager
    ): Response {
        // ✅ CORRECTION
        $user = $this->doctrine->getRepository(User::class)->find(1);

        if ($journalLecture->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à cette entrée.');
        }

        $originalUser = $journalLecture->getUser();
        $originalCreatedAt = $journalLecture->getCreatedAt();

        $form = $this->createForm(JournalLectureType::class, $journalLecture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $journalLecture->setUser($originalUser);
            $journalLecture->setCreatedAt($originalCreatedAt);
            
            $entityManager->flush();
            $this->addFlash('success', '✅ Lecture modifiée avec succès !');
            return $this->redirectToRoute('app_front_journal_show', ['id' => $journalLecture->getId()]);
        }

        return $this->render('frontoffice/journal/edit.html.twig', [
            'journal_lecture' => $journalLecture,
            'form' => $form,
        ]);
    }

    // ===========================================
    // FRONT OFFICE - Suppression
    // ===========================================
    #[Route('/{id}', name: 'app_front_journal_delete', methods: ['POST'])]
    public function frontDelete(
        Request $request,
        JournalLecture $journalLecture,
        EntityManagerInterface $entityManager
    ): Response {
        // ✅ CORRECTION
        $user = $this->doctrine->getRepository(User::class)->find(1);

        if ($journalLecture->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('❌ Vous n\'avez pas accès à cette entrée.');
        }

        if ($this->isCsrfTokenValid('delete'.$journalLecture->getId(), $request->request->get('_token'))) {
            $entityManager->remove($journalLecture);
            $entityManager->flush();
            $this->addFlash('success', '✅ Entrée de journal supprimée avec succès !');
        }

        return $this->redirectToRoute('app_front_journal_index');
    }
}