<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;

class UploadController
{
    #[Route('/uploads/books/{filename}', name: 'app_book_cover')]
    public function bookCover(string $filename): BinaryFileResponse
    {
        return new BinaryFileResponse(
            __DIR__.'/../../public/uploads/books/'.$filename
        );
    }
}
