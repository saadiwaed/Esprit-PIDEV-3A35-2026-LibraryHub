<?php

namespace App\Controller;

use App\Repository\ClubRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;
class n8nApiController extends AbstractController
{
    // ============================================
    // API 1: STATISTIQUES QUOTIDIENNES
    // ============================================
    #[Route('/api/stats/daily', name: 'api_stats_daily')]
    public function dailyStats(
        UserRepository $userRepo,
        ClubRepository $clubRepo,
        EventRepository $eventRepo
    ): JsonResponse {
        
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        // Compter les nouveaux membres
        $newUsers = $userRepo->createQueryBuilder('u')
            ->where('u.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Compter les nouveaux clubs
        $newClubs = $clubRepo->createQueryBuilder('c')
            ->where('c.createdDate BETWEEN :start AND :end')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->select('count(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Compter les clubs actifs
        $activeClubs = $clubRepo->count(['status' => 'active']);
        
        // Compter les événements à venir
        $upcomingEvents = $eventRepo->createQueryBuilder('e')
            ->where('e.startDateTime > :now')
            ->setParameter('now', new \DateTime())
            ->select('count(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Compter les événements aujourd'hui
        $eventsToday = $eventRepo->createQueryBuilder('e')
            ->where('e.startDateTime BETWEEN :start AND :end')
            ->setParameter('start', $today)
            ->setParameter('end', $tomorrow)
            ->select('count(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        return $this->json([
            'date' => $today->format('Y-m-d'),
            'new_members' => $newUsers,
            'new_clubs' => $newClubs,
            'total_clubs' => $clubRepo->count([]),
            'active_clubs' => $activeClubs,
            'upcoming_events' => $upcomingEvents,
            'events_today' => $eventsToday
        ]);
    }

    // ============================================
    // API 2: NOUVEAUX CLUBS (24h)
    // ============================================
    #[Route('/api/clubs/new', name: 'api_clubs_new')]
    public function newClubs(ClubRepository $clubRepo): JsonResponse
    {
        $yesterday = new \DateTime('yesterday');
        $today = new \DateTime('today');
        
        $newClubs = $clubRepo->createQueryBuilder('c')
            ->where('c.createdDate BETWEEN :yesterday AND :today')
            ->setParameter('yesterday', $yesterday)
            ->setParameter('today', $today)
            ->orderBy('c.createdDate', 'DESC')
            ->getQuery()
            ->getResult();
        
        $data = [];
        foreach ($newClubs as $club) {
            $founder = $club->getFounder();
            $data[] = [
                'id' => $club->getId(),
                'title' => $club->getTitle(),
                'description' => substr($club->getDescription(), 0, 100) . '...',
                'category' => $club->getCategory(),
                'founder' => $founder ? $founder->getFullName() : 'Inconnu',
                'meeting_date' => $club->getMeetingDate() ? $club->getMeetingDate()->format('d/m/Y H:i') : 'Non définie',
                'meeting_location' => $club->getMeetingLocation(),
                'members_count' => $club->getMembers()->count(),
                'created_at' => $club->getCreatedDate()->format('d/m/Y H:i')
            ];
        }
        
        return $this->json([
            'date' => $today->format('Y-m-d'),
            'count' => count($data),
            'clubs' => $data
        ]);
    }

    // ============================================
    // API 3: ÉVÉNEMENTS À VENIR
    // ============================================
    #[Route('/api/events/upcoming', name: 'api_events_upcoming')]
    public function upcomingEvents(EventRepository $eventRepo): JsonResponse
    {
        $events = $eventRepo->createQueryBuilder('e')
            ->where('e.startDateTime > :now')
            ->andWhere('e.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'UPCOMING')
            ->orderBy('e.startDateTime', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
        
        $data = [];
        foreach ($events as $event) {
            $clubs = [];
            foreach ($event->getOrganizingClubs() as $club) {
                $clubs[] = $club->getTitle();
            }
            
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => substr($event->getDescription(), 0, 100) . '...',
                'type' => $event->getType()->value,
                'start' => $event->getStartDateTime()->format('d/m/Y H:i'),
                'end' => $event->getEndDateTime()->format('d/m/Y H:i'),
                'location' => $event->getLocation(),
                'clubs' => implode(', ', $clubs),
                'capacity' => $event->getCapacity(),
                'spots_left' => $event->getAvailableSpots(),
                'registered' => $event->getRegistrations()->count()
            ];
        }
        
        return $this->json([
            'count' => count($data),
            'events' => $data
        ]);
    }

#[Route('/api/save-report', name: 'api_save_report', methods: ['POST'])]
public function saveReport(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    if (!isset($data['html'])) {
        return $this->json(['error' => 'HTML manquant'], 400);
    }
    
    $html = $data['html'];
    $date = $data['date'] ?? date('Y-m-d H:i:s');
    
    // Sauvegarder dans un fichier
    $fs = new Filesystem();
    $reportDir = $this->getParameter('kernel.project_dir') . '/var/reports';
    
    if (!$fs->exists($reportDir)) {
        $fs->mkdir($reportDir);
    }
    
    // Sauvegarder la version du jour (écrasée à chaque fois - pour le dashboard)
    $todayFile = $reportDir . '/daily_report_latest.html';
    $fs->dumpFile($todayFile, $html);
    
    // ✅ SOLUTION: Ajouter l'heure pour garder TOUS les rapports
    $archiveFile = $reportDir . '/report_' . date('Y-m-d_H-i') . '.html';
    $fs->dumpFile($archiveFile, $html);
    
    return $this->json([
        'success' => true,
        'message' => 'Rapport sauvegardé',
        'file' => 'daily_report_latest.html'
    ]);
}

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/n8nDashboard.html.twig');
    }

    #[Route('/api/get-latest-report', name: 'api_get_report', methods: ['GET'])]
    public function getLatestReport(): JsonResponse
    {
        $reportFile = $this->getParameter('kernel.project_dir') . '/var/reports/daily_report_latest.html';
        
        if (!file_exists($reportFile)) {
            return $this->json(['html' => '<div class="alert alert-info">Aucun rapport disponible pour le moment. En attente du prochain cron...</div>']);
        }
        
        $html = file_get_contents($reportFile);
        
        return $this->json(['html' => $html]);
    }
}