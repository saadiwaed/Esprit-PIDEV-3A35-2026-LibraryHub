<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\EventRegistration;
use App\Entity\User;
use App\Enum\EventStatus;
use App\Enum\RegistrationStatus;
use App\Form\EventType;
use App\Repository\EventRegistrationRepository;
use App\Repository\EventRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/event')]
final class EventController extends AbstractController
{
    public function __construct(private readonly MailerService $mailerService)
    {
    }

    #[Route('/discover', name: 'app_event_discover', methods: ['GET'])]
    public function discover(Request $request, EventRepository $eventRepository, EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'startDateTime');
        $order = $request->query->get('order', 'asc');

        $events = $eventRepository->findDiscoverEvents($user, $search, $status, $sort, $order);
        $userRegistrations = $registrationRepo->findUserRegistrations($user);
        $registeredEventIds = array_values(array_filter(array_map(
            static fn (EventRegistration $registration): ?int => $registration->getEvent()?->getId(),
            $userRegistrations
        )));

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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('event/my_history.html.twig', [
            'history' => $registrationRepo->findUserHistory($user),
            'stats' => $registrationRepo->getUserStats($user),
            'cancellations' => $registrationRepo->findUserCancellations($user),
        ]);
    }

    #[Route('/my-events', name: 'app_event_my_events', methods: ['GET'])]
    public function myEvents(Request $request, EventRepository $eventRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $sort = $request->query->get('sort', 'startDateTime');
        $order = $request->query->get('order', 'asc');

        return $this->render('event/my_events.html.twig', [
            'events' => $eventRepository->findByUser($user, $search, $status, $sort, $order),
            'stats' => $eventRepository->countByStatusForUser($user),
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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $event = new Event();
        $event->setCreatedBy($user);

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            foreach ($event->getOrganizingClubs() as $club) {
                $results = $this->mailerService->sendNewEventNotification($club, $event);
                $sentCount = count(array_filter($results));
                if ($sentCount > 0) {
                    $this->addFlash('info', "Annonce envoyee aux membres du club {$club->getTitle()}");
                }
            }

            $this->addFlash('success', 'Evenement "'.$event->getTitle().'" cree avec succes !');

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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $event = new Event();
        $event->setCreatedBy($user);

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            foreach ($event->getOrganizingClubs() as $club) {
                $results = $this->mailerService->sendNewEventNotification($club, $event);
                $sentCount = count(array_filter($results));
                if ($sentCount > 0) {
                    $this->addFlash('info', "Annonce envoyee aux membres du club {$club->getTitle()}");
                }
            }

            $this->addFlash('success', 'Evenement "'.$event->getTitle().'" cree avec succes !');

            return $this->redirectToRoute('app_event_my_events');
        }

        return $this->render('event/addformember.html.twig', [
            'event' => $event,
            'form' => $form,
            'button_label' => "Creer l'evenement",
        ]);
    }

    #[Route('/addformember/club/{clubId}', name: 'app_event_addformember_club', methods: ['GET', 'POST'])]
    public function addForMemberClub(Request $request, EntityManagerInterface $entityManager, int $clubId): Response
    {
        $club = $entityManager->getRepository(Club::class)->find($clubId);
        if (!$club instanceof Club) {
            throw $this->createNotFoundException('Club non trouve');
        }

        $user = $this->getCurrentUser();
        if (!$user instanceof User || $club->getFounder() !== $user) {
            $this->addFlash('error', 'Seul le fondateur peut creer des evenements pour ce club.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        $event = new Event();
        $event->setCreatedBy($user);
        $event->addOrganizingClub($club);
        $event->setStatus(EventStatus::UPCOMING);

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            $results = $this->mailerService->sendNewEventNotification($club, $event);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "Annonce envoyee a $sentCount membre(s) du club");
            }

            $this->addFlash('success', 'Evenement "'.$event->getTitle().'" cree pour le club '.$club->getTitle());

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        return $this->render('event/addEventforclub.html.twig', [
            'event' => $event,
            'club' => $club,
            'form' => $form,
            'button_label' => "Creer l'evenement pour le club",
        ]);
    }

    #[Route('/{id}/join', name: 'app_event_join', methods: ['POST'])]
    public function join(Event $event, EntityManagerInterface $entityManager, EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($event->getCreatedBy() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas vous inscrire a votre propre evenement.');

            return $this->redirectToRoute('app_event_discover');
        }

        $existingReg = $registrationRepo->findUserRegistrationForEvent($event, $user);
        if ($existingReg instanceof EventRegistration) {
            if ($existingReg->getStatus() === RegistrationStatus::CONFIRMED) {
                $this->addFlash('warning', 'Vous etes deja inscrit a cet evenement.');
            } elseif ($existingReg->getStatus() === RegistrationStatus::CANCELLED) {
                $existingReg->setStatus(RegistrationStatus::CONFIRMED);
                $entityManager->flush();
                $this->addFlash('success', 'Votre inscription a ete reactivee !');
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

            if ($this->mailerService->sendEventWaitlistNotification($user, $event)) {
                $this->addFlash('info', "Confirmation de liste d'attente envoyee");
            }

            $this->addFlash('warning', "L'evenement est complet. Vous etes en liste d'attente.");

            return $this->redirectToRoute('app_event_discover');
        }

        $registration = new EventRegistration();
        $registration->setUser($user);
        $registration->setEvent($event);
        $registration->setStatus(RegistrationStatus::CONFIRMED);
        $registration->setRegisteredAt(new \DateTime());

        $entityManager->persist($registration);
        $entityManager->flush();

        if ($this->mailerService->sendEventRegistrationConfirmation($user, $event)) {
            $this->addFlash('success', 'Email de confirmation envoye a '.$user->getEmail());
        }

        $this->addFlash('success', "Vous etes inscrit a l'evenement : ".$event->getTitle());

        return $this->redirectToRoute('app_event_discover');
    }

    #[Route('/{id}/leave', name: 'app_event_leave', methods: ['POST'])]
    public function leave(Event $event, EntityManagerInterface $entityManager, EventRegistrationRepository $registrationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $registration = $registrationRepo->findUserRegistrationForEvent($event, $user);
        if (!$registration instanceof EventRegistration) {
            $this->addFlash('error', "Vous n'etes pas inscrit a cet evenement.");

            return $this->redirectToRoute('app_event_discover');
        }

        $wasConfirmed = $registration->getStatus() === RegistrationStatus::CONFIRMED;
        $registration->setStatus(RegistrationStatus::CANCELLED);
        $entityManager->flush();

        if ($this->mailerService->sendEventCancellationNotification($user, $event)) {
            $this->addFlash('info', 'Confirmation de desinscription envoyee');
        }

        if ($wasConfirmed) {
            $waitlisted = $registrationRepo->findFirstWaitlisted($event);
            if ($waitlisted instanceof EventRegistration) {
                $waitlisted->setStatus(RegistrationStatus::CONFIRMED);
                $entityManager->flush();

                $waitlistedUser = $waitlisted->getUser();
                if ($waitlistedUser instanceof User && $this->mailerService->sendEventSpotFreedNotification($waitlistedUser, $event)) {
                    $this->addFlash('success', "Un membre en liste d'attente a ete notifie");
                }
            }
        }

        $this->addFlash('success', "Vous vous etes desinscrit de l'evenement : ".$event->getTitle());

        return $this->redirectToRoute('app_event_discover');
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ?Event $event, EntityManagerInterface $entityManager): Response
    {
        if (!$event instanceof Event) {
            throw $this->createNotFoundException("Cet evenement n'existe pas.");
        }

        $user = $this->getCurrentUser();
        if ($event->getCreatedBy() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException("Vous n'etes pas le createur de cet evenement.");
        }

        $oldDate = clone $event->getStartDateTime();
        $oldLocation = $event->getLocation();

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $changes = [];
            if ($oldDate != $event->getStartDateTime()) {
                $changes[] = 'Date modifiee : '.$event->getStartDateTime()->format('d/m/Y H:i');
            }
            if ($oldLocation !== $event->getLocation()) {
                $changes[] = 'Lieu modifie : '.$event->getLocation();
            }

            if ($changes !== []) {
                $results = $this->mailerService->sendEventUpdateNotification($event, $changes);
                $sentCount = count(array_filter($results));
                if ($sentCount > 0) {
                    $this->addFlash('info', "Notification envoyee a $sentCount inscrit(s)");
                }
            }

            $this->addFlash('success', 'Evenement "'.$event->getTitle().'" modifie avec succes !');

            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_event_index' : 'app_event_my_events');
        }

        return $this->render('event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', methods: ['GET'])]
    public function show(?Event $event, EventRegistrationRepository $registrationRepo): Response
    {
        if (!$event instanceof Event) {
            throw $this->createNotFoundException("Cet evenement n'existe pas.");
        }

        $isRegistered = false;
        $registrationStatus = null;

        $user = $this->getCurrentUser();
        if ($user instanceof User) {
            $registration = $registrationRepo->findUserRegistrationForEvent($event, $user);
            if ($registration instanceof EventRegistration) {
                $isRegistered = true;
                $registrationStatus = $registration->getStatus();
            }
        }

        return $this->render($this->isGranted('ROLE_ADMIN') ? 'event/show.html.twig' : 'event/showformember.html.twig', [
            'event' => $event,
            'isRegistered' => $isRegistered,
            'registrationStatus' => $registrationStatus,
        ]);
    }

    #[Route('/{id}', name: 'app_event_delete', methods: ['POST'])]
    public function delete(Request $request, ?Event $event, EntityManagerInterface $entityManager): Response
    {
        if (!$event instanceof Event) {
            throw $this->createNotFoundException("Cet evenement n'existe pas.");
        }

        $user = $this->getCurrentUser();
        if ($event->getCreatedBy() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException("Vous n'etes pas le createur de cet evenement.");
        }

        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->getPayload()->getString('_token'))) {
            $eventTitle = $event->getTitle();
            $results = $this->mailerService->sendEventDeletionNotification($event);
            $sentCount = count(array_filter($results));

            if ($sentCount > 0) {
                $this->addFlash('info', "Notification d'annulation envoyee a $sentCount inscrit(s)");
            }

            $entityManager->remove($event);
            $entityManager->flush();
            $this->addFlash('success', 'Evenement "'.$eventTitle.'" supprime avec succes !');
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

        return $this->render('event/index.html.twig', [
            'events' => $eventRepository->findByFilters($search, $status, $sort, $order),
            'stats' => $eventRepository->countByStatus(),
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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte pour tester les emails.');

            return $this->redirectToRoute('app_login');
        }

        $result = $this->mailerService->sendTestEmail($user->getEmail());
        $this->addFlash($result ? 'success' : 'error', $result ? 'Email de test envoye.' : "Echec de l'envoi.");

        return $this->redirectToRoute('app_event_discover');
    }

    #[Route('/test-reminder/{id}', name: 'app_event_test_reminder', methods: ['GET'])]
    public function testReminder(Event $event): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $sentCount = 0;
        foreach ($event->getRegistrations() as $registration) {
            if ($registration->getStatus() !== RegistrationStatus::CONFIRMED) {
                continue;
            }

            $user = $registration->getUser();
            if ($user instanceof User && $this->mailerService->sendEventReminderNotification($user, $event)) {
                $sentCount++;
            }
        }

        $this->addFlash('success', "Rappel envoye a $sentCount inscrit(s) pour l'evenement ".$event->getTitle());

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
