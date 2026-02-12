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
        // Récupérer les paramètres de filtres
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $category = $request->query->get('category', '');
        $sort = $request->query->get('sort', 'createdDate');
        $order = $request->query->get('order', 'desc');
        
        // Filtrer les clubs
        $clubs = $clubRepository->findByFilters($search, $status, $category, $sort, $order);
        
        // Compter par statut pour les badges
        $stats = $clubRepository->countByStatus();
        
        // Récupérer toutes les catégories uniques
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

    #[Route('/new', name: 'app_club_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($club);
            $entityManager->flush();

            return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('club/new.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_club_show', methods: ['GET'])]
    public function show(Club $club): Response
    {
        return $this->render('club/show.html.twig', [
            'club' => $club,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_club_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('club/edit.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_club_delete', methods: ['POST'])]
    public function delete(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$club->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($club);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_club_index', [], Response::HTTP_SEE_OTHER);
    }
    #[Route('/{id}/event/new', name: 'app_club_event_create', methods: ['GET', 'POST'])]
public function createClubEvent(Request $request, Club $club, EntityManagerInterface $entityManager): Response
{
    // STRICT CHECK: Only the founder can create events for this club
    if ($club->getFounder() !== $this->getUser()) {
        throw $this->createAccessDeniedException('Vous devez être le fondateur du club pour créer un événement.');
    }
    
    $event = new Event();
    $event->setCreatedBy($this->getUser());
    $event->addOrganizingClub($club); // Automatically link to this club
    
    // ✅ FIXED: Use EventType::class, NOT EventTypes::class
    $form = $this->createForm(EventType::class, $event);
    
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->persist($event);
        $entityManager->flush();
        
        $this->addFlash('success', 'Événement créé pour le club ' . $club->getTitle());
        
        return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
    }
    
    return $this->render('club/create_event.html.twig', [
        'club' => $club,
        'form' => $form->createView(),
    ]);
}
#[Route('/{id}/transfer-ownership', name: 'app_club_transfer_ownership', methods: ['POST'])]
public function transferOwnership(Request $request, Club $club, EntityManagerInterface $entityManager): Response
{
    // Seul le fondateur actuel peut transférer
    if ($club->getFounder() !== $this->getUser()) {
        throw $this->createAccessDeniedException('Seul le fondateur peut transférer le club.');
    }
    
    $newFounderId = $request->request->get('new_founder_id');
    $newFounder = $entityManager->getRepository(User::class)->find($newFounderId);
    
    // Vérifier que le nouvel utilisateur est membre du club
    if (!$club->isMember($newFounder)) {
        throw $this->createAccessDeniedException('Le nouveau fondateur doit être membre du club.');
    }
    
    // Transférer la propriété
    $club->setFounder($newFounder);
    
    $entityManager->flush();
    
    $this->addFlash('success', 'La propriété du club a été transférée à ' . $newFounder->getFirstName());
    
    return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
}
#[Route('/{id}/change-founder', name: 'app_club_change_founder', methods: ['POST'])]
public function changeFounder(Request $request, Club $club, EntityManagerInterface $entityManager): Response
{
    
    $this->denyAccessUnlessGranted('ROLE_ADMIN');
    
    $newFounderId = $request->request->get('new_founder_id');
    $newFounder = $entityManager->getRepository(User::class)->find($newFounderId);
    
    if (!$newFounder) {
        $this->addFlash('error', 'Utilisateur non trouvé');
        return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
    }
    
    if (!$club->isMember($newFounder)) {
        $this->addFlash('error', 'Le nouveau fondateur doit être membre du club');
        return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
    }
    
    $oldFounder = $club->getFounder();
    $club->setFounder($newFounder);
    $entityManager->flush();
    
    $this->addFlash('success', 'Admin: Fondateur changé de ' . ($oldFounder?->getEmail() ?? 'Personne') . ' → ' . $newFounder->getEmail());
    
    return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
}
}
