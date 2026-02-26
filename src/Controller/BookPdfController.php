<?php

namespace App\Controller;

use App\Entity\Book;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookPdfController extends AbstractController
{
    #[Route('/book/{id}/pdf', name: 'book_pdf', methods:['GET'])]
    public function generate(Book $book): Response
    {
        // Dompdf configuration
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // Arabic support
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);

        // render twig into HTML
        $html = $this->renderView('pdf/book_pdf.html.twig', [
            'book' => $book,
        ]);

        $dompdf->loadHtml($html);

        // A4 portrait
        $dompdf->setPaper('A4', 'portrait');

        // generate
        $dompdf->render();

        // download
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="book_'.$book->getId().'.pdf"'
            ]
        );
    }
}