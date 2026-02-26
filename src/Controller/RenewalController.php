<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Entity\Renewal;
use App\Exception\LoanAlreadyReturnedException;
use App\Exception\MaxRenewalsReachedException;
use App\Form\RenewalType;
use App\Service\RenewalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/loans', name: 'loan_')]
class RenewalController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RenewalService $renewalService,
    ) {
    }

    #[Route('/renewals', name: 'renewal_index', methods: ['GET'])]
    public function index(): Response
    {
        $renewals = $this->entityManager
            ->getRepository(Renewal::class)
            ->findBy([], ['renewedAt' => 'DESC']);

        return $this->render('renewal/index.html.twig', [
            'renewals' => $renewals,
        ]);
    }

    #[Route('/{id}/renew', name: 'renew', methods: ['GET', 'POST'])]
    public function renew(Request $request, Loan $loan): Response
    {
        if ($loan->getRenewalCount() >= 3) {
            $this->addFlash('error', 'Limite de renouvellements atteinte');

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
        }
        if (!$loan->getDueDate() instanceof \DateTimeInterface) {
            $this->addFlash('error', 'La date limite de cet emprunt est absente. Renouvellement impossible.');

            return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
        }

        $renewal = (new Renewal())
            ->setLoan($loan)
            ->setPreviousDueDate($loan->getDueDate())
            ->setNewDueDate(
                \DateTimeImmutable::createFromInterface($loan->getDueDate())
                    ->modify(sprintf('+%d days', $this->renewalService->getRenewalDays()))
            );

        $form = $this->createForm(RenewalType::class, $renewal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $chosenDueDate = $renewal->getNewDueDate();
                if (!$chosenDueDate instanceof \DateTimeInterface) {
                    throw new \InvalidArgumentException('La nouvelle date limite est obligatoire.');
                }

                $renewalRecord = $this->renewalService->renewLoan(
                    $loan,
                    \DateTimeImmutable::createFromInterface($chosenDueDate)
                );

                $this->addFlash('success', sprintf(
                    'Emprunt renouvele (renouvellement n° %d) jusqu\'au %s.',
                    $renewalRecord->getLoan()?->getRenewalCount() ?? $renewalRecord->getRenewalNumber(),
                    $renewalRecord->getNewDueDate()?->format('d/m/Y')
                ));

                return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
            } catch (LoanAlreadyReturnedException|MaxRenewalsReachedException|\LogicException|\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('loan_show', ['id' => $loan->getId()]);
            }
        }

        return $this->render('loan/renew.html.twig', [
            'loan' => $loan,
            'renewal' => $renewal,
            'form' => $form,
        ]);
    }
}

