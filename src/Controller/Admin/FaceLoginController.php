<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\FaceRecognitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin/face-login', name: 'admin_face_login_')]
final class FaceLoginController extends AbstractController
{
    public function __construct(
        private FaceRecognitionService $faceRecognition,
        private TokenStorageInterface $tokenStorage,
    ) {}

    #[Route('', name: 'page', methods: ['GET'])]
    public function page(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_LIBRARIAN') ? 'app_home' : 'app_frontoffice');
        }

        return $this->render('admin/face/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    /**
     * Temporary debug endpoint — remove after confirming the request arrives correctly.
     * Test from browser console:
     *   const fd = new FormData(); fd.append('image','test');
     *   fetch('/admin/face-login/debug',{method:'POST',body:fd}).then(r=>r.json()).then(console.log)
     */
    #[Route('/debug', name: 'debug', methods: ['POST'])]
    public function debug(Request $request): JsonResponse
    {
        return new JsonResponse([
            'content_type'    => $request->headers->get('Content-Type'),
            'request_keys'    => array_keys($request->request->all()),
            'files_keys'      => array_keys($request->files->all()),
            'raw_body_length' => strlen($request->getContent()),
            'image_present'   => $request->request->has('image'),
            'image_length'    => strlen((string) $request->request->get('image')),
        ]);
    }

    #[Route('/recognize', name: 'recognize', methods: ['POST'])]
    public function recognize(Request $request, SessionInterface $session): JsonResponse
    {
        // ── 0. Unified body parsing (form-data, urlencoded, or JSON) ──────────
        $contentType = $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $body = json_decode($request->getContent(), true) ?? [];
        } else {
            $body = $request->request->all();
        }

        // ── 1. Obtain the 512-dim descriptor ──────────────────────────────────

        $probe = null;

        // Path A: descriptor already computed (JavaFX, etc.)
        if (!empty($body['descriptor'])) {
            $decoded = json_decode($body['descriptor'], true);
            if (!is_array($decoded) || count($decoded) !== 512) {
                return new JsonResponse([
                    'isSuccessful' => false,
                    'message'      => 'Descripteur invalide (attendu 512, reçu ' . (is_array($decoded) ? count($decoded) : 0) . ').',
                ], 400);
            }
            $probe = array_map('floatval', $decoded);
        }

        // Path B: browser sends base64 image
        if ($probe === null) {
            $imageB64 = $body['image'] ?? null;

            if (empty($imageB64)) {
                return new JsonResponse([
                    'isSuccessful' => false,
                    'message'      => 'Aucune donnée reçue. Clés reçues : [' . implode(', ', array_keys($body) ?: ['(aucune)']) . '].',
                ], 400);
            }

            // Strip optional data-URI prefix
            if (str_contains($imageB64, ',')) {
                $imageB64 = explode(',', $imageB64, 2)[1];
            }

            $imageBytes = base64_decode($imageB64, true);
            if ($imageBytes === false || strlen($imageBytes) < 100) {
                return new JsonResponse(['isSuccessful' => false, 'message' => 'Image base64 invalide.'], 400);
            }

            $tmpDir     = sys_get_temp_dir();
            $inputFile  = $tmpDir . DIRECTORY_SEPARATOR . 'face_capture.png';
            $outputFile = $tmpDir . DIRECTORY_SEPARATOR . 'face_result.txt';

            file_put_contents($inputFile, $imageBytes);

            $scriptPath = realpath(__DIR__ . '/../../../face_encode.py');
            if (!$scriptPath || !file_exists($scriptPath)) {
                return new JsonResponse([
                    'isSuccessful' => false,
                    'message'      => 'Script introuvable. Chemin cherché : ' . __DIR__ . '/../../../face_encode.py',
                ], 500);
            }

            // Try python3 first (modern systems), then fall back to python
            $pythonCmd = shell_exec('which python3 2>/dev/null') ? 'python3' : 'python';
            $cmd       = $pythonCmd . ' ' . escapeshellarg($scriptPath);
            $output    = [];
            $exitCode  = 0;
            
            // Capture stderr to aid debugging
            $fullOutput = shell_exec($cmd . ' 2>&1; echo "EXIT_CODE:$?"');
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($outputFile)) {
                // Log the error for debugging purposes
                error_log("Face encoding error: Exit code $exitCode, Output: " . implode("\n", $output));
                
                return new JsonResponse([
                    'isSuccessful' => false,
                    'message'      => 'Erreur Python (code sortie : ' . $exitCode . '). Vérifiez que insightface est installé.',
                ], 500);
            }

            $result = trim(file_get_contents($outputFile));

            if (str_starts_with($result, 'ERROR:')) {
                $detail = trim(substr($result, 6));
                $msg = match (true) {
                    str_contains($detail, 'No face')    => 'Aucun visage détecté dans l\'image.',
                    str_contains($detail, 'read image') => 'Impossible de lire l\'image.',
                    default                             => 'Erreur : ' . $detail,
                };
                return new JsonResponse(['isSuccessful' => false, 'message' => $msg], 422);
            }

            $floats = array_map('floatval', explode(',', $result));
            if (count($floats) !== 512) {
                return new JsonResponse([
                    'isSuccessful' => false,
                    'message'      => 'Embedding invalide : ' . count($floats) . ' valeurs (attendu 512).',
                ], 500);
            }

            $probe = $floats;
        }

        // ── 2. Match against stored admin embeddings ──────────────────────────

        $user = $this->faceRecognition->findMatchingAdmin($probe);

        if (!$user instanceof User) {
            return new JsonResponse([
                'isSuccessful' => false,
                'message'      => 'Aucun administrateur correspondant trouvé.',
            ], 404);
        }

        // ── 3. Authenticate and redirect to the admin dashboard ───────────────

        $session->remove('face_login_token');
        $session->remove('face_login_user_id');
        $securityToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($securityToken);
        $session->set('_security_main', serialize($securityToken));

        return new JsonResponse([
            'isSuccessful' => true,
            'message'      => 'Visage reconnu avec succès.',
            'email'        => $user->getEmail(),
            'redirectUrl'  => $this->generateUrl('app_home'),
        ]);
    }
}
