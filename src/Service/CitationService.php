<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CitationService
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Récupère une citation aléatoire - ZENQUOTES ✅
     */
    public function getCitationAleatoire(): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://zenquotes.io/api/random');
            $data = $response->toArray();
            
            return [
                'contenu' => $data[0]['q'],
                'auteur' => $data[0]['a'],
                'succes' => true
            ];
        } catch (\Exception $e) {
            return [
                'contenu' => 'La lecture est une amitié qui ne déçoit jamais.',
                'auteur' => 'Marcel Proust',
                'succes' => false
            ];
        }
    }

    /**
     * Récupère une citation sur le thème de la lecture - ZENQUOTES ✅
     */
    public function getCitationLecture(): array
    {
        try {
            // ✅ MAINTENANT AVEC ZENQUOTES !
            $response = $this->httpClient->request('GET', 'https://zenquotes.io/api/random');
            $data = $response->toArray();
            
            return [
                'contenu' => $data[0]['q'],
                'auteur' => $data[0]['a'],
                'succes' => true
            ];
        } catch (\Exception $e) {
            return $this->getCitationAleatoire();
        }
    }

    /**
     * Récupère une citation de motivation - ZENQUOTES ✅
     */
    public function getCitationMotivation(): array
    {
        try {
            // ✅ MAINTENANT AVEC ZENQUOTES !
            $response = $this->httpClient->request('GET', 'https://zenquotes.io/api/random');
            $data = $response->toArray();
            
            return [
                'contenu' => $data[0]['q'],
                'auteur' => $data[0]['a'],
                'succes' => true
            ];
        } catch (\Exception $e) {
            return $this->getCitationAleatoire();
        }
    }
}