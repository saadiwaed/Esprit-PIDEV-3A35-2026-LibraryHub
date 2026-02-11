<?php

namespace App\Controller;

use App\Entity\ReadingProfile;
use App\Form\ReadingProfileType;
use App\Repository\ReadingProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reading-profile')]
class ReadingProfileController extends AbstractController
{
    #[Route('/', name: 'app_reading_profile_index', methods: ['GET'])]
    public function index(Request $request, ReadingProfileRepository $readingProfileRepository): Response
    {
        $search = $request->query->get('q', '');
        $booksRead = $request->query->get('booksRead', '');
        $hasGoal = $request->query->get('hasGoal', '');
        
        // Use filters
        $readingProfiles = $readingProfileRepository->findWithFilters(
            $search ?: null,
            $booksRead ?: null,
            $hasGoal ?: null
        );
        
        return $this->render('reading_profile/index.html.twig', [
            'reading_profiles' => $readingProfiles,
            'search' => $search,
            'currentBooksRead' => $booksRead,
            'currentHasGoal' => $hasGoal,
        ]);
    }

    #[Route('/search', name: 'app_reading_profile_search', methods: ['GET'])]
    public function search(Request $request, ReadingProfileRepository $readingProfileRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        
        $profiles = $readingProfileRepository->searchByUserNameOrEmail($query, 10);
        
        $results = [];
        foreach ($profiles as $profile) {
            $results[] = [
                'id' => $profile->getId(),
                'text' => $profile->getUser()->getFullName() . ' (' . $profile->getUser()->getEmail() . ')',
                'name' => $profile->getUser()->getFullName(),
                'email' => $profile->getUser()->getEmail(),
            ];
        }
        
        return $this->json($results);
    }

    #[Route('/new', name: 'app_reading_profile_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $readingProfile = new ReadingProfile();
        $form = $this->createForm(ReadingProfileType::class, $readingProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($readingProfile);
            $entityManager->flush();

            $this->addFlash('success', 'Reading profile created successfully!');
            return $this->redirectToRoute('app_reading_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reading_profile/new.html.twig', [
            'reading_profile' => $readingProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reading_profile_show', methods: ['GET'])]
    public function show(ReadingProfile $readingProfile): Response
    {
        return $this->render('reading_profile/show.html.twig', [
            'reading_profile' => $readingProfile,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reading_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ReadingProfile $readingProfile, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReadingProfileType::class, $readingProfile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Reading profile updated successfully!');
            return $this->redirectToRoute('app_reading_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reading_profile/edit.html.twig', [
            'reading_profile' => $readingProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reading_profile_delete', methods: ['POST'])]
    public function delete(Request $request, ReadingProfile $readingProfile, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $readingProfile->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($readingProfile);
            $entityManager->flush();

            $this->addFlash('success', 'Reading profile deleted successfully!');
        }

        return $this->redirectToRoute('app_reading_profile_index', [], Response::HTTP_SEE_OTHER);
    }
}
