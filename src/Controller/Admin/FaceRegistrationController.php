<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/face', name: 'admin_face_')]
final class FaceRegistrationController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['GET'])]
    public function registerPage(): Response
    {
        return $this->render('admin/face/register.html.twig');
    }

    #[Route('/register-image', name: 'register_image', methods: ['POST'])]
    public function registerFromImage(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->hasRole('ROLE_ADMIN')) {
            return new JsonResponse(['isSuccessful' => false, 'message' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $base64 = $data['image'] ?? null;

        if (!$base64) {
            return new JsonResponse(['isSuccessful' => false, 'message' => 'Image manquante.'], 400);
        }

        // Strip data URI prefix if present
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64, 2)[1];
        }

        $descriptor = $this->generateFaceDescriptor($base64);

        if (!$descriptor) {
            return new JsonResponse([
                'isSuccessful' => false,
                'message' => 'Aucun visage détecté ou erreur d\'encodage. Essayez dans un bon éclairage et regardez bien la caméra.'
            ], 400);
        }

        $user->setFaceDescriptor($descriptor);
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'isSuccessful' => true,
            'message' => 'Visage enregistré avec succès (512 dimensions).'
        ]);
    }

    private function generateFaceDescriptor(string $base64Image): ?string
    {
        $tmpDir = sys_get_temp_dir();
        $inputFile  = $tmpDir . DIRECTORY_SEPARATOR . 'face_capture_reg.png';
        $outputFile = $tmpDir . DIRECTORY_SEPARATOR . 'face_result_reg.txt';

        // Save image
        $imageBytes = base64_decode($base64Image, true);
        if ($imageBytes === false || strlen($imageBytes) < 100) {
            error_log("Face Register: Invalid base64 image");
            return null;
        }

        file_put_contents($inputFile, $imageBytes);

        // Use same script path logic as your working login
        $scriptPath = realpath(__DIR__ . '/../../../face_encode.py');
        if (!$scriptPath || !file_exists($scriptPath)) {
            error_log("Face Register: Script not found at " . $scriptPath);
            return null;
        }

        $pythonCmd = shell_exec('which python3 2>/dev/null') ? 'python3' : 'python';
        $cmd = $pythonCmd . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($inputFile) . ' ' . escapeshellarg($outputFile);

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $result = file_exists($outputFile) ? trim(file_get_contents($outputFile)) : '';

        // Cleanup
        @unlink($inputFile);
        @unlink($outputFile);

        // Debug
        error_log("=== FACE REGISTER DEBUG ===");
        error_log("Exit Code: " . $exitCode);
        error_log("Result: " . substr($result, 0, 200));

        if ($exitCode !== 0 || str_starts_with($result, 'ERROR') || empty($result)) {
            return null;
        }

        return $result;
    }
}