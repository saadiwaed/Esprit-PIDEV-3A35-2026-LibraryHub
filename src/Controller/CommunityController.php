<?php

namespace App\Controller;

use App\Entity\Community;
use App\Form\CommunityType;
use App\Repository\CommunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/community')]
final class CommunityController extends AbstractController
{
    #[Route(name: 'app_community_index', methods: ['GET'])]
    public function index(CommunityRepository $communityRepository): Response
    {
        return $this->render('community/index.html.twig', [
            'communities' => $communityRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_community_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $community = new Community();
        $form = $this->createForm(CommunityType::class, $community);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($community);
            $entityManager->flush();

            $this->addFlash('success', 'La communauté "' . $community->getName() . '" a été créée avec succès.');

            return $this->redirectToRoute('app_community_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('community/new.html.twig', [
            'community' => $community,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_community_show', methods: ['GET'])]
    public function show(Community $community): Response
    {
        return $this->render('community/show.html.twig', [
            'community' => $community,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_community_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Community $community, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CommunityType::class, $community);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La communauté a été modifiée avec succès.');

            return $this->redirectToRoute('app_community_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('community/edit.html.twig', [
            'community' => $community,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_community_delete', methods: ['POST'])]
    public function delete(Request $request, Community $community, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $community->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($community);
            $entityManager->flush();

            $this->addFlash('success', 'La communauté a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_community_index', [], Response::HTTP_SEE_OTHER);
    }
}