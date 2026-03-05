<?php

namespace App\Controller;

use App\Entity\Attachment;
use App\Entity\Comment;
use App\Entity\CommentReaction;
use App\Entity\Community;
use App\Entity\Post;
use App\Entity\PostReport;
use App\Entity\PostReaction;
use App\Entity\User;
use App\Enum\CommunityStatus;
use App\Enum\PostModerationDecision;
use App\Enum\PostStatus;
use App\Enum\ReactionType;
use App\Form\CommentType;
use App\Form\FrontPostType;
use App\Form\PostType;
use App\Repository\CommentReactionRepository;
use App\Repository\CommentRepository;
use App\Repository\CommunityRepository;
use App\Repository\PostReportRepository;
use App\Repository\PostReactionRepository;
use App\Repository\PostRepository;
use App\Service\FileUploadService;
use App\Service\Forum\ForumContentModerationService;
use App\Service\PostModerationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormError;
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
        CommunityRepository $communityRepository,
        ForumContentModerationService $forumContentModerationService
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
            $moderationResult = $forumContentModerationService->moderate((string) $post->getContent(), 'post');
            if ($moderationResult->isBlocked()) {
                $form->get('content')->addError(new FormError($moderationResult->getMessage()));
            } else {
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

                $message = sprintf('Le post "%s" a ete cree avec succes.', $post->getTitle());
                if ($moderationResult->isApiAvailable() && $moderationResult->getToxicityScore() !== null) {
                    $message .= sprintf(' (Score de toxicite API: %.2f)', $moderationResult->getToxicityScore());
                }
                $this->addFlash('success', $message);

                return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
            }
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
        FileUploadService $fileUploadService,
        ForumContentModerationService $forumContentModerationService
    ): Response {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $moderationResult = $forumContentModerationService->moderate((string) $post->getContent(), 'post');
            if ($moderationResult->isBlocked()) {
                $form->get('content')->addError(new FormError($moderationResult->getMessage()));
            } else {
                $this->handleAttachments($form->get('attachmentFiles')->getData(), $post, $fileUploadService);

                $entityManager->flush();

                $message = 'Le post a ete modifie avec succes.';
                if ($moderationResult->isApiAvailable() && $moderationResult->getToxicityScore() !== null) {
                    $message .= sprintf(' (Score de toxicite API: %.2f)', $moderationResult->getToxicityScore());
                }
                $this->addFlash('success', $message);

                return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
            }
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

    #[Route('/post/moderation/queue', name: 'app_post_moderation_queue', methods: ['GET'])]
    public function moderationQueue(PostReportRepository $postReportRepository): Response
    {
        $this->denyUnlessModerator();

        return $this->render('post/moderation_queue.html.twig', [
            'queue' => $this->groupPendingReportsByPost($postReportRepository->findPendingForModerationQueue()),
        ]);
    }

    #[Route('/post/{id}/moderate', name: 'app_post_moderate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function moderatePost(
        Post $post,
        Request $request,
        EntityManagerInterface $entityManager,
        PostModerationService $postModerationService
    ): Response {
        $this->denyUnlessModerator();

        $moderator = $this->getUser();
        if (!$moderator instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        if (!$this->isCsrfTokenValid('moderate_post_' . $post->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_post_moderation_queue', [], Response::HTTP_SEE_OTHER);
        }

        $decision = PostModerationDecision::tryFrom(trim($request->request->getString('decision')));
        if ($decision === null || $decision === PostModerationDecision::APPROVE) {
            $this->addFlash('error', 'Decision de moderation invalide.');

            return $this->redirectToRoute('app_post_moderation_queue', [], Response::HTTP_SEE_OTHER);
        }

        $affectedReports = $postModerationService->moderate(
            $post,
            $moderator,
            $decision,
            $request->request->getString('decision_reason')
        );

        if ($affectedReports === 0) {
            $this->addFlash('warning', 'Aucun signalement en attente pour ce post.');

            return $this->redirectToRoute('app_post_moderation_queue', [], Response::HTTP_SEE_OTHER);
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Moderation appliquee (%d signalement%s traite%s).',
            $affectedReports,
            $affectedReports > 1 ? 's' : '',
            $affectedReports > 1 ? 's' : ''
        ));

        return $this->redirectToRoute('app_post_moderation_queue', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/forum/communities/{id}/posts/new', name: 'app_front_post_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function frontNew(
        Community $community,
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
        ForumContentModerationService $forumContentModerationService
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
            $moderationResult = $forumContentModerationService->moderate((string) $post->getContent(), 'post');
            if ($moderationResult->isBlocked()) {
                $form->get('content')->addError(new FormError($moderationResult->getMessage()));
            } else {
                $this->handleAttachments($form->get('attachmentFiles')->getData(), $post, $fileUploadService);
                $post->setCreatedBy($author);

                $community->incrementPostCount();

                $entityManager->persist($post);
                $entityManager->flush();

                $message = 'Votre post a ete publie.';
                if ($moderationResult->isApiAvailable() && $moderationResult->getToxicityScore() !== null) {
                    $message .= sprintf(' (Score de toxicite API: %.2f)', $moderationResult->getToxicityScore());
                }
                $this->addFlash('success', $message);

                return $this->redirectToRoute('app_front_community_show', ['id' => $community->getId()]);
            }
        }

        return $this->render('forum_front/post/new.html.twig', [
            'community' => $community,
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/forum/posts/{id}', name: 'app_front_post_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function frontShow(
        int $id,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        PostReactionRepository $postReactionRepository,
        CommentReactionRepository $commentReactionRepository,
        PostReportRepository $postReportRepository
    ): Response {
        $post = $postRepository->find($id);
        if (!$post instanceof Post) {
            return $this->renderPostUnavailable();
        }

        $community = $this->getVisibleFrontCommunity($post);
        if (!$community instanceof Community) {
            return $this->renderPostUnavailable($post);
        }

        $comments = $commentRepository->findByPostForFront($post);
        $topLevelComments = [];
        $childCommentsByParent = [];

        foreach ($comments as $commentItem) {
            $parentId = $commentItem->getParent()?->getId();
            if ($parentId === null) {
                $topLevelComments[] = $commentItem;

                continue;
            }

            $childCommentsByParent[$parentId][] = $commentItem;
        }

        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment, [
            'action' => $this->generateUrl('app_front_post_comment_new', ['id' => $post->getId()]),
            'method' => 'POST',
        ]);

        $user = $this->getUser();
        $isMember = $user instanceof User && $community->hasMember($user);
        $currentPostReaction = null;
        $commentReactionMap = [];
        $hasReportedPost = false;
        $replyForms = [];

        if ($user instanceof User) {
            $currentPostReaction = $postReactionRepository->findOneByPostAndUser($post, $user)?->getType();
            $hasReportedPost = $postReportRepository->findPendingOneByPostAndReporter($post, $user) !== null;

            foreach ($commentReactionRepository->findByPostAndUser($post, $user) as $reaction) {
                $commentId = $reaction->getComment()?->getId();
                if ($commentId !== null) {
                    $commentReactionMap[$commentId] = $reaction->getType();
                }
            }
        }

        if ($isMember && $post->isAllowComments()) {
            foreach ($comments as $commentItem) {
                $commentId = $commentItem->getId();
                if ($commentId === null) {
                    continue;
                }

                $reply = new Comment();
                $reply->setPost($post);
                $reply->setParent($commentItem);

                $replyForms[$commentId] = $this->createNamedCommentForm('reply_comment_' . $commentId, $reply, [
                    'action' => $this->generateUrl('app_front_post_comment_reply', [
                        'postId' => $post->getId(),
                        'parentId' => $commentId,
                    ]),
                    'method' => 'POST',
                ])->createView();
            }
        }

        return $this->render('forum_front/post/show.html.twig', [
            'post' => $post,
            'comments' => $comments,
            'topLevelComments' => $topLevelComments,
            'childCommentsByParent' => $childCommentsByParent,
            'commentForm' => $commentForm->createView(),
            'replyForms' => $replyForms,
            'isMember' => $isMember,
            'currentPostReaction' => $currentPostReaction,
            'commentReactionMap' => $commentReactionMap,
            'hasReportedPost' => $hasReportedPost,
        ]);
    }

    #[Route('/forum/posts/{id}/unavailable', name: 'app_front_post_unavailable', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function frontUnavailable(
        int $id,
        Request $request,
        PostRepository $postRepository,
        CommunityRepository $communityRepository
    ): Response {
        $post = $postRepository->find($id);
        if ($post instanceof Post && $this->getVisibleFrontCommunity($post) instanceof Community) {
            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $community = null;
        $communityId = $request->query->getInt('community');
        if ($communityId > 0) {
            $community = $communityRepository->find($communityId);
        }

        return $this->renderPostUnavailable($post, $community);
    }

    #[Route('/forum/posts/{id}/comments/new', name: 'app_front_post_comment_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addComment(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ForumContentModerationService $forumContentModerationService,
        PostRepository $postRepository
    ): Response
    {
        $post = $postRepository->find($id);
        if (!$post instanceof Post) {
            return $this->redirectToUnavailablePost($id, null, 'Ce post n est plus disponible.');
        }

        if (!$this->getVisibleFrontCommunity($post) instanceof Community) {
            return $this->redirectToUnavailablePost(
                $id,
                $post->getCommunity()?->getId(),
                'Ce post n est plus disponible.'
            );
        }

        return $this->handleCommentCreation($post, null, $request, $entityManager, $forumContentModerationService);
    }

    #[Route('/forum/posts/{postId}/comments/{parentId}/reply', name: 'app_front_post_comment_reply', methods: ['POST'], requirements: ['postId' => '\d+', 'parentId' => '\d+'])]
    public function addCommentReply(
        int $postId,
        int $parentId,
        Request $request,
        EntityManagerInterface $entityManager,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        ForumContentModerationService $forumContentModerationService
    ): Response
    {
        $post = $postRepository->find($postId);
        if (!$post instanceof Post) {
            return $this->redirectToUnavailablePost($postId, null, 'Ce post n est plus disponible.');
        }

        if (!$this->getVisibleFrontCommunity($post) instanceof Community) {
            return $this->redirectToUnavailablePost(
                $postId,
                $post->getCommunity()?->getId(),
                'Ce post n est plus disponible.'
            );
        }

        $parentComment = $commentRepository->find($parentId);

        if (!$parentComment instanceof Comment || $parentComment->getPost()?->getId() !== $post->getId()) {
            $this->addFlash('warning', 'Le commentaire parent n est plus disponible.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $postId], Response::HTTP_SEE_OTHER);
        }

        return $this->handleCommentCreation(
            $post,
            $parentComment,
            $request,
            $entityManager,
            $forumContentModerationService,
            'reply_comment_' . $parentId
        );
    }

    #[Route('/forum/posts/{id}/report', name: 'app_front_post_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reportPost(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        PostReportRepository $postReportRepository,
        PostRepository $postRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $post = $postRepository->find($id);
        if (!$post instanceof Post) {
            return $this->redirectToUnavailablePost($id, null, 'Ce post n est plus disponible.');
        }

        $community = $this->getVisibleFrontCommunity($post);
        if (!$community instanceof Community) {
            return $this->redirectToUnavailablePost(
                $id,
                $post->getCommunity()?->getId(),
                'Ce post n est plus disponible.'
            );
        }

        if (!$community->hasMember($user)) {
            $this->addFlash('warning', 'Vous devez rejoindre la communaute avant de signaler un post.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('report_post_' . $post->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($postReportRepository->findPendingOneByPostAndReporter($post, $user) !== null) {
            $this->addFlash('warning', 'Vous avez deja signale ce post.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $reason = trim($request->request->getString('reason'));
        if (mb_strlen(preg_replace('/\s+/', '', $reason) ?? '') < 10) {
            $this->addFlash('error', 'Le motif du signalement doit contenir au moins 10 caracteres utiles.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $report = new PostReport();
        $report->setPost($post);
        $report->setReporter($user);
        $report->setReason($reason);

        $entityManager->persist($report);
        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('error', 'Contrainte de base non synchronisee. Appliquez la derniere migration puis reessayez.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', 'Votre signalement a ete envoye a l equipe de moderation.');

        return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/forum/posts/{id}/react/{type}', name: 'app_front_post_react', methods: ['POST'], requirements: ['id' => '\d+', 'type' => 'like|dislike'])]
    public function reactPost(
        int $id,
        string $type,
        Request $request,
        EntityManagerInterface $entityManager,
        PostReactionRepository $postReactionRepository,
        PostRepository $postRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $post = $postRepository->find($id);
        if (!$post instanceof Post) {
            return $this->redirectToUnavailablePost($id, null, 'Ce post n est plus disponible.');
        }

        $community = $this->getVisibleFrontCommunity($post);
        if (!$community instanceof Community) {
            return $this->redirectToUnavailablePost(
                $id,
                $post->getCommunity()?->getId(),
                'Ce post n est plus disponible.'
            );
        }

        if (!$community->hasMember($user)) {
            $this->addFlash('warning', 'Vous devez rejoindre la communaute avant de reagir.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('react_post_' . $post->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $requestedType = $type === ReactionType::DISLIKE->value ? ReactionType::DISLIKE : ReactionType::LIKE;
        $existingReaction = $postReactionRepository->findOneByPostAndUser($post, $user);

        if ($existingReaction === null) {
            $reaction = new PostReaction();
            $reaction->setPost($post);
            $reaction->setUser($user);
            $reaction->setType($requestedType);
            $entityManager->persist($reaction);

            if ($requestedType === ReactionType::LIKE) {
                $post->incrementLikeCount();
            } else {
                $post->incrementDislikeCount();
            }
        } elseif ($existingReaction->getType() === $requestedType) {
            if ($existingReaction->getType() === ReactionType::LIKE) {
                $post->decrementLikeCount();
            } else {
                $post->decrementDislikeCount();
            }

            $entityManager->remove($existingReaction);
        } else {
            if ($existingReaction->getType() === ReactionType::LIKE) {
                $post->decrementLikeCount();
                $post->incrementDislikeCount();
            } else {
                $post->decrementDislikeCount();
                $post->incrementLikeCount();
            }

            $existingReaction->setType($requestedType);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/forum/posts/{postId}/comments/{commentId}/react/{type}', name: 'app_front_comment_react', methods: ['POST'], requirements: ['postId' => '\d+', 'commentId' => '\d+', 'type' => 'like|dislike'])]
    public function reactComment(
        int $postId,
        int $commentId,
        string $type,
        Request $request,
        EntityManagerInterface $entityManager,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        CommentReactionRepository $commentReactionRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $post = $postRepository->find($postId);
        if (!$post instanceof Post) {
            return $this->redirectToUnavailablePost($postId, null, 'Ce post n est plus disponible.');
        }

        $community = $this->getVisibleFrontCommunity($post);
        if (!$community instanceof Community) {
            return $this->redirectToUnavailablePost(
                $postId,
                $post->getCommunity()?->getId(),
                'Ce post n est plus disponible.'
            );
        }

        $comment = $commentRepository->find($commentId);
        if (!$comment instanceof Comment || $comment->getPost()?->getId() !== $post->getId()) {
            $this->addFlash('warning', 'Ce commentaire n est plus disponible.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $postId], Response::HTTP_SEE_OTHER);
        }

        if (!$community->hasMember($user)) {
            $this->addFlash('warning', 'Vous devez rejoindre la communaute avant de reagir.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('react_comment_' . $comment->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $requestedType = $type === ReactionType::DISLIKE->value ? ReactionType::DISLIKE : ReactionType::LIKE;
        $existingReaction = $commentReactionRepository->findOneByCommentAndUser($comment, $user);

        if ($existingReaction === null) {
            $reaction = new CommentReaction();
            $reaction->setComment($comment);
            $reaction->setUser($user);
            $reaction->setType($requestedType);
            $entityManager->persist($reaction);

            if ($requestedType === ReactionType::LIKE) {
                $comment->incrementLikeCount();
            } else {
                $comment->incrementDislikeCount();
            }
        } elseif ($existingReaction->getType() === $requestedType) {
            if ($existingReaction->getType() === ReactionType::LIKE) {
                $comment->decrementLikeCount();
            } else {
                $comment->decrementDislikeCount();
            }

            $entityManager->remove($existingReaction);
        } else {
            if ($existingReaction->getType() === ReactionType::LIKE) {
                $comment->decrementLikeCount();
                $comment->incrementDislikeCount();
            } else {
                $comment->decrementDislikeCount();
                $comment->incrementLikeCount();
            }

            $existingReaction->setType($requestedType);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
    }

    private function handleCommentCreation(
        Post $post,
        ?Comment $parentComment,
        Request $request,
        EntityManagerInterface $entityManager,
        ForumContentModerationService $forumContentModerationService,
        ?string $formName = null
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $community = $this->getVisibleFrontCommunity($post);
        if (!$community instanceof Community) {
            return $this->redirectToUnavailablePost(
                $post->getId() ?? 0,
                $post->getCommunity()?->getId(),
                'Ce post n est plus disponible.'
            );
        }

        if (!$community->hasMember($user)) {
            $this->addFlash('warning', 'Vous devez rejoindre la communaute avant de commenter.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        if (!$post->isAllowComments()) {
            $this->addFlash('warning', 'Les commentaires sont desactives pour ce post.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($parentComment instanceof Comment && $parentComment->getPost()?->getId() !== $post->getId()) {
            $this->addFlash('warning', 'Le commentaire parent n est plus disponible.');

            return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setCreatedBy($user);
        $comment->setParent($parentComment);

        $form = $formName === null
            ? $this->createForm(CommentType::class, $comment)
            : $this->createNamedCommentForm($formName, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $moderationResult = $forumContentModerationService->moderate((string) $comment->getContent(), 'commentaire');
            if ($moderationResult->isBlocked()) {
                $this->addFlash('comment_error', $moderationResult->getMessage());

                return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
            }

            $post->incrementCommentCount();

            $entityManager->persist($comment);
            $entityManager->flush();

            $message = $parentComment instanceof Comment ? 'Reponse publiee.' : 'Commentaire publie.';
            if ($moderationResult->isApiAvailable() && $moderationResult->getToxicityScore() !== null) {
                $message .= sprintf(' (Score de toxicite API: %.2f)', $moderationResult->getToxicityScore());
            }
            $this->addFlash('success', $message);
        } else {
            $this->addFlash('comment_error', 'Impossible de publier ce commentaire.');
        }

        return $this->redirectToRoute('app_front_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
    }

    private function createNamedCommentForm(string $name, Comment $comment, array $options = [])
    {
        $formFactory = $this->container->get('form.factory');
        if (!$formFactory instanceof FormFactoryInterface) {
            throw new \LogicException('Le service form.factory est indisponible.');
        }

        return $formFactory->createNamed($name, CommentType::class, $comment, $options);
    }

    private function getVisibleFrontCommunity(Post $post): ?Community
    {
        $community = $post->getCommunity();

        if (
            $community === null
            || !$community->isPublic()
            || $community->getStatus() !== CommunityStatus::APPROVED
            || $post->getStatus() !== PostStatus::PUBLISHED
        ) {
            return null;
        }

        return $community;
    }

    private function redirectToUnavailablePost(int $postId, ?int $communityId = null, ?string $flashMessage = null): Response
    {
        if ($flashMessage !== null && $flashMessage !== '') {
            $this->addFlash('warning', $flashMessage);
        }

        $routeParameters = ['id' => $postId];
        if ($communityId !== null) {
            $routeParameters['community'] = $communityId;
        }

        return $this->redirectToRoute('app_front_post_unavailable', $routeParameters, Response::HTTP_SEE_OTHER);
    }

    private function renderPostUnavailable(?Post $post = null, ?Community $fallbackCommunity = null): Response
    {
        $community = $post?->getCommunity();
        if (!$community instanceof Community) {
            $community = $fallbackCommunity;
        }

        $canNavigateToCommunity = $community instanceof Community
            && $community->isPublic()
            && $community->getStatus() === CommunityStatus::APPROVED;

        $response = new Response();
        $response->setStatusCode(Response::HTTP_GONE);

        return $this->render('forum_front/post/unavailable.html.twig', [
            'post' => $post,
            'community' => $community,
            'canNavigateToCommunity' => $canNavigateToCommunity,
        ], $response);
    }

    /**
     * @param array<int, PostReport> $reports
     * @return array<int, array{post: Post, reports: array<int, PostReport>, pendingCount: int}>
     */
    private function groupPendingReportsByPost(array $reports): array
    {
        $queue = [];

        foreach ($reports as $report) {
            $post = $report->getPost();
            if (!$post instanceof Post || $post->getId() === null) {
                continue;
            }

            $postId = $post->getId();
            if (!isset($queue[$postId])) {
                $queue[$postId] = [
                    'post' => $post,
                    'reports' => [],
                    'pendingCount' => 0,
                ];
            }

            $queue[$postId]['reports'][] = $report;
            ++$queue[$postId]['pendingCount'];
        }

        return $queue;
    }

    private function denyUnlessModerator(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_LIBRARIAN')) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }

    private function normalizeSearch(?string $search): ?string
    {
        $search = trim((string) $search);

        return $search === '' ? null : $search;
    }

    private function normalizePostSort(?string $sort): string
    {
        $sort = strtolower(trim((string) $sort));
        $allowed = ['newest', 'oldest', 'most_commented', 'most_liked', 'best_score', 'title_asc', 'title_desc'];

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
