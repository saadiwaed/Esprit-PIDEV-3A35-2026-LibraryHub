<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private string $uploadsDirectory;

    public function __construct(string $uploadsDirectory)
    {
        $this->uploadsDirectory = $uploadsDirectory;
    }

    /**
     * Enregistre le fichier uploadé et retourne le chemin relatif (ex: uploads/categories/xxx.jpg).
     */
    public function upload(UploadedFile $file, string $subdir): string
    {
        $safeName = bin2hex(random_bytes(8)) . '.' . ($file->guessExtension() ?: 'bin');
        $targetDir = $this->uploadsDirectory . '/uploads/' . $subdir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        try {
            $file->move($targetDir, $safeName);
        } catch (FileException $e) {
            throw $e;
        }
        return 'uploads/' . $subdir . '/' . $safeName;
    }
}
