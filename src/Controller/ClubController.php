<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Form\ClubType;
use App\Enum\ClubStatus;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Event;
use App\Form\EventType;

#[Route('/club')]
final class ClubController extends AbstractController
{

    
    #[Route('/', name: 'app_club_index', methods: ['GET'])]
    public function index(Request $request, ClubRepository $clubRepository): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $category = $request->query->get('category', '');
        $sort = $request->query->get('sort', 'createdDate');
        $order = $request->query->get('order', 'desc');
        
        $clubs = $clubRepository->findByFilters($search, $status, $category, $sort, $order);
        
        $stats = $clubRepository->countByStatus();
        
        $categories = $clubRepository->findAllCategories();
        
        return $this->render('club/index.html.twig', [
            'clubs' => $clubs,
            'stats' => $stats,
            'categories' => $categories,
            'current_filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
                'sort' => $sort,
                'order' => $order,
            ],
            'all_status' => ClubStatus::cases(),
        ]);
    }

    #[Route('/addformember', name: 'app_club_addformember', methods: ['GET', 'POST'])]
    public function addForMember(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour créer un club.');
            return $this->redirectToRoute('app_login');
        }

        $club = new Club();
        
        $club->setFounder($user);
        $club->setCreatedBy($user);
        $club->addMember($user);
        $club->setStatus(ClubStatus::ACTIVE);
        
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($club);
            $entityManager->flush();

            $this->addFlash('success', 'Félicitations ! Votre club "' . $club->getTitle() . '" a été créé avec succès.');
            
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        return $this->render('club/addformember.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_club_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour créer un club.');
            return $this->redirectToRoute('app_login');
        }

        $club = new Club();
        
        $club->setFounder($user);
        $club->setCreatedBy($user);
        $club->addMember($user);
        $club->setStatus(ClubStatus::ACTIVE);
        
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($club);
            $entityManager->flush();

            $this->addFlash('success', 'Félicitations ! Votre club "' . $club->getTitle() . '" a été créé avec succès.');
            
            return $this->redirectToRoute('app_club_index', ['id' => $club->getId()]);
        }

        return $this->render('club/new.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/mes-clubs', name: 'app_my_clubs', methods: ['GET'])]
    public function myClubs(): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $clubs = $user->getClubs()->filter(function($club) {
            return $club->getStatus() === \App\Enum\ClubStatus::ACTIVE;
        });

        return $this->render('club/my_clubs.html.twig', [
            'clubs' => $clubs,
        ]);
    }


    #[Route('/member-view/{id}', name: 'app_showformember', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showForMember(Club $club): Response
    {
        $user = $this->getUser();
        if (!$user || !$club->isMember($user)) {
            $this->addFlash('error', 'Vous devez être membre pour voir cette page.');
            return $this->redirectToRoute('app_club_discover');
        }

        $isMember = true;
        
        return $this->render('club/showformember.html.twig', [
            'club' => $club,
            'isMember' => $isMember,
        ]);
    }


    #[Route('/{id}/edit', name: 'app_club_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        if ($club->getFounder() !== $this->getUser()) {
            $this->addFlash('error', 'Seul le fondateur peut modifier le club.');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le club a été modifié avec succès.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        return $this->render('club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/event/new', name: 'app_club_event_create', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function createClubEvent(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        if ($club->getFounder() !== $this->getUser()) {
            $this->addFlash('error', 'Seul le fondateur du club peut créer des événements officiels pour ce club.');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        $event = new Event();
        $event->setCreatedBy($this->getUser());
        $event->addOrganizingClub($club);
        
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();
            
            $this->addFlash('success', 'Événement créé pour le club ' . $club->getTitle());
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        return $this->render('event/new.html.twig', [
            'club' => $club,
            'form' => $form->createView(),
            'button_label' => 'Créer l\'événement pour le club',
            'hide_club_field' => true
        ]);
    }

    #[Route('/{id}/transfer-ownership', name: 'app_club_transfer_ownership', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transferOwnership(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        if ($club->getFounder() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Seul le fondateur peut transférer le club.');
        }
        
        $newFounderId = $request->request->get('new_founder_id');
        $newFounder = $entityManager->getRepository(User::class)->find($newFounderId);
        
        if (!$club->isMember($newFounder)) {
            throw $this->createAccessDeniedException('Le nouveau fondateur doit être membre du club.');
        }
        
        $club->setFounder($newFounder);
        
        $entityManager->flush();
        
        $this->addFlash('success', 'La propriété du club a été transférée à ' . $newFounder->getFirstName());
        
        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    #[Route('/{id}/change-founder', name: 'app_club_change_founder', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changeFounder(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $newFounderId = $request->request->get('new_founder_id');
        $newFounder = $entityManager->getRepository(User::class)->find($newFounderId);
        
        if (!$newFounder) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        if (!$club->isMember($newFounder)) {
            $this->addFlash('error', 'Le nouveau fondateur doit être membre du club');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        $oldFounder = $club->getFounder();
        $club->setFounder($newFounder);
        $entityManager->flush();
        
        $this->addFlash('success', 'Admin: Fondateur changé de ' . ($oldFounder?->getEmail() ?? 'Personne') . ' → ' . $newFounder->getEmail());
        
        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    #[Route('/{id}/join', name: 'app_club_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        if (!$club->canJoin()) {
            $this->addFlash('error', 'Ce club n\'accepte pas de nouveaux membres pour le moment.');
            return $this->redirectToRoute('app_club_discover');
        }
        
        if ($club->isMember($user)) {
            $this->addFlash('warning', 'Vous êtes déjà membre de ce club.');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        if (!$this->isCsrfTokenValid('join' . $club->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_club_discover');
        }
        
        $club->addMember($user);
        $entityManager->flush();
        
        $this->addFlash('success', 'Vous avez rejoint le club "' . $club->getTitle() . '" !');
        
        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    #[Route('/{id}/leave', name: 'app_club_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function leave(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        if ($club->getFounder() === $user) {
            $this->addFlash('error', 'Vous êtes le fondateur du club. Vous devez transférer la propriété avant de quitter.');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        if (!$club->isMember($user)) {
            $this->addFlash('warning', 'Vous n\'êtes pas membre de ce club.');
            return $this->redirectToRoute('app_club_discover');
        }
        
        if (!$this->isCsrfTokenValid('leave' . $club->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }
        
        $club->removeMember($user);
        $entityManager->flush();
        
        $this->addFlash('success', 'Vous avez quitté le club "' . $club->getTitle() . '".');
        
        return $this->redirectToRoute('app_club_discover');
    }

    #[Route('/{id}', name: 'app_club_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Club $club): Response
    {

        $isMember = false;
        $user = $this->getUser();
        if ($user) {
            $isMember = $club->isMember($user);
        }

        return $this->render('club/show.html.twig', [
            'club' => $club,
            'isMember' => $isMember,
        ]);
    }

    #[Route('/{id}', name: 'app_club_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
  
        if ($club->getFounder() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n\'avez pas les droits pour supprimer ce club.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        if ($this->isCsrfTokenValid('delete'.$club->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($club);
            $entityManager->flush();
            $this->addFlash('success', 'Le club a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/decouvrir', name: 'app_club_discover', methods: ['GET'])]
    public function discover(Request $request, ClubRepository $clubRepository): Response
    {
        
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');
        $status = $request->query->get('status', 'active');
        $sort = $request->query->get('sort', 'createdDate');
        $order = $request->query->get('order', 'desc');
        
       
        $clubs = $clubRepository->findByFilters($search, $status, $category, $sort, $order);
        
       
        $categories = $clubRepository->findAllCategories();
        
        
        $userClubs = [];
        $user = $this->getUser();
        if ($user) {
            $userClubs = $user->getClubs()->map(function($club) {
                return $club->getId();
            })->toArray();
        }
        
        return $this->render('club/discover.html.twig', [
            'clubs' => $clubs,
            'categories' => $categories,
            'userClubs' => $userClubs,
            'current_filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'sort' => $sort,
                'order' => $order,
            ],
            'all_status' => ClubStatus::cases(),
        ]);
    }
}