<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\BookCopy;
use App\Enum\LoanStatus;
use App\Repository\BookCopyRepository;
use App\Repository\LoanRequestRepository;
use App\Service\LoanReminderService;
use App\Service\SmsNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class AdminLoanRequestController extends AbstractController
{
    #[Route('/admin/loan-requests', name: 'admin_loan_requests', methods: ['GET'])]
    public function index(
        Request $request,
        LoanRequestRepository $loanRequestRepository,
        PaginatorInterface $paginator,
    ): Response {
        $this->assertAdminOrLibrarian();

        $status = strtoupper(trim((string) $request->query->get('status', LoanRequest::STATUS_PENDING)));
        if ($status === 'ALL') {
            $status = '';
        }

        $qb = $loanRequestRepository->createQueryBuilder('lr')
            ->leftJoin('lr.member', 'm')
            ->addSelect('m')
            ->orderBy('lr.requestedAt', 'DESC')
            ->addOrderBy('lr.id', 'DESC');

        if ($status !== '') {
            $qb->andWhere('lr.status = :status')
                ->setParameter('status', $status);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $pagination = $paginator->paginate($qb, $page, $limit, [
            'distinct' => true,
            'pageParameterName' => 'page',
        ]);

        return $this->render('admin/loan_requests/index.html.twig', [
            'requests' => $pagination,
            'status' => $status !== '' ? $status : 'ALL',
            'statuses' => [
                'PENDING' => 'En attente',
                'APPROVED' => 'Approuvée',
                'REJECTED' => 'Refusée',
                'ALL' => 'Toutes',
            ],
        ]);
    }

    #[Route('/admin/loan-requests/{id<\\d+>}/approve', name: 'admin_loan_request_approve', methods: ['POST'])]
    public function approve(
        Request $request,
        LoanRequest $loanRequest,
        BookCopyRepository $bookCopyRepository,
        EntityManagerInterface $entityManager,
        LoanReminderService $loanReminderService,
        SmsNotifier $smsNotifier,
        LoggerInterface $logger,
    ): Response {
        $this->assertAdminOrLibrarian();

        if (!$this->isCsrfTokenValid('approve_loan_request_' . $loanRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_loan_requests');
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Cette demande n\'est plus en attente.');

            return $this->redirectToRoute('admin_loan_requests');
        }

        $bookCopy = $bookCopyRepository->find($loanRequest->getBookId());
        if ($bookCopy === null) {
            $bookCopy = new BookCopy();
            $entityManager->persist($bookCopy);
        }

        $member = $loanRequest->getMember();
        if ($member === null) {
            $this->addFlash('error', 'Le membre associé à cette demande est introuvable.');

            return $this->redirectToRoute('admin_loan_requests');
        }

        $now = new \DateTimeImmutable();
        $desiredLoanDate = $loanRequest->getDesiredLoanDate();
        $checkoutTime = $now;
        if ($desiredLoanDate instanceof \DateTimeInterface) {
            $desiredDay = \DateTimeImmutable::createFromInterface($desiredLoanDate)->setTime(0, 0, 0);
            $today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
            if ($desiredDay <= $today) {
                $checkoutTime = $desiredDay->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
            }
        }

        $desiredReturnDate = $loanRequest->getDesiredReturnDate();
        $dueDate = null;
        if ($desiredReturnDate instanceof \DateTimeInterface) {
            $dueDate = \DateTimeImmutable::createFromInterface($desiredReturnDate)->setTime(0, 0, 0);
        } else {
            $loanDays = (int) $this->getParameter('renewal_days');
            $dueDate = \DateTimeImmutable::createFromInterface($checkoutTime)
                ->setTime(0, 0, 0)
                ->modify(sprintf('+%d days', $loanDays));
        }

        if ($dueDate <= \DateTimeImmutable::createFromInterface($checkoutTime)->setTime(0, 0, 0)) {
            $this->addFlash('error', 'La date de retour souhaitée doit être postérieure à la date d\'emprunt.');

            return $this->redirectToRoute('admin_loan_requests');
        }

        $loan = (new Loan())
            ->setMember($member)
            ->setBookCopy($bookCopy)
            ->setPhoneNumber($loanRequest->getPhoneNumber())
            ->setCheckoutTime(\DateTime::createFromImmutable($checkoutTime))
            ->setDueDate(\DateTime::createFromImmutable($dueDate))
            ->setStatus(LoanStatus::ACTIVE)
            ->setRenewalCount(0);
        $loan->refreshStatusFromDates();

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        $approvalSucceeded = false;
        try {
            $loanRequest->setStatus(LoanRequest::STATUS_APPROVED);

            $entityManager->persist($loan);
            $entityManager->persist($loanRequest);
            $entityManager->flush();

            $connection->commit();
            $approvalSucceeded = true;

            $this->addFlash('success', 'Demande approuvée – emprunt créé');
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->addFlash('error', sprintf('Impossible de créer l\'emprunt : %s', $exception->getMessage()));
        }

        if ($approvalSucceeded) {
            try {
                $reminder = $loanReminderService->sendRequestStatusUpdate($loanRequest);
                if (($reminder['should_update_email_sent_at'] ?? false) === true) {
                    $loanRequest->setLastEmailReminderSentAt(new \DateTimeImmutable());
                    $entityManager->flush();
                }
            } catch (\Throwable $e) {
                $logger->error('LoanRequest reminder failed after approval.', ['loan_request_id' => $loanRequest->getId(), 'exception' => $e]);
            }

            try {
                $smsNotifier->sendLoanRequestStatusUpdate($loanRequest);
                if ($loanRequest->getLastSmsReminderSentAt() instanceof \DateTimeImmutable) {
                    $entityManager->flush();
                }
            } catch (\Throwable $e) {
                $logger->error('LoanRequest SMS notifier failed after approval.', ['loan_request_id' => $loanRequest->getId(), 'exception' => $e]);
            }
        }

        return $this->redirectToRoute('admin_loan_requests');
    }

    #[Route('/admin/loan-requests/{id<\\d+>}/reject', name: 'admin_loan_request_reject', methods: ['POST'])]
    public function reject(
        Request $request,
        LoanRequest $loanRequest,
        EntityManagerInterface $entityManager,
        LoanReminderService $loanReminderService,
        SmsNotifier $smsNotifier,
        LoggerInterface $logger,
    ): Response {
        $this->assertAdminOrLibrarian();

        if (!$this->isCsrfTokenValid('reject_loan_request_' . $loanRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_loan_requests');
        }

        if ($loanRequest->getStatus() !== LoanRequest::STATUS_PENDING) {
            $this->addFlash('error', 'Cette demande n\'est plus en attente.');

            return $this->redirectToRoute('admin_loan_requests');
        }

        $reason = trim((string) $request->request->get('reason', ''));
        if ($reason !== '') {
            $existing = trim((string) ($loanRequest->getNotes() ?? ''));
            $loanRequest->setNotes(trim($existing . "\nMotif du refus: " . $reason));
        }

        $loanRequest->setStatus(LoanRequest::STATUS_REJECTED);

        $entityManager->persist($loanRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande refusée');

        try {
            $reminder = $loanReminderService->sendRequestStatusUpdate($loanRequest);
            if (($reminder['should_update_email_sent_at'] ?? false) === true) {
                $loanRequest->setLastEmailReminderSentAt(new \DateTimeImmutable());
                $entityManager->flush();
            }
        } catch (\Throwable $e) {
            $logger->error('LoanRequest reminder failed after rejection.', ['loan_request_id' => $loanRequest->getId(), 'exception' => $e]);
        }

        try {
            $smsNotifier->sendLoanRequestStatusUpdate($loanRequest);
            if ($loanRequest->getLastSmsReminderSentAt() instanceof \DateTimeImmutable) {
                $entityManager->flush();
            }
        } catch (\Throwable $e) {
            $logger->error('LoanRequest SMS notifier failed after rejection.', ['loan_request_id' => $loanRequest->getId(), 'exception' => $e]);
        }

        return $this->redirectToRoute('admin_loan_requests');
    }

    private function assertAdminOrLibrarian(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN')) {
            return;
        }

        throw $this->createAccessDeniedException('Accès réservé aux administrateurs/bibliothécaires.');
    }
}
