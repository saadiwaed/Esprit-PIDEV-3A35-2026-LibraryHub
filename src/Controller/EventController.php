<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventRegistration;
use App\Entity\Club;  // ✅ AJOUTEZ CETTE LIGNE !
use App\Form\EventType;
use App\Enum\EventStatus;
use App\Enum\RegistrationStatus;
use App\Repository\EventRepository;
use App\Repository\EventRegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event')]
final class EventController extends AbstractController
{

    #[Route('/discover', name: 'app_event_discover', methods: ['GET'])]
    public function discover(Request $request, EventRepository $eventRepository, EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'startDateTime');
        $order = $request->query->get('order', 'asc');
        
        $events = $eventRepository->findDiscoverEvents($user, $search, $status, $sort, $order);
        
        $userRegistrations = $registrationRepo->findUserRegistrations($user);
        $registeredEventIds = array_map(fn($reg) => $reg->getEvent()->getId(), $userRegistrations);
        
        return $this->render('event/discover.html.twig', [
            'events' => $events,
            'registeredEventIds' => $registeredEventIds,
            'current_filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'order' => $order,
            ],
            'all_status' => EventStatus::cases(),
        ]);
    }

    #[Route('/my-history', name: 'app_event_my_history', methods: ['GET'])]
    public function myHistory(EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        $history = $registrationRepo->findUserHistory($user);
        $stats = $registrationRepo->getUserStats($user);
        $cancellations = $registrationRepo->findUserCancellations($user);
        
        return $this->render('event/my_history.html.twig', [
            'history' => $history,
            'stats' => $stats,
            'cancellations' => $cancellations,
        ]);
    }

    #[Route('/my-events', name: 'app_event_my_events', methods: ['GET'])]
    public function myEvents(Request $request, EventRepository $eventRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'startDateTime');
        $order = $request->query->get('order', 'asc');
        
        $events = $eventRepository->findByUser($user, $search, $status, $sort, $order);
        $stats = $eventRepository->countByStatusForUser($user);
        
        return $this->render('event/my_events.html.twig', [
            'events' => $events,
            'stats' => $stats,
            'current_filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'order' => $order,
            ],
            'all_status' => EventStatus::cases(),
        ]);
    }

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $event = new Event();
        $event->setCreatedBy($this->getUser());
        
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement "' . $event->getTitle() . '" créé avec succès !');
            return $this->redirectToRoute('app_event_index');
        }

        return $this->render('event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }
 #[Route('/addformember', name: 'app_event_addformember', methods: ['GET', 'POST'])]
public function addForMember(Request $request, EntityManagerInterface $entityManager): Response
{
    $event = new Event();
    $event->setCreatedBy($this->getUser());

    $form = $this->createForm(EventType::class, $event);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->persist($event);
        $entityManager->flush();

        $this->addFlash('success', 'Événement "' . $event->getTitle() . '" créé avec succès !');
        return $this->redirectToRoute('app_event_my_events');
    }

    return $this->render('event/addformember.html.twig', [
        'event' => $event,
        'form' => $form,
        'button_label' => 'Créer l\'événement'
    ]);
}
#[Route('/addformember/club/{clubId}', name: 'app_event_addformember_club', methods: ['GET', 'POST'])]
public function addForMemberClub(Request $request, EntityManagerInterface $entityManager, int $clubId): Response
{
    $club = $entityManager->getRepository(Club::class)->find($clubId);
    if (!$club) {
        throw $this->createNotFoundException('Club non trouvé');
    }

    if ($club->getFounder() !== $this->getUser()) {
        $this->addFlash('error', 'Seul le fondateur peut créer des événements pour ce club.');
        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]); // ✅ REDIRECTION VERS showformember
    }

    $event = new Event();
    $event->setCreatedBy($this->getUser());
    $event->addOrganizingClub($club);
    $event->setStatus(EventStatus::UPCOMING);
    
    $form = $this->createForm(EventType::class, $event);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->persist($event);
        $entityManager->flush();

        $this->addFlash('success', 'Événement "' . $event->getTitle() . '" créé pour le club ' . $club->getTitle());
        
        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    return $this->render('event/addEventforclub.html.twig', [
        'event' => $event,
        'club' => $club,
        'form' => $form,
        'button_label' => 'Créer l\'événement pour le club'
    ]);
}

    #[Route('/{id}/join', name: 'app_event_join', methods: ['POST'])]
    public function join(Event $event, EntityManagerInterface $entityManager, EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        if ($event->getCreatedBy() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas vous inscrire à votre propre événement.');
            return $this->redirectToRoute('app_event_discover');
        }
        
        $existingReg = $registrationRepo->findUserRegistrationForEvent($event, $user);
        if ($existingReg) {
            if ($existingReg->getStatus() === RegistrationStatus::CONFIRMED) {
                $this->addFlash('warning', 'Vous êtes déjà inscrit à cet événement.');
            } elseif ($existingReg->getStatus() === RegistrationStatus::CANCELLED) {
                $existingReg->setStatus(RegistrationStatus::CONFIRMED);
                $entityManager->flush();
                $this->addFlash('success', 'Votre inscription a été réactivée !');
            }
            return $this->redirectToRoute('app_event_discover');
        }
        
        $confirmedCount = $registrationRepo->countConfirmedRegistrations($event);
        if ($confirmedCount >= $event->getCapacity()) {
            $registration = new EventRegistration();
            $registration->setUser($user);
            $registration->setEvent($event);
            $registration->setStatus(RegistrationStatus::WAITLISTED);
            $registration->setRegisteredAt(new \DateTime());
            
            $entityManager->persist($registration);
            $entityManager->flush();
            
            $this->addFlash('warning', 'L\'événement est complet. Vous êtes en liste d\'attente.');
            return $this->redirectToRoute('app_event_discover');
        }
        
        $registration = new EventRegistration();
        $registration->setUser($user);
        $registration->setEvent($event);
        $registration->setStatus(RegistrationStatus::CONFIRMED);
        $registration->setRegisteredAt(new \DateTime());
        
        $entityManager->persist($registration);
        $entityManager->flush();
        
        $this->addFlash('success', 'Vous êtes inscrit à l\'événement : ' . $event->getTitle());
        return $this->redirectToRoute('app_event_discover');
    }

    #[Route('/{id}/leave', name: 'app_event_leave', methods: ['POST'])]
    public function leave(Event $event, EntityManagerInterface $entityManager, EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        $registration = $registrationRepo->findUserRegistrationForEvent($event, $user);
        
        if (!$registration) {
            $this->addFlash('error', 'Vous n\'êtes pas inscrit à cet événement.');
            return $this->redirectToRoute('app_event_discover');
        }
        
        $registration->setStatus(RegistrationStatus::CANCELLED);
        $entityManager->flush();
        
        $this->addFlash('success', 'Vous vous êtes désinscrit de l\'événement : ' . $event->getTitle());
        return $this->redirectToRoute('app_event_discover');
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ?Event $event, EntityManagerInterface $entityManager): Response
    {
        if (!$event) {
            throw $this->createNotFoundException('Cet événement n\'existe pas.');
        }

        if ($event->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le créateur de cet événement.');
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();
        $this->addFlash('success', 'Événement "' . $event->getTitle() . '" modifié avec succès !');
        
        // ✅ REDIRECTION CONDITIONNELLE
        if ($this->isGranted('ROLE_ADMIN')) {
            // Admin → retourne à la liste admin (backoffice)
            return $this->redirectToRoute('app_event_index');
        } else {
            // Membre → retourne à la liste de ses événements (frontoffice)
            return $this->redirectToRoute('app_event_my_events');
        }
    }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'])]
public function show(?Event $event, EventRegistrationRepository $registrationRepo): Response
{
    if (!$event) {
        throw $this->createNotFoundException('Cet événement n\'existe pas.');
    }

    $isRegistered = false;
    $registrationStatus = null;
    
    if ($this->getUser()) {
        $registration = $registrationRepo->findUserRegistrationForEvent($event, $this->getUser());
        if ($registration) {
            $isRegistered = true;
            $registrationStatus = $registration->getStatus();
        }
    }
    
    // ✅ VÉRIFICATION DU RÔLE
    if ($this->isGranted('ROLE_ADMIN')) {
        // Admin → template show.html.twig
        return $this->render('event/show.html.twig', [
            'event' => $event,
            'isRegistered' => $isRegistered,
            'registrationStatus' => $registrationStatus,
        ]);
    } else {
        // Membre → template showformember.html.twig
        return $this->render('event/showformember.html.twig', [
            'event' => $event,
            'isRegistered' => $isRegistered,
            'registrationStatus' => $registrationStatus,
        ]);
    }
}
    

    #[Route('/{id}', name: 'app_event_delete', methods: ['POST'])]
    public function delete(Request $request, ?Event $event, EntityManagerInterface $entityManager): Response
    {
        if (!$event) {
            throw $this->createNotFoundException('Cet événement n\'existe pas.');
        }

        if ($event->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas le créateur de cet événement.');
        }

        if ($this->isCsrfTokenValid('delete' . $event->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($event);
            $entityManager->flush();
            $this->addFlash('success', 'Événement "' . $event->getTitle() . '" supprimé avec succès !');
        }

        return $this->redirectToRoute('app_event_index');
    }


    #[Route('/', name: 'app_event_index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'startDateTime');
        $order = $request->query->get('order', 'asc');
        
        $events = $eventRepository->findByFilters($search, $status, $sort, $order);
        $stats = $eventRepository->countByStatus();
        
        return $this->render('event/index.html.twig', [
            'events' => $events,
            'stats' => $stats,
            'current_filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'order' => $order,
            ],
            'all_status' => EventStatus::cases(),
        ]);
    }
   
}