<?php

namespace App\Controller;

use App\Entity\Penalty;
use App\Form\PenaltyType;
use App\Repository\PenaltyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/penalty', name: 'penalty_')]
class PenaltyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PenaltyRepository $penaltyRepository,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $sortBy = $request->query->getString('sortBy', '');
        if ($sortBy === '') {
            $sortBy = $this->resolveLegacyPenaltySort(
                $request->query->getString('sort', ''),
                $request->query->getString('direction', 'asc')
            );
        }

        $queryBuilder = $this->penaltyRepository->findByFiltersAndSort([], $sortBy !== '' ? $sortBy : null);

        $penalties = $paginator->paginate($queryBuilder, $page, $limit, [
            'distinct' => true,
            'pageParameterName' => 'page',
        ]);

        $totalResults = $penalties->getTotalItemCount();
        $maxPage = (int) ceil(max(1, $totalResults) / $limit);

        return $this->render('penalty/index.html.twig', [
            'penalties' => $penalties,
            'totalResults' => $totalResults,
            'currentPage' => $page,
            'maxPage' => $maxPage,
            'sortBy' => $sortBy,
            'sort' => $request->query->getString('sort', ''),
            'direction' => $request->query->getString('direction', 'asc'),
        ]);
    }

    private function resolveLegacyPenaltySort(string $sort, string $direction): string
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return match (sprintf('%s_%s', $sort, $direction)) {
            'member_asc' => PenaltyRepository::SORT_MEMBER_NAME_ASC,
            'amount_desc' => PenaltyRepository::SORT_AMOUNT_DESC,
            'issueDate_desc' => PenaltyRepository::SORT_ISSUE_DATE_DESC,
            'status_asc' => PenaltyRepository::SORT_STATUS_PRIORITY,
            'waived_asc' => PenaltyRepository::SORT_WAIVED_LAST,
            default => '',
        };
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $penalty = new Penalty();
        $form = $this->createForm(PenaltyType::class, $penalty);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($penalty);
            $this->entityManager->flush();

            $this->addFlash('success', 'Penalty created successfully.');

            return $this->redirectToRoute('penalty_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('penalty/new.html.twig', [
            'penalty' => $penalty,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Penalty $penalty): Response
    {
        return $this->render('penalty/show.html.twig', [
            'penalty' => $penalty,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Penalty $penalty): Response
    {
        $form = $this->createForm(PenaltyType::class, $penalty);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Penalty updated successfully.');

            return $this->redirectToRoute('penalty_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('penalty/edit.html.twig', [
            'penalty' => $penalty,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Penalty $penalty): Response
    {
        if ($this->isCsrfTokenValid('delete' . $penalty->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($penalty);
            $this->entityManager->flush();

            $this->addFlash('success', 'Penalty deleted successfully.');
        }

        return $this->redirectToRoute('penalty_index', [], Response::HTTP_SEE_OTHER);
    }
}
