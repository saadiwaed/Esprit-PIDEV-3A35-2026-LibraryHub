<?php

namespace App\Controller;

use App\Entity\Loan;
use App\Form\LoanSearchType;
use App\Repository\LoanRepository;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class AdminLoanController extends AbstractController
{
    #[Route('/admin/loans', name: 'admin_loans', methods: ['GET'])]
    public function index(LoanRepository $loanRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $this->assertAdminOrLibrarian();

        $form = $this->createForm(LoanSearchType::class);
        $form->handleRequest($request);

        $filters = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData() ?? [];
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = max(5, min(100, $limit));

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

        return $this->render('admin/loans/index.html.twig', [
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

    #[Route('/admin/loans/export/pdf', name: 'admin_loans_export_pdf', methods: ['GET'])]
    public function exportPdf(LoanRepository $loanRepository, Request $request, LoggerInterface $logger): Response
    {
        $this->assertAdminOrLibrarian();

        $form = $this->createForm(LoanSearchType::class);
        $form->handleRequest($request);

        $filters = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $filters = $form->getData() ?? [];
        }

        $sortBy = $request->query->getString('sortBy', '');
        if ($sortBy === '') {
            $sortBy = $this->resolveLegacyLoanSort(
                $request->query->getString('sort', ''),
                $request->query->getString('direction', 'asc')
            );
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 20);
        $limit = max(5, min(200, $limit));

        $queryBuilder = $loanRepository->findByFiltersAndSort($filters, $sortBy !== '' ? $sortBy : null);
        $totalResults = $loanRepository->countByFilters($filters);

        $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        /** @var Loan[] $loans */
        $loans = $queryBuilder->getQuery()->getResult();

        $generatedAt = new \DateTimeImmutable();
        $fileName = sprintf('emprunts-%s.pdf', $generatedAt->format('Y-m-d'));
        $isRtl = $request->query->getBoolean('rtl', false) || $request->query->getString('dir', '') === 'rtl';

        $html = $this->renderView('admin/loans/pdf_list.html.twig', [
            'loans' => $loans,
            'generatedAt' => $generatedAt,
            'totalResults' => $totalResults,
            'exportedCount' => \count($loans),
            'page' => $page,
            'limit' => $limit,
            'isRtl' => $isRtl,
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

            if ($isRtl) {
                $mpdf->SetDirectionality('rtl');
                $mpdf->SetDefaultBodyCSS('direction', 'rtl');
                $mpdf->SetDefaultBodyCSS('text-align', 'right');
            }

            $headerHtml = sprintf(
                '<div style="font-family: dejavusans; font-size: 10px; color: #212529; border-bottom: 1px solid #dee2e6; padding-bottom: 6px;">%s</div>',
                htmlspecialchars(sprintf('LIBRARYHUB – Liste des emprunts – %s', $generatedAt->format('d/m/Y')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );

            $footerHtml = sprintf(
                '<table width="100%%" style="font-family: dejavusans; font-size: 10px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 6px;"><tr><td width="50%%">Total emprunts : %d</td><td width="50%%" align="right">Page {PAGENO} / {nbpg}</td></tr></table>',
                $totalResults
            );

            $mpdf->SetHTMLHeader($headerHtml);
            $mpdf->SetHTMLFooter($footerHtml);
            $mpdf->WriteHTML($html);

            $pdfContent = $mpdf->Output($fileName, 'S');
        } catch (\Throwable $e) {
            $logger->error('Admin loans PDF export failed.', [
                'exception' => $e,
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit,
            ]);

            $message = 'Impossible de générer le PDF pour le moment.';
            if ((string) $this->getParameter('kernel.environment') === 'dev') {
                $message .= ' ' . $e->getMessage();
            }

            $this->addFlash('error', $message);

            return $this->redirectToRoute('admin_loans', $request->query->all());
        }

        $response = new Response($pdfContent);
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
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

    private function assertAdminOrLibrarian(): void
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN')) {
            return;
        }

        throw $this->createAccessDeniedException('Accès réservé aux administrateurs/bibliothécaires.');
    }
}
