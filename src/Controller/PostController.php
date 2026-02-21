<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Community;
use App\Entity\Post;
use App\Entity\User;
use App\Enum\CommunityStatus;
use App\Enum\PostStatus;
use App\Form\FrontPostType;
use App\Form\PostType;
use App\Repository\CommunityRepository;
use App\Repository\PostRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    #[Route('/post', name: 'app_post_index', methods: ['GET'])]
    public function index(Request $request, PostRepository $postRepository): Response
    {
        $search = $this->normalizeSearch($request->query->getString('q'));
        $sort = $this->normalizePostSort($request->query->getString('sort', 'newest'));

        return $this->render('post/index.html.twig', [
            'posts' => $postRepository->findForAdmin($search, $sort),
            'filters' => [
                'q' => $search ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/post/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
        CommunityRepository $communityRepository
    ): Response {
        $post = new Post();
        $author = $this->getUser();
        if ($author instanceof User) {
            $post->setCreatedBy($author);
        }

        $communityId = $request->query->getInt('community');
        if ($communityId > 0) {
            $community = $communityRepository->find($communityId);
            if ($community !== null) {
                $post->setCommunity($community);
            }
        }

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleAttachments($form->get('attachmentFiles')->getData(), $post, $fileUploadService);

            $community = $post->getCommunity();
            if ($community !== null) {
                $community->incrementPostCount();
            }

            if ($post->getCreatedBy() === null && $author instanceof User) {
                $post->setCreatedBy($author);
            }

            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le post "%s" a ete cree avec succes.', $post->getTitle()));

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/post/{id}', name: 'app_post_show', methods: ['GET'])]
    public function show(Post $post): Response
    {
        return $this->render('post/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/post/{id}/edit', name: 'app_post_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleAttachments($form->get('attachmentFiles')->getData(), $post, $fileUploadService);

            $entityManager->flush();

            $this->addFlash('success', 'Le post a ete modifie avec succes.');

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/post/{id}', name: 'app_post_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->getPayload()->getString('_token'))) {
            foreach ($post->getAttachments() as $attachment) {
                $fileUploadService->delete($attachment->getFilePath());
            }

            $community = $post->getCommunity();
            if ($community !== null) {
                $community->decrementPostCount();
            }

            $entityManager->remove($post);
            $entityManager->flush();

            $this->addFlash('success', 'Le post a ete supprime avec succes.');
        }

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/post/{postId}/attachment/{attachmentId}/delete', name: 'app_attachment_delete', methods: ['POST'])]
    public function deleteAttachment(
        int $postId,
        int $attachmentId,
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $attachment = $entityManager->getRepository(Attachment::class)->find($attachmentId);

        if (!$attachment || $attachment->getPost()->getId() !== $postId) {
            throw $this->createNotFoundException('Piece jointe introuvable.');
        }

        if ($this->isCsrfTokenValid('delete_attachment' . $attachmentId, $request->getPayload()->getString('_token'))) {
            $fileUploadService->delete($attachment->getFilePath());

            $entityManager->remove($attachment);
            $entityManager->flush();

            $this->addFlash('success', 'La piece jointe a ete supprimee.');
        }

        return $this->redirectToRoute('app_post_edit', ['id' => $postId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/forum/communities/{id}/posts/new', name: 'app_front_post_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function frontNew(
        Community $community,
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $author = $this->getUser();
        if (!$author instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        if (!$community->isPublic() || $community->getStatus() !== CommunityStatus::APPROVED) {
            throw $this->createNotFoundException('Communaute introuvable.');
        }

        if (!$community->hasMember($author)) {
            $this->addFlash('warning', 'Vous devez rejoindre la communaute avant de publier un post.');

            return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()], Response::HTTP_SEE_OTHER);
        }

        $post = new Post();
        $post->setCommunity($community);
        $post->setStatus(PostStatus::PUBLISHED);
        $post->setCreatedBy($author);

        $form = $this->createForm(FrontPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleAttachments($form->get('attachmentFiles')->getData(), $post, $fileUploadService);
            $post->setCreatedBy($author);

            $community->incrementPostCount();

            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('success', 'Votre post a ete publie.');

            return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()]);
        }

        return $this->render('forum_front/post/new.html.twig', [
            'community' => $community,
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/forum/posts/{id}', name: 'app_front_post_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function frontShow(Post $post): Response
    {
        $community = $post->getCommunity();

        if (
            $community === null
            || !$community->isPublic()
            || $community->getStatus() !== CommunityStatus::APPROVED
            || $post->getStatus() !== PostStatus::PUBLISHED
        ) {
            throw $this->createNotFoundException('Post introuvable.');
        }

        return $this->render('forum_front/post/show.html.twig', [
            'post' => $post,
        ]);
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

    /**
     * @param array<int, \Symfony\Component\HttpFoundation\File\UploadedFile>|null $attachmentFiles
     */
    private function handleAttachments(?array $attachmentFiles, Post $post, FileUploadService $fileUploadService): void
    {
        if (!$attachmentFiles) {
            return;
        }

        foreach ($attachmentFiles as $file) {
            $filename = $fileUploadService->upload($file);

            $attachment = new Attachment();
            $attachment->setFilePath($filename);
            $post->addAttachment($attachment);
        }
    }
}
