<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\RenewalRequest;
use App\Entity\User;
use App\Enum\LoanStatus;
use App\Form\LoanRequestType;
use App\Repository\BookRepository;
use App\Repository\BookCopyRepository;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\RenewalRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MemberController extends AbstractController
{
    #[Route('/mes-emprunts', name: 'member_loans', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function myLoans(
        Request $request,
        LoanRepository $loanRepository,
        PenaltyRepository $penaltyRepository,
    ): Response {
        $member = $this->getUser();
        if (!$member instanceof User) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $loanRequest = (new LoanRequest())
            ->setDesiredLoanDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(LoanRequestType::class, $loanRequest, [
            'action' => $this->generateUrl('member_loan_request_create'),
            'method' => 'POST',
        ]);

        return $this->render('member/my_loans.html.twig', $this->buildLoansPageData(
            member: $member,
            loanRepository: $loanRepository,
            penaltyRepository: $penaltyRepository,
            loanRequestForm: $form->createView(),
            activeTab: $request->query->getString('tab', 'loans'),
        ));
    }

    #[Route('/emprunts/demander', name: 'member_loan_request_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function createLoanRequest(
        Request $request,
        LoanRepository $loanRepository,
        PenaltyRepository $penaltyRepository,
        BookRepository $bookRepository,
        BookCopyRepository $bookCopyRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $member = $this->getUser();
        if (!$member instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $loanRequest = (new LoanRequest())
            ->setMember($member)
            ->setRequestedAt(new \DateTimeImmutable())
            ->setStatus(LoanRequest::STATUS_PENDING)
            ->setDesiredLoanDate(new \DateTimeImmutable('today'));

        $form = $this->createForm(LoanRequestType::class, $loanRequest, [
            'action' => $this->generateUrl('member_loan_request_create'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('member_loans', ['tab' => 'request']);
        }

        $enteredId = $loanRequest->getBookId();
        if ($enteredId > 0) {
            $bookExists = $bookRepository->find($enteredId) !== null;
            $bookCopyExists = $bookCopyRepository->find($enteredId) !== null;

            if (!$bookExists && !$bookCopyExists) {
                $form->get('bookId')->addError(new FormError('Livre non trouvé.'));
            }
        }

        $desiredLoanDate = $loanRequest->getDesiredLoanDate();
        if ($desiredLoanDate instanceof \DateTimeInterface) {
            $today = new \DateTimeImmutable('today');
            $desiredDay = \DateTimeImmutable::createFromInterface($desiredLoanDate)->setTime(0, 0, 0);
            if ($desiredDay < $today) {
                $form->get('desiredLoanDate')->addError(new FormError('La date d\'emprunt souhaitée doit être aujourd\'hui ou ultérieure.'));
            }
        }

        if ($form->isValid()) {
            $entityManager->persist($loanRequest);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande d\'emprunt a été envoyée avec succès !');

            return $this->redirectToRoute('member_loans', ['tab' => 'request']);
        }

        return $this->render('member/my_loans.html.twig', $this->buildLoansPageData(
            member: $member,
            loanRepository: $loanRepository,
            penaltyRepository: $penaltyRepository,
            loanRequestForm: $form->createView(),
            activeTab: 'request',
        ));
    }

    #[Route('/loans/{id<\\d+>}/request-renewal', name: 'member_loan_request_renewal', methods: ['POST'])]
    #[Route('/mes-emprunts/{id<\\d+>}/renouveler', name: 'member_loan_renew', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function requestRenewal(
        Request $request,
        Loan $loan,
        RenewalRequestRepository $renewalRequestRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $member = $this->getUser();
        if (!$member instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($loan->getMember()?->getId() !== $member->getId()) {
            throw $this->createAccessDeniedException('Accès interdit à cet emprunt.');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('member_loan_request_renewal_' . $loan->getId(), $token)) {
            $this->addFlash('error', 'Jeton CSRF invalide. Veuillez réessayer.');

            return $this->redirectToRoute('member_loans');
        }

        if ($loan->getReturnDate() instanceof \DateTimeInterface || $loan->getStatus() === LoanStatus::RETURNED) {
            $this->addFlash('error', 'Cet emprunt a déjà été retourné. Renouvellement impossible.');

            return $this->redirectToRoute('member_loans');
        }

        if (!$loan->canBeRenewed()) {
            $this->addFlash('error', 'Cet emprunt n\'est pas renouvelable (seuls les emprunts en cours ou en retard peuvent être renouvelés).');

            return $this->redirectToRoute('member_loans');
        }

        $maxRenewals = (int) $this->getParameter('max_renewals');
        if ($loan->maxRenewalsReached($maxRenewals)) {
            $this->addFlash('error', 'Vous avez atteint la limite de renouvellements.');

            return $this->redirectToRoute('member_loans');
        }

        $existing = $renewalRequestRepository->findPendingForLoanAndMember($loan, $member);
        if ($existing instanceof RenewalRequest) {
            $this->addFlash('info', 'Une demande de renouvellement est déjà en attente pour cet emprunt.');

            return $this->redirectToRoute('member_loans');
        }

        $renewalRequest = (new RenewalRequest())
            ->setLoan($loan)
            ->setMember($member)
            ->setStatus(RenewalRequest::STATUS_PENDING)
            ->setRequestedAt(new \DateTimeImmutable());

        $entityManager->persist($renewalRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande de renouvellement envoyée.');

        return $this->redirectToRoute('member_loans');
    }

    /**
     * @return array{
     *     loans: array,
     *     penalties: array,
     *     unpaidTotal: float,
     *     renewalDays: int,
     *     maxRenewals: int,
     *     loanRequestForm: mixed,
     *     activeTab: string
     * }
     */
    private function buildLoansPageData(
        User $member,
        LoanRepository $loanRepository,
        PenaltyRepository $penaltyRepository,
        mixed $loanRequestForm,
        string $activeTab,
    ): array {
        $loans = $loanRepository->findByMember($member);
        $penalties = $penaltyRepository->findByMember($member);

        $unpaidTotal = 0.0;
        foreach ($penalties as $penalty) {
            if ($penalty->isWaived()) {
                continue;
            }

            if (\in_array($penalty->getStatus()->value, ['unpaid', 'partial'], true)) {
                $unpaidTotal += $penalty->getAmount();
            }
        }

        return [
            'loans' => $loans,
            'penalties' => $penalties,
            'unpaidTotal' => round($unpaidTotal, 2),
            'renewalDays' => (int) $this->getParameter('renewal_days'),
            'maxRenewals' => (int) $this->getParameter('max_renewals'),
            'loanRequestForm' => $loanRequestForm,
            'activeTab' => $activeTab,
        ];
    }
}
