<?php

namespace App\Controller;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ClubStatus;
use App\Form\ClubType;
use App\Form\EventType;
use App\Repository\ClubRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/club')]
final class ClubController extends AbstractController
{
    public function __construct(private readonly MailerService $mailerService)
    {
    }

    #[Route('/', name: 'app_club_index', methods: ['GET'])]
    public function index(Request $request, ClubRepository $clubRepository): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $category = $request->query->get('category', '');
        $sort = $request->query->get('sort', 'createdDate');
        $order = $request->query->get('order', 'desc');

        return $this->render('club/index.html.twig', [
            'clubs' => $clubRepository->findByFilters($search, $status, $category, $sort, $order),
            'stats' => $clubRepository->countByStatus(),
            'categories' => $clubRepository->findAllCategories(),
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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte pour creer un club.');

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

            $emailSent = $this->mailerService->sendClubCreationConfirmation($user, $club);
            if ($emailSent) {
                $this->addFlash('success', 'Email de confirmation envoye a '.$user->getEmail());
            } else {
                $this->addFlash('warning', "L'email de confirmation n'a pas pu etre envoye.");
            }

            $this->addFlash('success', 'Votre club "'.$club->getTitle().'" a ete cree avec succes.');

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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte pour creer un club.');

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

            $this->mailerService->sendClubCreationConfirmation($user, $club);
            $this->addFlash('success', 'Votre club "'.$club->getTitle().'" a ete cree avec succes.');

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
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $clubs = $user->getClubs()->filter(
            static fn (Club $club): bool => $club->getStatus() === ClubStatus::ACTIVE
        );

        return $this->render('club/my_clubs.html.twig', ['clubs' => $clubs]);
    }

    #[Route('/member-view/{id}', name: 'app_showformember', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showForMember(Club $club): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User || !$club->isMember($user)) {
            $this->addFlash('error', 'Vous devez etre membre pour voir cette page.');

            return $this->redirectToRoute('app_club_discover');
        }

        return $this->render('club/showformember.html.twig', [
            'club' => $club,
            'isMember' => true,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_club_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $currentUser = $this->getCurrentUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isFounder = $currentUser instanceof User && $club->getFounder() === $currentUser;

        if (!$isFounder && !$isAdmin) {
            $this->addFlash('error', 'Seul le fondateur ou un administrateur peut modifier le club.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $results = $this->mailerService->sendClubUpdateNotification($club);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "Notification envoyee a $sentCount membre(s)");
            }

            $this->addFlash('success', 'Le club a ete modifie avec succes.');

            return $this->redirectToRoute(
                $isAdmin ? 'app_club_show' : 'app_showformember',
                ['id' => $club->getId()]
            );
        }

        return $this->render($isAdmin ? 'club/edit.html.twig' : 'club/editformember.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/event/new', name: 'app_club_event_create', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function createClubEvent(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User || $club->getFounder() !== $user) {
            $this->addFlash('error', 'Seul le fondateur du club peut creer des evenements officiels.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        $event = new Event();
        $event->setCreatedBy($user);
        $event->addOrganizingClub($club);

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();

            $results = $this->mailerService->sendNewEventNotification($club, $event);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "Annonce envoyee a $sentCount membre(s)");
            }

            $this->addFlash('success', 'Evenement cree pour le club '.$club->getTitle());

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        return $this->render('event/new.html.twig', [
            'club' => $club,
            'form' => $form->createView(),
            'button_label' => "Creer l'evenement pour le club",
            'hide_club_field' => true,
        ]);
    }

    #[Route('/{id}/transfer-ownership', name: 'app_club_transfer_ownership', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function transferOwnership(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User || $club->getFounder() !== $user) {
            throw $this->createAccessDeniedException('Seul le fondateur peut transferer le club.');
        }

        $newFounder = $entityManager->getRepository(User::class)->find($request->request->getInt('new_founder_id'));
        if (!$newFounder instanceof User) {
            throw $this->createNotFoundException('Nouveau fondateur introuvable.');
        }

        if (!$club->isMember($newFounder)) {
            throw $this->createAccessDeniedException('Le nouveau fondateur doit etre membre du club.');
        }

        $oldFounder = $user;

        $club->setFounder($newFounder);
        $entityManager->flush();

        $results = $this->mailerService->sendOwnershipTransferNotification($oldFounder, $newFounder, $club);
        if ($results['old'] === true) {
            $this->addFlash('info', "Notification envoyee a l'ancien fondateur");
        }
        if ($results['new'] === true) {
            $this->addFlash('info', 'Notification envoyee au nouveau fondateur');
        }

        $this->addFlash('success', 'La propriete du club a ete transferee a '.$newFounder->getFirstName());

        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    #[Route('/{id}/change-founder', name: 'app_club_change_founder', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function changeFounder(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $newFounder = $entityManager->getRepository(User::class)->find($request->request->getInt('new_founder_id'));
        if (!$newFounder instanceof User) {
            $this->addFlash('error', 'Utilisateur non trouve.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        if (!$club->isMember($newFounder)) {
            $this->addFlash('error', 'Le nouveau fondateur doit etre membre du club.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        $oldFounder = $club->getFounder();
        $club->setFounder($newFounder);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'Admin: Fondateur change de '.($oldFounder?->getEmail() ?? 'Personne').' -> '.$newFounder->getEmail()
        );

        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    #[Route('/{id}/join', name: 'app_club_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$club->canJoin()) {
            $this->addFlash('error', "Ce club n'accepte pas de nouveaux membres.");

            return $this->redirectToRoute('app_club_discover');
        }

        if ($club->isMember($user)) {
            $this->addFlash('warning', 'Vous etes deja membre de ce club.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        if (!$this->isCsrfTokenValid('join'.$club->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_club_discover');
        }

        $club->addMember($user);
        $entityManager->flush();

        $this->mailerService->sendWelcomeToNewMember($user, $club);
        $this->mailerService->sendNewMemberNotificationToFounder($user, $club);

        $this->addFlash('success', 'Vous avez rejoint le club "'.$club->getTitle().'" !');

        return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
    }

    #[Route('/{id}/leave', name: 'app_club_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function leave(Request $request, Club $club, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($club->getFounder() === $user) {
            $this->addFlash('error', 'Vous etes le fondateur du club.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        if (!$club->isMember($user)) {
            $this->addFlash('warning', "Vous n'etes pas membre de ce club.");

            return $this->redirectToRoute('app_club_discover');
        }

        if (!$this->isCsrfTokenValid('leave'.$club->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        $club->removeMember($user);
        $entityManager->flush();

        $this->mailerService->sendMemberLeftNotificationToFounder($user, $club);
        $this->addFlash('success', 'Vous avez quitte le club "'.$club->getTitle().'".');

        return $this->redirectToRoute('app_club_discover');
    }

    #[Route('/{id}', name: 'app_club_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Club $club): Response
    {
        $isMember = false;
        $user = $this->getCurrentUser();
        if ($user instanceof User) {
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
        $user = $this->getCurrentUser();
        if (($club->getFounder() !== $user) && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', "Vous n'avez pas les droits pour supprimer ce club.");

            return $this->redirectToRoute('app_showformember', ['id' => $club->getId()]);
        }

        if ($this->isCsrfTokenValid('delete'.$club->getId(), $request->getPayload()->getString('_token'))) {
            $results = $this->mailerService->sendClubDeletionNotification($club);
            $sentCount = count(array_filter($results));
            if ($sentCount > 0) {
                $this->addFlash('info', "Notification de suppression envoyee a $sentCount membre(s)");
            }

            $entityManager->remove($club);
            $entityManager->flush();
            $this->addFlash('success', 'Le club a ete supprime avec succes.');
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

        $userClubs = [];
        $user = $this->getCurrentUser();
        if ($user instanceof User) {
            $userClubs = $user->getClubs()->map(static fn (Club $club): ?int => $club->getId())->toArray();
        }

        return $this->render('club/discover.html.twig', [
            'clubs' => $clubRepository->findByFilters($search, $status, $category, $sort, $order),
            'categories' => $clubRepository->findAllCategories(),
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

    #[Route('/test-email', name: 'app_test_email')]
    public function testEmail(): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte pour tester les emails.');

            return $this->redirectToRoute('app_login');
        }

        $result = $this->mailerService->sendTestEmail($user->getEmail());
        $this->addFlash($result ? 'success' : 'error', $result ? 'Email de test envoye.' : "Echec de l'envoi.");

        return $this->redirectToRoute('app_club_index');
    }

    #[Route('/debug-mail', name: 'app_debug_mail')]
    public function debugMail(TransportInterface $transport): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $email = (new Email())
                ->from('azizarfaoui0987@gmail.com')
                ->to($user->getEmail())
                ->subject('TEST DEBUG')
                ->text('Ceci est un test de debug');

            $transport->send($email);
            $this->addFlash('success', 'Email de debug envoye.');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Erreur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_club_index');
    }

    #[Route('/test-mailer-service', name: 'app_test_mailer_service')]
    public function testMailerService(MailerService $mailerService): Response
    {
        $user = $this->getCurrentUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez etre connecte.');

            return $this->redirectToRoute('app_login');
        }

        $result = $mailerService->sendTestEmail($user->getEmail());
        $this->addFlash($result ? 'success' : 'error', $result ? 'Email de test envoye via MailerService.' : 'Echec envoi via MailerService.');

        return $this->redirectToRoute('app_club_index');
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
    
}
