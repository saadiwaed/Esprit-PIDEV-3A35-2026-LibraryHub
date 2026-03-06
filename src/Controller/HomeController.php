<?php

namespace App\Controller;

use App\Service\PerformanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/dashboard', name: 'app_home')]
    public function index(PerformanceService $performance): Response
    {
        $performance->start('home_page');

        $response = $this->render('base.html.twig', [
            'controller_name' => 'HomeController',
        ]);

        $metrics = $performance->stop('home_page');
        $response->headers->set('X-Perf-Duration-Ms', (string) $metrics['duration_ms']);
        $response->headers->set('X-Perf-Memory-Delta-Bytes', (string) $metrics['memory_delta_bytes']);
        $response->headers->set('X-Perf-Peak-Memory-Bytes', (string) $metrics['peak_memory_bytes']);

        return $response;
    }

    #[Route('/perf-test', name: 'app_perf_test', methods: ['GET'])]
    public function perfTest(PerformanceService $performance): Response
    {
        $measured = $performance->measure('perf_test', static function (): array {
            return ['status' => 'ok'];
        });

        $metrics = $measured['metrics'];
        $response = $this->json([
            'duration_ms' => $metrics['duration_ms'],
            'memory_delta_bytes' => $metrics['memory_delta_bytes'],
            'peak_memory_bytes' => $metrics['peak_memory_bytes'],
        ]);

        $response->headers->set('X-Perf-Duration-Ms', (string) $metrics['duration_ms']);
        $response->headers->set('X-Perf-Memory-Delta-Bytes', (string) $metrics['memory_delta_bytes']);
        $response->headers->set('X-Perf-Peak-Memory-Bytes', (string) $metrics['peak_memory_bytes']);

        return $response;
    }

    #[Route('/perf-report', name: 'app_perf_report', methods: ['GET'])]
    public function perfReport(PerformanceService $performance, HttpKernelInterface $httpKernel): Response
    {
        $homeMetrics = $performance->measureAverage('home_route', 10, static function () use ($httpKernel): void {
            $request = Request::create('/login', 'GET');
            $httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
        });

        $penaltyMetrics = $performance->measureCommandAverage(
            'penalty_command',
            [PHP_BINARY, 'bin/console', 'app:generate-overdue-penalties', '--no-interaction'],
            3,
            $this->getParameter('kernel.project_dir')
        );

        return $this->json([
            'home_page_avg_response_ms' => $homeMetrics['avg_duration_ms'],
            'penalties_feature_avg_execution_ms' => $penaltyMetrics['avg_duration_ms'],
            'memory_usage' => [
                'avg_memory_delta_bytes' => $homeMetrics['avg_memory_delta_bytes'],
                'avg_peak_memory_bytes' => $homeMetrics['avg_peak_memory_bytes'],
            ],
            'details' => [
                'home' => $homeMetrics,
                'penalties' => $penaltyMetrics,
            ],
        ]);
    }
}
