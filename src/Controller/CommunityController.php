<?php

namespace App\Controller;

use App\Entity\Community;
use App\Enum\CommunityStatus;
use App\Form\CommunityType;
use App\Repository\CommunityRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommunityController extends AbstractController
{
    #[Route('/community', name: 'app_community_index', methods: ['GET'])]
    public function index(CommunityRepository $communityRepository): Response
    {
        return $this->render('community/index.html.twig', [
            'communities' => $communityRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/community/new', name: 'app_community_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $community = new Community();
        $form = $this->createForm(CommunityType::class, $community);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($community);
            $entityManager->flush();

            $this->addFlash('success', sprintf('La communaute "%s" a ete creee avec succes.', $community->getName()));

            return $this->redirectToRoute('app_community_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('community/new.html.twig', [
            'community' => $community,
            'form' => $form,
        ]);
    }

    #[Route('/community/{id}', name: 'app_community_show', methods: ['GET'])]
    public function show(Community $community, PostRepository $postRepository): Response
    {
        return $this->render('community/show.html.twig', [
            'community' => $community,
            'posts' => $postRepository->findByCommunityForAdmin($community),
        ]);
    }

    #[Route('/community/{id}/edit', name: 'app_community_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Community $community, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CommunityType::class, $community);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La communaute a ete modifiee avec succes.');

            return $this->redirectToRoute('app_community_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('community/edit.html.twig', [
            'community' => $community,
            'form' => $form,
        ]);
    }

    #[Route('/community/{id}', name: 'app_community_delete', methods: ['POST'])]
    public function delete(Request $request, Community $community, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $community->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($community);
            $entityManager->flush();

            $this->addFlash('success', 'La communaute a ete supprimee avec succes.');
        }

        return $this->redirectToRoute('app_community_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/forum/communities', name: 'app_front_community_index', methods: ['GET'])]
    #[Route('/index-2.html', name: 'app_front_community_index_legacy', methods: ['GET'])]
    #[Route('/index-7.html', name: 'app_front_community_index_legacy_alt', methods: ['GET'])]
    public function frontIndex(CommunityRepository $communityRepository): Response
    {
        return $this->render('forum_front/community/index.html.twig', [
            'communities' => $communityRepository->findPublicApproved(),
        ]);
    }

    #[Route('/forum/communities/{id}', name: 'app_front_community_show', methods: ['GET'])]
    public function frontShow(Community $community, PostRepository $postRepository): Response
    {
        if (!$community->isPublic() || $community->getStatus() !== CommunityStatus::APPROVED) {
            throw $this->createNotFoundException('Communaute introuvable.');
        }

        return $this->render('forum_front/community/show.html.twig', [
            'community' => $community,
            'posts' => $postRepository->findVisibleByCommunity($community),
        ]);
    }
}
