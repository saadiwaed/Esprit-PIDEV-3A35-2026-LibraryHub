<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    public function __construct(
        private string $attachmentsDirectory,
        private SluggerInterface $slugger
    ) {
    }

    /**
     * Upload un fichier et retourne le nouveau nom de fichier
     */
    public function upload(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = uniqid() . '-' . $safeFilename . '.' . $file->guessExtension();

        try {
            $file->move($this->attachmentsDirectory, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }

        return $newFilename;
    }

    /**
     * Supprime un fichier du disque
     */
    public function delete(string $filename): void
    {
        $filePath = $this->attachmentsDirectory . '/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Retourne le chemin complet d'un fichier
     */
    public function getFullPath(string $filename): string
    {
        return $this->attachmentsDirectory . '/' . $filename;
    }
}