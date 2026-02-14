<?php

namespace App\Controller;

use App\Entity\LoanRequest;
use App\Enum\LoanRequestStatus;
use App\Repository\LoanRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class AdminLoanRequestController extends AbstractController
{
    #[Route('/admin/demandes-emprunt', name: 'admin_loan_request_pending', methods: ['GET'])]
    public function pending(LoanRequestRepository $loanRequestRepository): Response
    {
        return $this->render('admin/loan_request_pending.html.twig', [
            'loanRequests' => $loanRequestRepository->findPending(100),
        ]);
    }

    #[Route('/admin/demandes-emprunt/{id}/approuver', name: 'admin_loan_request_approve', methods: ['POST'])]
    public function approve(
        LoanRequest $loanRequest,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('approve' . $loanRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_loan_request_pending');
        }

        if ($loanRequest->getStatus() !== LoanRequestStatus::PENDING) {
            $this->addFlash('warning', 'Cette demande nâ€™est plus en attente.');

            return $this->redirectToRoute('admin_loan_request_pending');
        }

        $loanRequest->setStatus(LoanRequestStatus::APPROVED);
        $entityManager->flush();

        $this->addFlash('success', 'Demande approuvee.');

        return $this->redirectToRoute('admin_loan_request_pending');
    }

    #[Route('/admin/demandes-emprunt/{id}/refuser', name: 'admin_loan_request_reject', methods: ['POST'])]
    public function reject(
        LoanRequest $loanRequest,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('reject' . $loanRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_loan_request_pending');
        }

        if ($loanRequest->getStatus() !== LoanRequestStatus::PENDING) {
            $this->addFlash('warning', 'Cette demande nâ€™est plus en attente.');

            return $this->redirectToRoute('admin_loan_request_pending');
        }

        $loanRequest->setStatus(LoanRequestStatus::REJECTED);
        $entityManager->flush();

        $this->addFlash('success', 'Demande refusee.');

        return $this->redirectToRoute('admin_loan_request_pending');
    }
}

