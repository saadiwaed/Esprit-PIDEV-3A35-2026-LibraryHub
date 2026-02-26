<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AiBookController extends AbstractController
{
   
    #[Route('/ai/recommend', name:'ai_recommend', methods:['POST'])]
    public function recommend(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
    
        if(!isset($data['prompt']) || trim($data['prompt']) === ''){
            return new JsonResponse(['books'=>[]]);
        }
    
        $client = HttpClient::create([
            'timeout' => 60
        ]);
    
        try {
    
            $response = $client->request('POST', 'http://127.0.0.1:8001/recommend', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'text' => $data['prompt']
                ])
            ]);
    
            $result = $response->toArray(false);
    
            return new JsonResponse($result);
    
        } catch (\Exception $e) {
    
            return new JsonResponse([
                'error' => 'AI server unreachable',
                'details' => $e->getMessage()
            ], 500);
    
        }
    }

}
