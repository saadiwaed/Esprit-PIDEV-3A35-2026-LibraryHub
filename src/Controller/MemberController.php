<?php

namespace App\Controller;

use App\Entity\LoanRequest;
use App\Entity\User;
use App\Enum\LoanStatus;
use App\Enum\LoanRequestStatus;
use App\Form\LoanRequestType;
use App\Repository\BookRepository;
use App\Repository\LoanRepository;
use App\Repository\LoanRequestRepository;
use App\Repository\PenaltyRepository;
use App\Service\LoanService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MemberController extends AbstractController
{
    #[Route('/accueil', name: 'member_home', methods: ['GET'])]
    #[Route('/espace-membre', name: 'member_space', methods: ['GET'])]
    public function memberSpace(LoanRequestRepository $loanRequestRepository): Response
    {
        $user = $this->getUser();
        $loanRequests = $user instanceof User ? $loanRequestRepository->findLatestForMember($user, 5) : [];

        $loanRequest = (new LoanRequest())
            ->setDesiredLoanDate(new \DateTimeImmutable('today'))
            ->setDesiredReturnDate((new \DateTimeImmutable('today'))->modify('+7 days'));

        $form = $this->createForm(LoanRequestType::class, $loanRequest, [
            'action' => $this->generateUrl('member_loan_request_submit'),
            'method' => 'POST',
        ]);

        return $this->render('member/home.html.twig', [
            'loanRequests' => $loanRequests,
            'loanRequestForm' => $form->createView(),
        ]);
    }

    #[Route('/mes-emprunts', name: 'member_my_loans', methods: ['GET'])]
    public function myLoans(LoanRepository $loanRepository, LoanService $loanService): Response
    {
        $user = $this->getUser();
        $loans = $user instanceof User ? $loanRepository->findByMember($user->getId()) : [];
        $openLoans = [];
        $historyLoans = [];

        foreach ($loans as $loan) {
            if ($loan->getReturnDate() instanceof \DateTimeInterface) {
                $historyLoans[] = $loan;
            } else {
                $openLoans[] = $loan;
            }
        }

        return $this->render('member/loans.html.twig', [
            'openLoans' => $openLoans,
            'historyLoans' => $historyLoans,
            'maxRenewals' => $loanService->getMaxRenewals(),
        ]);
    }

    #[Route('/renouvellements/demander', name: 'member_renewal_request', methods: ['GET'])]
    public function renewalRequestEntry(): Response
    {
        return $this->redirectToRoute('member_my_loans', [
            '_fragment' => 'renouvellements',
        ]);
    }

    #[Route('/loans/{id<\\d+>}/request-renewal', name: 'member_loan_request_renewal', methods: ['POST'])]
    public function requestRenewal(
        Request $request,
        int $id,
        LoanRepository $loanRepository,
        LoanService $loanService
    ): Response {
        $loan = $loanRepository->find($id);
        if ($loan === null) {
            $this->addFlash('error', 'Emprunt introuvable.');

            return $this->redirectToRoute('member_my_loans');
        }

        $user = $this->getUser();
        if ($user instanceof User && $loan->getMember()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        if ($loan->getStatus() !== LoanStatus::ACTIVE) {
            $this->addFlash('error', 'Seuls les emprunts actifs peuvent être renouvelés.');

            return $this->redirectToRoute('member_my_loans');
        }

        if ($loan->getRenewalCount() >= $loanService->getMaxRenewals()) {
            $this->addFlash('error', 'Limite de renouvellements atteinte.');

            return $this->redirectToRoute('member_my_loans');
        }

        if (!$this->isCsrfTokenValid('request_renewal' . $loan->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('member_my_loans');
        }

        try {
            $renewal = $loanService->renewLoan($loan);
            $this->addFlash('success', sprintf(
                'Renouvellement enregistré avec succès (n° %d). Nouvelle date limite : %s.',
                $renewal->getRenewalNumber(),
                $renewal->getNewDueDate()?->format('d/m/Y')
            ));
        } catch (\LogicException|\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('member_my_loans', [
            '_fragment' => 'renouvellements',
        ]);
    }

    #[Route('/mes-penalites', name: 'member_my_penalties', methods: ['GET'])]
    public function myPenalties(PenaltyRepository $penaltyRepository): Response
    {
        $user = $this->getUser();
        $penalties = $user instanceof User ? $penaltyRepository->findForMember($user) : [];

        return $this->render('member/penalties.html.twig', [
            'penalties' => $penalties,
        ]);
    }

    #[Route('/emprunts/demander', name: 'member_loan_request', methods: ['GET'])]
    public function requestLoan(): Response
    {
        $loanRequest = (new LoanRequest())
            ->setDesiredLoanDate(new \DateTimeImmutable('today'))
            ->setDesiredReturnDate((new \DateTimeImmutable('today'))->modify('+7 days'));

        $form = $this->createForm(LoanRequestType::class, $loanRequest, [
            'action' => $this->generateUrl('member_loan_request_submit'),
            'method' => 'POST',
        ]);

        return $this->render('member/loan_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/emprunts/demander', name: 'member_request_loan', methods: ['POST'])]
    #[Route('/emprunts/demander', name: 'member_loan_request_submit', methods: ['POST'])]
    public function requestLoanSubmit(
        Request $request,
        BookRepository $bookRepository,
        LoanRequestRepository $loanRequestRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        $member = $user instanceof User ? $user : null;

        $loanRequest = new LoanRequest();
        $form = $this->createForm(LoanRequestType::class, $loanRequest, [
            'action' => $this->generateUrl('member_loan_request_submit'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $loanRequests = $member instanceof User ? $loanRequestRepository->findLatestForMember($member, 5) : [];

            return $this->render('member/home.html.twig', [
                'loanRequests' => $loanRequests,
                'loanRequestForm' => $form->createView(),
                'openLoanRequestModal' => true,
            ]);
        }

        $desiredLoanDate = $loanRequest->getDesiredLoanDate();
        $desiredReturnDate = $loanRequest->getDesiredReturnDate();
        if ($desiredLoanDate instanceof \DateTimeImmutable && $desiredReturnDate instanceof \DateTimeImmutable) {
            if ($desiredReturnDate <= $desiredLoanDate) {
                $form->get('desiredReturnDate')->addError(new FormError('La date de retour doit être postérieure à la date d’emprunt souhaitée.'));
            }
        }

        $bookId = (int) $form->get('bookId')->getData();
        $book = $bookRepository->find($bookId);
        if ($book === null) {
            $form->get('bookId')->addError(new FormError("L'ID du livre est invalide."));
        }

        if (!$form->isValid()) {
            $loanRequests = $member instanceof User ? $loanRequestRepository->findLatestForMember($member, 5) : [];

            return $this->render('member/home.html.twig', [
                'loanRequests' => $loanRequests,
                'loanRequestForm' => $form->createView(),
                'openLoanRequestModal' => true,
            ]);
        }

        $loanRequest
            ->setMember($member)
            ->setBook($book)
            ->setRequestedAt(new \DateTimeImmutable())
            ->setStatus(LoanRequestStatus::PENDING);

        $entityManager->persist($loanRequest);
        $entityManager->flush();

        $this->addFlash('success', 'Demande envoyée ! Vous serez informé dès que possible.');

        return $this->redirectToRoute('member_home', [], Response::HTTP_SEE_OTHER);
    }
}
