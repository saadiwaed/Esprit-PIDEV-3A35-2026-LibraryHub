<?php

namespace App\Controller;

use App\Entity\ReadingProfile;
use App\Form\ReadingProfileType;
use App\Repository\ReadingProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reading/profile')]
class ReadingProfileController extends AbstractController
{
    #[Route('/', name: 'app_reading_profile_index', methods: ['GET'])]
    public function index(Request $request, ReadingProfileRepository $readingProfileRepository): Response
    {
        $search = $request->query->get('search', '');
        
        if ($search) {
            $readingProfiles = $readingProfileRepository->searchByUserNameOrEmail($search);
        } else {
            $readingProfiles = $readingProfileRepository->findAll();
        }
        
        return $this->render('reading_profile/index.html.twig', [
            'reading_profiles' => $readingProfiles,
        ]);
    }

    #[Route('/search', name: 'app_reading_profile_search', methods: ['GET'])]
    public function search(Request $request, ReadingProfileRepository $readingProfileRepository): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json([]);
        }
        
        $profiles = $readingProfileRepository->searchByUserNameOrEmail($query);
        
        $results = array_map(function($profile) {
            return [
                'id' => $profile->getId(),
                'userName' => $profile->getUser()->getFullName(),
                'userEmail' => $profile->getUser()->getEmail(),
                'booksRead' => $profile->getTotalBooksRead(),
            ];
        }, $profiles);
        
        return $this->json($results);
    }

    #[Route('/new', name: 'app_reading_profile_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $readingProfile = new ReadingProfile();
        $form = $this->createForm(ReadingProfileType::class, $readingProfile, [
            'is_edit' => false,
            'current_user_id' => null
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($readingProfile);
            $entityManager->flush();

            $this->addFlash('success', 'Reading Profile created successfully!');

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
        $form = $this->createForm(ReadingProfileType::class, $readingProfile, [
            'is_edit' => true,
            'current_user_id' => $readingProfile->getUser()->getId()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Reading Profile updated successfully!');

            return $this->redirectToRoute('app_reading_profile_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reading_profile/edit.html.twig', [
            'reading_profile' => $readingProfile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_reading_profile_delete', methods: ['POST'])]
    public function delete(Request $request, ReadingProfile $readingProfile, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$readingProfile->getId(), $request->request->get('_token'))) {
            try {
                // Get the user and clear the relationship before deleting
                $user = $readingProfile->getUser();
                if ($user) {
                    $user->setReadingProfile(null);
                }
                
                $entityManager->remove($readingProfile);
                $entityManager->flush();

                $this->addFlash('success', 'Reading Profile deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Cannot delete this reading profile: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('danger', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_reading_profile_index', [], Response::HTTP_SEE_OTHER);
    }
}
