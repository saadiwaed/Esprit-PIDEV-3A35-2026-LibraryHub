<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TranslateController extends AbstractController
{
    #[Route('/api/translate', name:'api_translate', methods:['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $text = trim($data['text'] ?? '');
        $to   = strtolower($data['to'] ?? 'fr');

        if(!$text){
            return new JsonResponse(['translated'=>'(vide)']);
        }

        // ---------- SOURCE LANGUAGE DETECTION ----------
        // detect arabic
        if(preg_match('/[\x{0600}-\x{06FF}]/u', $text)){
            $from = 'ar';
        }else{
            // assume french for your library
            $from = 'fr';
        }

        // same language → no API call
        if($from === $to){
            return new JsonResponse([
                'translated'=>$text
            ]);
        }

        $client = HttpClient::create();

        try{

            // IMPORTANT: DO NOT urlencode
            $response = $client->request('GET', 'https://api.mymemory.translated.net/get', [
                'query' => [
                    'q' => $text,
                    'langpair' => "{$from}|{$to}"
                ],
                'timeout' => 15
            ]);

            $result = $response->toArray(false);

            // validate response
            if(
                !isset($result['responseStatus']) ||
                $result['responseStatus'] != 200 ||
                empty($result['responseData']['translatedText'])
            ){
                return new JsonResponse(['translated'=>$text]);
            }

            $translated = html_entity_decode(
                $result['responseData']['translatedText'],
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            return new JsonResponse([
                'translated'=>$translated
            ]);

        }catch(\Exception $e){

            // fallback
            return new JsonResponse([
                'translated'=>$text
            ]);
        }
    }
}