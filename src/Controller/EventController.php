<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventRegistration;
use App\Entity\Club;
use App\Form\EventType;
use App\Enum\EventStatus;
use App\Enum\RegistrationStatus;
use App\Repository\EventRepository;
use App\Repository\EventRegistrationRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event')]
final class EventController extends AbstractController
{
    private $mailerService;
    
    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }

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

        // ✅ AJOUTER ICI - Notification aux membres des clubs organisateurs
        foreach ($event->getOrganizingClubs() as $club) {
            $results = $this->mailerService->sendNewEventNotification($club, $event);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "📧 Annonce envoyée aux membres du club {$club->getTitle()}");
            }
        }

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

        // ✅ AJOUTER ICI - Notification aux membres des clubs organisateurs
        foreach ($event->getOrganizingClubs() as $club) {
            $results = $this->mailerService->sendNewEventNotification($club, $event);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "📧 Annonce envoyée aux membres du club {$club->getTitle()}");
            }
        }

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
            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
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

            // ✅ ENVOI EMAIL: Notification aux membres du club
            $results = $this->mailerService->sendNewEventNotification($club, $event);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "📧 Annonce envoyée à $sentCount membre(s) du club");
            }

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
        /** @var \App\Entity\User $user */
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
            
            // ✅ ENVOI EMAIL: Confirmation de liste d'attente
            $emailSent = $this->mailerService->sendEventWaitlistNotification($user, $event);
            
            if ($emailSent) {
                $this->addFlash('info', '📧 Confirmation de liste d\'attente envoyée');
            }
            
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
        
        // ✅ ENVOI EMAIL: Confirmation d'inscription
        $emailSent = $this->mailerService->sendEventRegistrationConfirmation($user, $event);
        
        if ($emailSent) {
            $this->addFlash('success', '📧 Email de confirmation envoyé à ' . $user->getEmail());
        }
        
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
        
        $wasConfirmed = $registration->getStatus() === RegistrationStatus::CONFIRMED;
        $registration->setStatus(RegistrationStatus::CANCELLED);
        $entityManager->flush();
        
        // ✅ ENVOI EMAIL: Confirmation de désinscription
        $emailSent = $this->mailerService->sendEventCancellationNotification($user, $event);
        
        if ($emailSent) {
            $this->addFlash('info', '📧 Confirmation de désinscription envoyée');
        }
        
        // ✅ SI C'ÉTAIT UNE INSCRIPTION CONFIRMÉE, NOTIFIER LE PREMIER SUR LISTE D'ATTENTE
        if ($wasConfirmed) {
            $waitlisted = $registrationRepo->findFirstWaitlisted($event);
            if ($waitlisted) {
                $waitlisted->setStatus(RegistrationStatus::CONFIRMED);
                $entityManager->flush();
                
                // ✅ ENVOI EMAIL: Notification de place libérée
                $notifSent = $this->mailerService->sendEventSpotFreedNotification($waitlisted->getUser(), $event);
                
                if ($notifSent) {
                    $this->addFlash('success', '📧 Un membre en liste d\'attente a été notifié');
                }
            }
        }
        
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

        $oldDate = clone $event->getStartDateTime();
        $oldLocation = $event->getLocation();
        
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            // ✅ ENVOI EMAIL: Notification de modification aux inscrits
            $changes = [];
            if ($oldDate != $event->getStartDateTime()) {
                $changes[] = "Date modifiée : " . $event->getStartDateTime()->format('d/m/Y H:i');
            }
            if ($oldLocation != $event->getLocation()) {
                $changes[] = "Lieu modifié : " . $event->getLocation();
            }
            
            if (!empty($changes)) {
                $results = $this->mailerService->sendEventUpdateNotification($event, $changes);
                $sentCount = count(array_filter($results));
                if ($sentCount > 0) {
                    $this->addFlash('info', "📧 Notification envoyée à $sentCount inscrit(s)");
                }
            }
            
            $this->addFlash('success', 'Événement "' . $event->getTitle() . '" modifié avec succès !');
            
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_event_index');
            } else {
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
        
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->render('event/show.html.twig', [
                'event' => $event,
                'isRegistered' => $isRegistered,
                'registrationStatus' => $registrationStatus,
            ]);
        } else {
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
            $eventTitle = $event->getTitle();
            
            // ✅ ENVOI EMAIL: Notification d'annulation aux inscrits
            $results = $this->mailerService->sendEventDeletionNotification($event);
            $sentCount = count(array_filter($results));
            
            if ($sentCount > 0) {
                $this->addFlash('info', "📧 Notification d'annulation envoyée à $sentCount inscrit(s)");
            }
            
            $entityManager->remove($event);
            $entityManager->flush();
            $this->addFlash('success', 'Événement "' . $eventTitle . '" supprimé avec succès !');
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

    #[Route('/test-email', name: 'app_event_test_email')]
    public function testEmail(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour tester les emails.');
            return $this->redirectToRoute('app_login');
        }
        
        $result = $this->mailerService->sendTestEmail($user->getEmail());
        
        if ($result) {
            $this->addFlash('success', '✅ Email de test envoyé à ' . $user->getEmail());
        } else {
            $this->addFlash('error', '❌ Échec de l\'envoi de l\'email de test. Vérifiez votre configuration.');
        }
        
        return $this->redirectToRoute('app_event_discover');
    }

    /**
     * Route pour tester les rappels d'événement (admin seulement)
     */
    #[Route('/test-reminder/{id}', name: 'app_event_test_reminder', methods: ['GET'])]
    public function testReminder(Event $event): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $sentCount = 0;
        foreach ($event->getRegistrations() as $registration) {
            if ($registration->getStatus() === RegistrationStatus::CONFIRMED) {
                $sent = $this->mailerService->sendEventReminderNotification($registration->getUser(), $event);
                if ($sent) $sentCount++;
            }
        }
        
        $this->addFlash('success', "📧 Rappel envoyé à $sentCount inscrit(s) pour l'événement " . $event->getTitle());
        
        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }
}