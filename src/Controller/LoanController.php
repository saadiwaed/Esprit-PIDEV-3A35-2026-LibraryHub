<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Enum\LoanStatus;
use App\Enum\PaymentStatus;
use App\Form\LatePenaltyType;
use App\Form\LoanSearchType;
use App\Form\LoanType;
use App\Repository\LoanRepository;
use App\Service\LoanService;
use App\Service\LoanReminderService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/loans', name: 'loan_')]
class LoanController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(LoanRepository $loanRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $form = $this->createForm(LoanSearchType::class);
        $form->handleRequest($request);

        $filters = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData() ?? [];
        }

        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $sortBy = $request->query->getString('sortBy', '');
        if ($sortBy === '') {
            $sortBy = $this->resolveLegacyLoanSort(
                $request->query->getString('sort', ''),
                $request->query->getString('direction', 'asc')
            );
        }

        $queryBuilder = $loanRepository->findByFiltersAndSort($filters, $sortBy !== '' ? $sortBy : null);

        $loans = $paginator->paginate($queryBuilder, $page, $limit, [
            'distinct' => true,
            'pageParameterName' => 'page',
        ]);

        $totalResults = $loans->getTotalItemCount();
        $maxPage = (int) ceil(max(1, $totalResults) / $limit);

        return $this->render('loan/index.html.twig', [
            'form' => $form->createView(),
            'loans' => $loans,
            'totalResults' => $totalResults,
            'currentPage' => $page,
            'maxPage' => $maxPage,
            'limit' => $limit,
            'sortBy' => $sortBy,
            'sort' => $request->query->getString('sort', ''),
            'direction' => $request->query->getString('direction', 'asc'),
        ]);
    }

    private function resolveLegacyLoanSort(string $sort, string $direction): string
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return match (sprintf('%s_%s', $sort, $direction)) {
            'member_asc' => LoanRepository::SORT_MEMBER_NAME_ASC,
            'checkout_desc' => LoanRepository::SORT_CHECKOUT_DESC,
            'due_asc' => LoanRepository::SORT_DUE_ASC,
            'return_desc' => LoanRepository::SORT_RETURN_DESC,
            'status_asc' => LoanRepository::SORT_STATUS_PRIORITY,
            'id_desc' => LoanRepository::SORT_ID_DESC,
            default => '',
        };
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, LoanService $loanService): Response
    {
        $loan = new Loan();
        $initialCheckoutTime = new \DateTimeImmutable();
        $loan->setCheckoutTime($initialCheckoutTime);
        $loan->setDueDate($loanService->calculateDueDate($initialCheckoutTime));

        $form = $this->createForm(LoanType::class, $loan, [
            'allow_return_date_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $loanService->createLoan($loan);

            $this->addFlash('success', sprintf(
                'Emprunt cree. Date limite automatique: %s',
                $loan->getDueDate()?->format('d/m/Y')
            ));

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('loan/new.html.twig', [
            'loan' => $loan,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\\d+>}', name: 'show', methods: ['GET'])]
    public function show(
        Loan $loan,
        #[Autowire('%app.loan.daily_late_fee_rate%')] float $dailyLateFeeRate,
    ): Response
    {
        $daysLate = $loan->getDaysLate();

        $latePenaltyFormView = null;
        $activeLatePenalty = $loan->getActiveLatePenalty();
        if (
            $loan->getStatus() === LoanStatus::OVERDUE
            && $activeLatePenalty === null
            && ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN'))
        ) {
            $latePenaltyForm = $this->createForm(LatePenaltyType::class, [
                'amount' => round(max(0, $daysLate) * max(0, $dailyLateFeeRate), 2),
                'dailyRate' => $dailyLateFeeRate,
                'notes' => null,
            ], [
                'action' => $this->generateUrl('loan_create_late_penalty_submit', ['id' => $loan->getId()]),
                'method' => 'POST',
                'default_daily_rate' => $dailyLateFeeRate,
            ]);

            $latePenaltyFormView = $latePenaltyForm->createView();
        }

        return $this->render('loan/show.html.twig', [
            'loan' => $loan,
            'daysLate' => $daysLate,
            'latePenaltyForm' => $latePenaltyFormView,
            'activeLatePenalty' => $activeLatePenalty,
            'latePenaltyReason' => sprintf('Retard journalier - Retard de %d jours', $daysLate),
            'latePenaltyIssueDate' => new \DateTime('today'),
            'latePenaltyStatus' => PaymentStatus::UNPAID,
        ]);
    }

    #[Route('/{id<\\d+>}/create-late-penalty', name: 'create_late_penalty', methods: ['GET'])]
    public function createLatePenalty(
        Loan $loan,
        #[Autowire('%app.loan.daily_late_fee_rate%')] float $dailyLateFeeRate,
    ): Response
    {
        $this->assertAdminOrLibrarian();
        if ($loan->getStatus() !== LoanStatus::OVERDUE) {
            throw $this->createNotFoundException('Cet emprunt n\'est pas en retard.');
        }

        $daysLate = $loan->getDaysLate();
        $form = $this->createForm(LatePenaltyType::class, [
            'amount' => round(max(0, $daysLate) * max(0, $dailyLateFeeRate), 2),
            'dailyRate' => $dailyLateFeeRate,
            'notes' => null,
        ], [
            'action' => $this->generateUrl('loan_create_late_penalty_submit', ['id' => $loan->getId()]),
            'method' => 'POST',
            'default_daily_rate' => $dailyLateFeeRate,
        ]);

        return $this->render('loan/create_late_penalty.html.twig', [
            'loan' => $loan,
            'daysLate' => $daysLate,
            'reason' => sprintf('Retard journalier - Retard de %d jours', $daysLate),
            'issueDate' => new \DateTime('today'),
            'status' => PaymentStatus::UNPAID,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id<\\d+>}/create-late-penalty', name: 'create_late_penalty_submit', methods: ['POST'])]
    public function createLatePenaltySubmit(
        Loan $loan,
        Request $request,
        EntityManagerInterface $entityManager,
        LoanReminderService $loanReminderService,
        LoggerInterface $logger,
        #[Autowire('%app.loan.daily_late_fee_rate%')] float $dailyLateFeeRate,
    ): Response {
        $this->assertAdminOrLibrarian();
        if ($loan->getStatus() !== LoanStatus::OVERDUE) {
            throw $this->createNotFoundException('Cet emprunt n\'est pas en retard.');
        }

        $daysLate = $loan->getDaysLate();
        $reason = sprintf('Retard journalier - Retard de %d jours', $daysLate);
        $issueDate = new \DateTime('today');
        $status = PaymentStatus::UNPAID;

        $form = $this->createForm(LatePenaltyType::class, [
            'amount' => round(max(0, $daysLate) * max(0, $dailyLateFeeRate), 2),
            'dailyRate' => $dailyLateFeeRate,
            'notes' => null,
        ], [
            'action' => $this->generateUrl('loan_create_late_penalty_submit', ['id' => $loan->getId()]),
            'method' => 'POST',
            'default_daily_rate' => $dailyLateFeeRate,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('loan/create_late_penalty.html.twig', [
                'loan' => $loan,
                'daysLate' => $daysLate,
                'reason' => $reason,
                'issueDate' => $issueDate,
                'status' => $status,
                'form' => $form->createView(),
            ]);
        }

        /** @var array{amount: mixed, dailyRate?: mixed, notes: mixed} $data */
        $data = $form->getData();

        $rate = isset($data['dailyRate']) && is_numeric($data['dailyRate']) ? (float) $data['dailyRate'] : $dailyLateFeeRate;
        $rate = round(max(0.01, $rate), 2);
        $amount = round(max(0, $daysLate) * $rate, 2);

        $penalty = (new Penalty())
            ->setLoan($loan)
            ->setDailyRate($rate)
            ->setLateDays(max(0, $daysLate))
            ->setAmount($amount)
            ->setReason($reason)
            ->setIssueDate($issueDate)
            ->setWaived(false)
            ->setStatus($status)
            ->setNotes(trim((string) ($data['notes'] ?? '')) ?: null);

        $entityManager->persist($penalty);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Pénalité ajoutée avec succès pour %d jours de retard', $daysLate));

        try {
            $reminder = $loanReminderService->sendPenaltyUpdate($penalty, 'created');
            $sentAt = new \DateTimeImmutable();

            if (($reminder['should_update_email_sent_at'] ?? false) === true) {
                $loan->setLastEmailReminderSentAt($sentAt);
            }
            if (($reminder['should_update_sms_sent_at'] ?? false) === true) {
                $loan->setLastSmsReminderSentAt($sentAt);
            }

            if (($reminder['should_update_email_sent_at'] ?? false) === true || ($reminder['should_update_sms_sent_at'] ?? false) === true) {
                $entityManager->flush();
            }
        } catch (\Throwable $e) {
            $logger->error('Penalty reminder failed after creation.', ['loan_id' => $loan->getId(), 'penalty_id' => $penalty->getId(), 'exception' => $e]);
        }

        return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
    }

    private function assertAdminOrLibrarian(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN')) {
            return;
        }

        throw $this->createAccessDeniedException('Accès réservé aux administrateurs/bibliothécaires.');
    }

    #[Route('/{id<\\d+>}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Loan $loan, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LoanType::class, $loan, [
            'allow_return_date_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Loan updated successfully');

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('loan/edit.html.twig', [
            'loan' => $loan,
            'form' => $form,
        ]);
    }

    #[Route('/{id<\\d+>}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Loan $loan, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $loan->getId(), $request->request->get('_token'))) {
            $entityManager->remove($loan);
            $entityManager->flush();

            $this->addFlash('success', 'Loan deleted successfully');
        }

        return $this->redirectToRoute('loan_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id<\\d+>}/return', name: 'return', methods: ['POST'])]
    public function returnLoan(Request $request, Loan $loan, LoanService $loanService): Response
    {
        if (!$this->isCsrfTokenValid('return' . $loan->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
        }

        try {
            $loanService->returnLoan($loan);
            $this->addFlash('success', 'Retour enregistre avec succes.');
        } catch (\LogicException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
    }
}
