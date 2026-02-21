<?php

namespace App\Controller;

use App\Entity\Community;
use App\Entity\User;
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
    public function index(Request $request, CommunityRepository $communityRepository): Response
    {
        $search = $this->normalizeSearch($request->query->getString('q'));
        $sort = $this->normalizeCommunitySort($request->query->getString('sort', 'newest'));

        return $this->render('community/index.html.twig', [
            'communities' => $communityRepository->findForAdmin($search, $sort),
            'filters' => [
                'q' => $search ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/community/new', name: 'app_community_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $community = new Community();
        $creator = $this->getUser();
        if ($creator instanceof User) {
            $community->setCreatedBy($creator);
            $community->addMember($creator);
        }

        $form = $this->createForm(CommunityType::class, $community, [
            'allow_status_change' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($community->getCreatedBy() === null && $creator instanceof User) {
                $community->setCreatedBy($creator);
            }

            if ($community->getCreatedBy() instanceof User) {
                $community->addMember($community->getCreatedBy());
            }

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
    public function show(Community $community, PostRepository $postRepository, Request $request): Response
    {
        $search = $this->normalizeSearch($request->query->getString('q'));
        $sort = $this->normalizePostSort($request->query->getString('sort', 'newest'));

        return $this->render('community/show.html.twig', [
            'community' => $community,
            'posts' => $postRepository->findByCommunityForAdmin($community, $search, $sort),
            'postFilters' => [
                'q' => $search ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/community/{id}/edit', name: 'app_community_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Community $community, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CommunityType::class, $community, [
            'allow_status_change' => true,
        ]);
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

    #[Route('/forum/communities/new', name: 'app_front_community_new', methods: ['GET', 'POST'])]
    public function frontNew(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $creator = $this->getUser();
        if (!$creator instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $community = new Community();
        $community->setCreatedBy($creator);
        $community->setStatus(CommunityStatus::PENDING);
        $community->addMember($creator);

        $form = $this->createForm(CommunityType::class, $community, [
            'allow_status_change' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Front-office communities always require moderation approval.
            $community->setStatus(CommunityStatus::PENDING);
            $community->setCreatedBy($creator);
            $community->addMember($creator);

            $entityManager->persist($community);
            $entityManager->flush();

            $this->addFlash('success', sprintf('La communaute "%s" a ete soumise pour validation.', $community->getName()));

            return $this->redirectToRoute('app_front_community_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum_front/community/new.html.twig', [
            'community' => $community,
            'form' => $form,
        ]);
    }

    #[Route('/forum/communities', name: 'app_front_community_index', methods: ['GET'])]
    #[Route('/index-2.html', name: 'app_front_community_index_legacy', methods: ['GET'])]
    #[Route('/index-7.html', name: 'app_front_community_index_legacy_alt', methods: ['GET'])]
    public function frontIndex(Request $request, CommunityRepository $communityRepository): Response
    {
        $search = $this->normalizeSearch($request->query->getString('q'));
        $sort = $this->normalizeCommunitySort($request->query->getString('sort', 'newest'));

        return $this->render('forum_front/community/index.html.twig', [
            'communities' => $communityRepository->findPublicApprovedByFilters($search, $sort),
            'filters' => [
                'q' => $search ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/forum/communities/{id}', name: 'app_front_community_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function frontShow(Community $community, PostRepository $postRepository, Request $request): Response
    {
        if (!$community->isPublic() || $community->getStatus() !== CommunityStatus::APPROVED) {
            throw $this->createNotFoundException('Communaute introuvable.');
        }

        $search = $this->normalizeSearch($request->query->getString('q'));
        $sort = $this->normalizePostSort($request->query->getString('sort', 'newest'));
        $user = $this->getUser();
        $isSubscribed = $user instanceof User && $community->hasMember($user);

        return $this->render('forum_front/community/show.html.twig', [
            'community' => $community,
            'posts' => $postRepository->findVisibleByCommunity($community, $search, $sort),
            'isSubscribed' => $isSubscribed,
            'postFilters' => [
                'q' => $search ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/forum/communities/{id}/join', name: 'app_front_community_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(Community $community, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        if (!$community->isPublic() || $community->getStatus() !== CommunityStatus::APPROVED) {
            throw $this->createNotFoundException('Communaute introuvable.');
        }

        if (!$this->isCsrfTokenValid('join' . $community->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($community->hasMember($user)) {
            $this->addFlash('warning', 'Vous etes deja membre de cette communaute.');

            return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
        }

        $community->addMember($user);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Vous avez rejoint la communaute "%s".', $community->getName()));

        return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/forum/communities/{id}/leave', name: 'app_front_community_leave', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function leave(Community $community, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        if (!$community->isPublic() || $community->getStatus() !== CommunityStatus::APPROVED) {
            throw $this->createNotFoundException('Communaute introuvable.');
        }

        if (!$this->isCsrfTokenValid('leave' . $community->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
        }

        if (!$community->hasMember($user)) {
            $this->addFlash('warning', 'Vous n etes pas membre de cette communaute.');

            return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
        }

        $community->removeMember($user);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Vous avez quitte la communaute "%s".', $community->getName()));

        return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }

    private function normalizePostSort(?string $sort): string
    {
        $sort = strtolower(trim((string) $sort));
        $allowed = ['newest', 'oldest', 'most_commented', 'title_asc', 'title_desc'];

        return in_array($sort, $allowed, true) ? $sort : 'newest';
    }

    private function normalizeCommunitySort(?string $sort): string
    {
        $sort = strtolower(trim((string) $sort));
        $allowed = ['newest', 'oldest', 'name_asc', 'name_desc', 'most_posts', 'most_members'];

        return in_array($sort, $allowed, true) ? $sort : 'newest';
    }
}
