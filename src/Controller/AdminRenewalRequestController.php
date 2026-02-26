<?php

namespace App\Controller;

use App\Entity\RenewalRequest;
use App\Repository\RenewalRequestRepository;
use App\Service\AIRenewalSuggester;
use App\Service\LoanReminderService;
use App\Service\RenewalService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class AdminRenewalRequestController extends AbstractController
{
    #[Route('/admin/renewal-requests', name: 'admin_renewal_requests', methods: ['GET'])]
    public function index(
        Request $request,
        RenewalRequestRepository $renewalRequestRepository,
        AIRenewalSuggester $aiRenewalSuggester,
        PaginatorInterface $paginator,
        LoggerInterface $logger
    ): Response {
        $this->assertAdminOrLibrarian();

        $status = strtoupper(trim((string) $request->query->get('status', RenewalRequest::STATUS_PENDING)));
        if ($status === 'ALL') {
            $status = '';
        }

        $qb = $renewalRequestRepository->createAdminListQueryBuilder($status !== '' ? $status : null);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $pagination = $paginator->paginate($qb, $page, $limit, [
            'distinct' => true,
            'pageParameterName' => 'page',
        ]);

        $aiSuggestions = [];
        foreach ($pagination as $rr) {
            if (!$rr instanceof RenewalRequest) {
                continue;
            }
            if ($rr->getStatus() !== RenewalRequest::STATUS_PENDING) {
                continue;
            }
            try {
                $aiSuggestions[(int) ($rr->getId() ?? 0)] = $aiRenewalSuggester->getSuggestion($rr);
            } catch (\Throwable $e) {
                $logger->error('AI suggester failed for renewal request', ['id' => $rr->getId(), 'exception' => $e]);
                $aiSuggestions[(int) ($rr->getId() ?? 0)] = ['recommendation' => 'unknown', 'reason' => 'Suggestion IA indisponible (erreur interne)', 'confidence' => 0];
            }
        }

        return $this->render('admin/renewal_requests/index.html.twig', [
            'requests' => $pagination,
            'aiSuggestions' => $aiSuggestions,
            'status' => $status !== '' ? $status : 'ALL',
            'statuses' => [
                'PENDING' => 'En attente',
                'APPROVED' => 'Approuvée',
                'REJECTED' => 'Refusée',
                'ALL' => 'Toutes',
            ],
        ]);
    }

    #[Route('/admin/renewal-requests/{id<\\d+>}/approve', name: 'admin_renewal_request_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        RenewalRequest $renewalRequest,
        RenewalService $renewalService,
        EntityManagerInterface $entityManager,
        LoanReminderService $loanReminderService,
        LoggerInterface $logger,
    ): Response {
        $this->assertAdminOrLibrarian();

        if (!$this->isCsrfTokenValid('approve_renewal_request_' . $renewalRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_renewal_requests');
        }

        if ($renewalRequest->getStatus() !== RenewalRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Cette demande n\'est plus en attente.');

            return $this->redirectToRoute('admin_renewal_requests');
        }

        $loan = $renewalRequest->getLoan();
        if (!$loan) {
            $this->addFlash('error', 'Emprunt introuvable.');

            return $this->redirectToRoute('admin_renewal_requests');
        }

        $dueDate = $loan->getDueDate();
        if (!$dueDate instanceof \DateTimeInterface) {
            $this->addFlash('error', 'Date limite manquante. Renouvellement impossible.');

            return $this->redirectToRoute('admin_renewal_requests');
        }

        $renewalDays = (int) $this->getParameter('renewal_days');
        $proposedNewDueDate = \DateTimeImmutable::createFromInterface($dueDate)->modify(sprintf('+%d days', $renewalDays));

        try {
            $renewalService->renewLoan($loan, $proposedNewDueDate);
            $renewalRequest->setStatus(RenewalRequest::STATUS_APPROVED);
            $entityManager->persist($renewalRequest);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Renouvellement approuvé – nouvel échéancier: %s', $proposedNewDueDate->format('d/m/Y')));
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        try {
            $reminder = $loanReminderService->sendRenewalRequestStatusUpdate($renewalRequest);
            if (($reminder['should_update_email_sent_at'] ?? false) === true) {
                $renewalRequest->setLastEmailReminderSentAt(new \DateTimeImmutable());
                $entityManager->flush();
            }
        } catch (\Throwable $e) {
            $logger->error('RenewalRequest reminder failed after approval.', ['renewal_request_id' => $renewalRequest->getId(), 'exception' => $e]);
        }

        try {
            $reminder = $loanReminderService->sendRenewalRequestStatusUpdate($renewalRequest);
            if (($reminder['should_update_email_sent_at'] ?? false) === true) {
                $renewalRequest->setLastEmailReminderSentAt(new \DateTimeImmutable());
                $entityManager->flush();
            }
        } catch (\Throwable $e) {
            $logger->error('RenewalRequest reminder failed after rejection.', ['renewal_request_id' => $renewalRequest->getId(), 'exception' => $e]);
        }

        return $this->redirectToRoute('admin_renewal_requests');
    }

    #[Route('/admin/renewal-requests/{id<\\d+>}/reject', name: 'admin_renewal_request_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        RenewalRequest $renewalRequest,
        EntityManagerInterface $entityManager,
        LoanReminderService $loanReminderService,
        LoggerInterface $logger,
    ): Response {
        $this->assertAdminOrLibrarian();

        if (!$this->isCsrfTokenValid('reject_renewal_request_' . $renewalRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_renewal_requests');
        }

        if ($renewalRequest->getStatus() !== RenewalRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Cette demande n\'est plus en attente.');

            return $this->redirectToRoute('admin_renewal_requests');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason !== '') {
            $existing = trim((string) ($renewalRequest->getNotes() ?? ''));
            $renewalRequest->setNotes(trim($existing . "\nMotif du refus: " . $reason));
        }

        $renewalRequest->setStatus(RenewalRequest::STATUS_REJECTED);

        $entityManager->persist($renewalRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande refusée');

        return $this->redirectToRoute('admin_renewal_requests');
    }

    private function assertAdminOrLibrarian(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN')) {
            return;
        }

        throw $this->createAccessDeniedException('Accès réservé aux administrateurs/bibliothécaires.');
    }
}
