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
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function show(Loan $loan): Response
    {
        $latePenaltyForm = null;
        $daysLate = $loan->getDaysLate();
        $penaltiesCount = $loan->getPenaltiesCount();

        if (
            $this->isGranted('ROLE_LIBRARIAN')
            && $loan->getStatus() === LoanStatus::OVERDUE
            && $penaltiesCount === 0
        ) {
            $latePenaltyForm = $this->createForm(LatePenaltyType::class, [
                'amount' => null,
                'notes' => null,
            ], [
                'action' => $this->generateUrl('loan_add_penalty', ['id' => $loan->getId()]),
                'method' => 'POST',
            ])->createView();
        }

        return $this->render('loan/show.html.twig', [
            'loan' => $loan,
            'latePenaltyForm' => $latePenaltyForm,
            'daysLate' => $daysLate,
            'penaltiesCount' => $penaltiesCount,
            'latePenaltyReason' => sprintf('Retard de %d jours', $daysLate),
            'latePenaltyIssueDate' => new \DateTimeImmutable('today'),
            'latePenaltyStatus' => PaymentStatus::UNPAID,
        ]);
    }

    #[Route('/{id<\\d+>}/add-penalty', name: 'add_penalty_form', methods: ['GET'])]
    #[Route('/{id<\\d+>}/create-late-penalty', name: 'create_late_penalty', methods: ['GET'])]
    public function createLatePenalty(Loan $loan): Response
    {
        $this->denyAccessUnlessGranted('ROLE_LIBRARIAN');
        $daysLate = $loan->getDaysLate();
        if ($loan->getStatus() !== LoanStatus::OVERDUE || $daysLate <= 0) {
            throw $this->createNotFoundException('Cet emprunt n\'est pas en retard.');
        }

        if ($loan->hasPenalty()) {
            $this->addFlash('info', 'Une pénalité existe déjà pour cet emprunt.');

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
        }

        $form = $this->createForm(LatePenaltyType::class, [
            'amount' => null,
            'notes' => null,
        ], [
            'action' => $this->generateUrl('loan_add_penalty', ['id' => $loan->getId()]),
            'method' => 'POST',
        ]);

        return $this->render('loan/create_late_penalty.html.twig', [
            'loan' => $loan,
            'daysLate' => $daysLate,
            'reason' => sprintf('Retard de %d jours', $daysLate),
            'issueDate' => new \DateTimeImmutable('today'),
            'status' => PaymentStatus::UNPAID,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id<\\d+>}/add-penalty', name: 'add_penalty', methods: ['POST'])]
    #[Route('/{id<\\d+>}/create-late-penalty', name: 'create_late_penalty_submit', methods: ['POST'])]
    public function createLatePenaltySubmit(
        Loan $loan,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_LIBRARIAN');
        $daysLate = $loan->getDaysLate();
        if ($loan->getStatus() !== LoanStatus::OVERDUE || $daysLate <= 0) {
            throw $this->createNotFoundException('Cet emprunt n\'est pas en retard.');
        }

        if ($loan->hasPenalty()) {
            $this->addFlash('info', 'Une pénalité existe déjà pour cet emprunt.');

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
        }

        $reason = sprintf('Retard de %d jours', $daysLate);
        $issueDate = new \DateTimeImmutable('today');
        $status = PaymentStatus::UNPAID;

        $form = $this->createForm(LatePenaltyType::class, [
            'amount' => null,
            'notes' => null,
        ], [
            'action' => $this->generateUrl('loan_add_penalty', ['id' => $loan->getId()]),
            'method' => 'POST',
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

        /** @var array{amount: mixed, notes: mixed} $data */
        $data = $form->getData();

        $penalty = (new Penalty())
            ->setLoan($loan)
            ->setAmount((float) $data['amount'])
            ->setLateDays($daysLate)
            ->setReason($reason)
            ->setIssueDate($issueDate)
            ->setWaived(false)
            ->setStatus($status)
            ->setNotes(trim((string) ($data['notes'] ?? '')) ?: null);

        $entityManager->persist($penalty);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Pénalité enregistrée pour %d jours de retard', $daysLate));

        return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
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
        if ($this->isCsrfTokenValid('delete' . $loan->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($loan);
            $entityManager->flush();

            $this->addFlash('success', 'Loan deleted successfully');
        }

        return $this->redirectToRoute('loan_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id<\\d+>}/return', name: 'return', methods: ['POST'])]
    public function returnLoan(Request $request, Loan $loan, LoanService $loanService): Response
    {
        if (!$this->isCsrfTokenValid('return' . $loan->getId(), (string) $request->request->get('_token'))) {
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

