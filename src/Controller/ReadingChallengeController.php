<?php

namespace App\Controller;

use App\Entity\ChallengeParticipant;
use App\Form\ReadingChallengeType;
use App\Repository\ReadingChallengeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reading/challenge')]
final class ReadingChallengeController extends AbstractController
{
    #[Route(name: 'app_reading_challenge_index', methods: ['GET'])]
    public function index(ReadingChallengeRepository $readingChallengeRepository): Response
    {
        return $this->render('reading_challenge/index.html.twig', [
            'reading_challenges' => $readingChallengeRepository->findForIndex(),
        ]);
    }

    #[Route('/new', name: 'app_reading_challenge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $readingChallenge = new ChallengeParticipant();
        $form = $this->createForm(ReadingChallengeType::class, $readingChallenge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($readingChallenge);
            $entityManager->flush();

            return $this->redirectToRoute('app_reading_challenge_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reading_challenge/new.html.twig', [
            'reading_challenge' => $readingChallenge,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reading_challenge_show', methods: ['GET'])]
    public function show(ChallengeParticipant $readingChallenge): Response
    {
        return $this->render('reading_challenge/show.html.twig', [
            'reading_challenge' => $readingChallenge,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reading_challenge_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ChallengeParticipant $readingChallenge, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReadingChallengeType::class, $readingChallenge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_reading_challenge_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reading_challenge/edit.html.twig', [
            'reading_challenge' => $readingChallenge,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reading_challenge_delete', methods: ['POST'])]
    public function delete(Request $request, ChallengeParticipant $readingChallenge, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$readingChallenge->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($readingChallenge);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_reading_challenge_index', [], Response::HTTP_SEE_OTHER);
    }
}
