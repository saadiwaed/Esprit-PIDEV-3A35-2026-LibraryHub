<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Post;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/post')]
final class PostController extends AbstractController
{
    #[Route(name: 'app_post_index', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        return $this->render('post/index.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ─── Handle file attachments ─────────────────────
            $attachmentFiles = $form->get('attachmentFiles')->getData();
            if ($attachmentFiles) {
                foreach ($attachmentFiles as $file) {
                    $filename = $fileUploadService->upload($file);

                    $attachment = new Attachment();
                    $attachment->setFilePath($filename);
                    $post->addAttachment($attachment);
                }
            }

            // ─── Business Logic: increment community post count ───
            $community = $post->getCommunity();
            if ($community !== null) {
                $community->incrementPostCount();
            }

            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('success', 'Le post "' . $post->getTitle() . '" a été créé avec succès.');

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_post_show', methods: ['GET'])]
    public function show(Post $post): Response
    {
        return $this->render('post/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_post_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ─── Handle NEW file attachments ─────────────────
            $attachmentFiles = $form->get('attachmentFiles')->getData();
            if ($attachmentFiles) {
                foreach ($attachmentFiles as $file) {
                    $filename = $fileUploadService->upload($file);

                    $attachment = new Attachment();
                    $attachment->setFilePath($filename);
                    $post->addAttachment($attachment);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le post a été modifié avec succès.');

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_post_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->getPayload()->getString('_token'))) {
            // ─── Delete physical files from disk ─────────────
            foreach ($post->getAttachments() as $attachment) {
                $fileUploadService->delete($attachment->getFilePath());
            }

            // ─── Business Logic: decrement community post count ───
            $community = $post->getCommunity();
            if ($community !== null) {
                $community->decrementPostCount();
            }

            $entityManager->remove($post);
            $entityManager->flush();

            $this->addFlash('success', 'Le post a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Supprime une pièce jointe individuelle
     */
    #[Route('/{postId}/attachment/{attachmentId}/delete', name: 'app_attachment_delete', methods: ['POST'])]
    public function deleteAttachment(
        int $postId,
        int $attachmentId,
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $attachment = $entityManager->getRepository(Attachment::class)->find($attachmentId);

        if (!$attachment || $attachment->getPost()->getId() !== $postId) {
            throw $this->createNotFoundException('Pièce jointe introuvable');
        }

        if ($this->isCsrfTokenValid('delete_attachment' . $attachmentId, $request->getPayload()->getString('_token'))) {
            // Delete the physical file
            $fileUploadService->delete($attachment->getFilePath());

            // Remove from database
            $entityManager->remove($attachment);
            $entityManager->flush();

            $this->addFlash('success', 'La pièce jointe a été supprimée.');
        }

        return $this->redirectToRoute('app_post_edit', ['id' => $postId], Response::HTTP_SEE_OTHER);
    }
}