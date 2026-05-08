<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/live')]
final class LiveUpdateController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    #[Route('/fingerprint', name: 'app_live_fingerprint', methods: ['GET'])]
    public function fingerprint(): JsonResponse
    {
        $conn = $this->em->getConnection();

        // COUNT + MAX(id) detect inserts/deletes
        $clubCount = (int) $conn->fetchOne('SELECT COUNT(id) FROM clubs');
        $clubMaxId = (int) $conn->fetchOne('SELECT COALESCE(MAX(id), 0) FROM clubs');
        $evtCount  = (int) $conn->fetchOne('SELECT COUNT(id) FROM events');
        $evtMaxId  = (int) $conn->fetchOne('SELECT COALESCE(MAX(id), 0) FROM events');

        // UPDATE_TIME detects any modification (INSERT, UPDATE, DELETE)
        $clubUpdated = (string) $conn->fetchOne(
            "SELECT COALESCE(UPDATE_TIME, '') FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clubs'"
        );
        $evtUpdated = (string) $conn->fetchOne(
            "SELECT COALESCE(UPDATE_TIME, '') FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'"
        );

        $fingerprint = md5($clubCount . '|' . $clubMaxId . '|' . $clubUpdated
                         . '|' . $evtCount . '|' . $evtMaxId . '|' . $evtUpdated);

        $response = new JsonResponse(['fingerprint' => $fingerprint]);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }
}
