<?php

namespace App\Controller;

use App\Entity\Penalty;
use App\Entity\User;
use App\Form\PenaltyType;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Service\AIPenaltySuggester;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, LoggerInterface $logger): Response
    {
        $this->assertAdminOrLibrarian();

        $sortBy = $request->query->getString('sortBy', '');
        if ($sortBy === '') {
            $sortBy = $this->resolveLegacyPenaltySort(
                $request->query->getString('sort', ''),
                $request->query->getString('direction', 'asc')
            );
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = max(5, min(200, $limit));

        $queryBuilder = $this->penaltyRepository->findByFiltersAndSort([], $sortBy !== '' ? $sortBy : null);

        $countQb = clone $queryBuilder;
        $totalResults = (int) $countQb
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy')
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        /** @var Penalty[] $penalties */
        $penalties = $queryBuilder->getQuery()->getResult();

        $generatedAt = new \DateTimeImmutable();
        $fileName = sprintf('penalties-%s.pdf', $generatedAt->format('Y-m-d'));

        $html = $this->renderView('admin/penalties/pdf_list.html.twig', [
            'penalties' => $penalties,
            'generatedAt' => $generatedAt,
            'totalResults' => $totalResults,
            'exportedCount' => \count($penalties),
            'page' => $page,
            'limit' => $limit,
        ]);

        $tempDir = rtrim((string) $this->getParameter('kernel.cache_dir'), "\\/") . DIRECTORY_SEPARATOR . 'mpdf';
        if (!is_dir($tempDir) && @mkdir($tempDir, 0775, true) === false && !is_dir($tempDir)) {
            throw new \RuntimeException('Impossible de créer le dossier temporaire PDF.');
        }

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'dejavusans',
                'autoLangToFont' => true,
                'autoScriptToLang' => true,
                'tempDir' => $tempDir,
            ]);

            $headerHtml = sprintf(
                '<div style="font-family: dejavusans; font-size: 10px; color: #212529; border-bottom: 1px solid #dee2e6; padding-bottom: 6px;">%s</div>',
                htmlspecialchars(sprintf('LIBRARYHUB – Liste des pénalités – %s', $generatedAt->format('d/m/Y')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );

            $footerHtml = sprintf(
                '<table width="100%%" style="font-family: dejavusans; font-size: 10px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 6px;"><tr><td width="50%%">Total pénalités : %d</td><td width="50%%" align="right">Page {PAGENO} / {nbpg}</td></tr></table>',
                $totalResults
            );

            $mpdf->SetHTMLHeader($headerHtml);
            $mpdf->SetHTMLFooter($footerHtml);
            $mpdf->WriteHTML($html);

            $pdfContent = $mpdf->Output($fileName, 'S');
        } catch (\Throwable $e) {
            $logger->error('Admin penalties PDF export failed.', [
                'exception' => $e,
                'page' => $page,
                'limit' => $limit,
                'sortBy' => $sortBy,
            ]);

            $message = 'Impossible de générer le PDF pour le moment.';
            if ((string) $this->getParameter('kernel.environment') === 'dev') {
                $message .= ' ' . $e->getMessage();
            }

            $this->addFlash('error', $message);

            return $this->redirectToRoute('penalty_index', $request->query->all());
        }

        $response = new Response($pdfContent);
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
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

    #[Route('/ai-suggestion', name: 'ai_suggestion', methods: ['GET'])]
    public function aiSuggestion(
        Request $request,
        LoanRepository $loanRepository,
        AIPenaltySuggester $aiPenaltySuggester,
    ): JsonResponse {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_LIBRARIAN')) {
            throw $this->createAccessDeniedException('Accès réservé aux administrateurs/bibliothécaires.');
        }

        $loanId = (int) $request->query->get('loanId', 0);
        if ($loanId <= 0) {
            return new JsonResponse($aiPenaltySuggester->fallbackSuggestion(0));
        }

        $loan = $loanRepository->find($loanId);
        if ($loan === null) {
            return new JsonResponse($aiPenaltySuggester->fallbackSuggestion(0));
        }

        $daysLate = (int) $loan->getDaysLate();
        $memberHistory = $this->buildMemberHistory($loanRepository, $loan->getMember());

        $suggestion = $aiPenaltySuggester->suggestPenaltyForLoanId($loanId, $daysLate, $memberHistory);

        return new JsonResponse($suggestion);
    }

    /**
     * @return array<string, int>
     */
    private function buildMemberHistory(LoanRepository $loanRepository, ?User $member): array
    {
        if (!$member instanceof User || $member->getId() === null) {
            return [
                'total_loans' => 0,
                'overdue_returns_total' => 0,
                'overdue_returns_this_quarter' => 0,
                'total_late_days' => 0,
            ];
        }

        $totalLoans = (int) $loanRepository->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.member = :member')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();

        $overdueTotal = (int) $loanRepository->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.member = :member')
            ->andWhere('l.returnDate IS NOT NULL')
            ->andWhere('l.dueDate IS NOT NULL')
            ->andWhere('l.returnDate > l.dueDate')
            ->setParameter('member', $member)
            ->getQuery()
            ->getSingleScalarResult();

        $quarterStart = $this->quarterStart(new \DateTimeImmutable('today'));
        $overdueThisQuarter = (int) $loanRepository->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.member = :member')
            ->andWhere('l.returnDate IS NOT NULL')
            ->andWhere('l.dueDate IS NOT NULL')
            ->andWhere('l.returnDate > l.dueDate')
            ->andWhere('l.returnDate >= :from')
            ->setParameter('member', $member)
            ->setParameter('from', $quarterStart)
            ->getQuery()
            ->getSingleScalarResult();

        $lateRows = $loanRepository->createQueryBuilder('l')
            ->select('l.dueDate, l.returnDate')
            ->andWhere('l.member = :member')
            ->andWhere('l.returnDate IS NOT NULL')
            ->andWhere('l.dueDate IS NOT NULL')
            ->andWhere('l.returnDate > l.dueDate')
            ->setParameter('member', $member)
            ->getQuery()
            ->getArrayResult();

        $totalLateDays = 0;
        foreach ($lateRows as $row) {
            if (!isset($row['dueDate'], $row['returnDate'])) {
                continue;
            }

            $due = $row['dueDate'] instanceof \DateTimeInterface ? $row['dueDate'] : null;
            $ret = $row['returnDate'] instanceof \DateTimeInterface ? $row['returnDate'] : null;
            if ($due === null || $ret === null) {
                continue;
            }

            $days = (int) \DateTimeImmutable::createFromInterface($due)
                ->setTime(0, 0, 0)
                ->diff(\DateTimeImmutable::createFromInterface($ret)->setTime(0, 0, 0))
                ->days;
            $totalLateDays += max(0, $days);
        }

        return [
            'total_loans' => $totalLoans,
            'overdue_returns_total' => $overdueTotal,
            'overdue_returns_this_quarter' => $overdueThisQuarter,
            'total_late_days' => $totalLateDays,
        ];
    }

    private function quarterStart(\DateTimeImmutable $today): \DateTimeImmutable
    {
        $month = (int) $today->format('n');
        $quarter = (int) floor(($month - 1) / 3) + 1;
        $startMonth = (($quarter - 1) * 3) + 1;

        return $today
            ->setDate((int) $today->format('Y'), $startMonth, 1)
            ->setTime(0, 0, 0);
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

    private function assertAdminOrLibrarian(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN')) {
            return;
        }

        throw $this->createAccessDeniedException('Accès réservé aux administrateurs/bibliothécaires.');
    }
}
