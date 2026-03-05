<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Enum\CommunityStatus;
use App\Enum\PostStatus;
use App\Repository\CommentRepository;
use App\Service\Forum\ForumAiAssistantService;
use App\Service\Forum\ForumAiResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forum/ai', name: 'app_forum_ai_')]
final class ForumAiAssistantController extends AbstractController
{
    #[Route('/community/draft', name: 'community_draft', methods: ['POST'])]
    public function communityDraft(Request $request, ForumAiAssistantService $forumAiAssistantService): JsonResponse
    {
        $this->requireAuthenticatedUser();
        $payload = $this->decodeJsonPayload($request);
        if ($payload === null) {
            return $this->errorResponse('Requete JSON invalide.', JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $forumAiAssistantService->generateCommunityDraft(
            is_string($payload['name'] ?? null) ? $payload['name'] : null,
            is_string($payload['purpose'] ?? null) ? $payload['purpose'] : null,
            is_string($payload['description'] ?? null) ? $payload['description'] : null,
            is_string($payload['rules'] ?? null) ? $payload['rules'] : null,
            is_string($payload['welcomeMessage'] ?? null) ? $payload['welcomeMessage'] : null
        );

        return $this->successResponse($result);
    }

    #[Route('/post/draft', name: 'post_draft', methods: ['POST'])]
    public function postDraft(Request $request, ForumAiAssistantService $forumAiAssistantService): JsonResponse
    {
        $this->requireAuthenticatedUser();
        $payload = $this->decodeJsonPayload($request);
        if ($payload === null) {
            return $this->errorResponse('Requete JSON invalide.', JsonResponse::HTTP_BAD_REQUEST);
        }

        $result = $forumAiAssistantService->improvePostDraft(
            is_string($payload['title'] ?? null) ? $payload['title'] : null,
            is_string($payload['content'] ?? null) ? $payload['content'] : null,
            is_string($payload['communityName'] ?? null) ? $payload['communityName'] : null
        );

        return $this->successResponse($result);
    }

    #[Route('/post/{id}/comment/suggest', name: 'post_comment_suggest', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function commentSuggest(
        Post $post,
        Request $request,
        CommentRepository $commentRepository,
        ForumAiAssistantService $forumAiAssistantService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_MEMBER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->errorResponse('Utilisateur non authentifie.', JsonResponse::HTTP_UNAUTHORIZED);
        }

        $community = $post->getCommunity();
        if (
            $community === null
            || !$community->isPublic()
            || $community->getStatus() !== CommunityStatus::APPROVED
            || $post->getStatus() !== PostStatus::PUBLISHED
        ) {
            return $this->errorResponse('Post introuvable.', JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$community->hasMember($user)) {
            return $this->errorResponse('Vous devez rejoindre la communaute pour utiliser cette assistance.', JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = $this->decodeJsonPayload($request);
        if ($payload === null) {
            return $this->errorResponse('Requete JSON invalide.', JsonResponse::HTTP_BAD_REQUEST);
        }

        $commentSnippets = [];
        foreach ($commentRepository->findByPostForFront($post) as $comment) {
            $author = $comment->getCreatedBy()?->getFullName() ?? 'Utilisateur';
            $commentSnippets[] = sprintf('%s: %s', $author, (string) $comment->getContent());
        }

        $result = $forumAiAssistantService->suggestComment(
            (string) $post->getTitle(),
            (string) $post->getContent(),
            $commentSnippets,
            is_string($payload['draft'] ?? null) ? $payload['draft'] : null
        );

        return $this->successResponse($result);
    }

    #[Route('/post/{id}/summary', name: 'post_summary', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function postSummary(
        Post $post,
        CommentRepository $commentRepository,
        ForumAiAssistantService $forumAiAssistantService
    ): JsonResponse {
        $this->requireAuthenticatedUser();

        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_LIBRARIAN')) {
            $community = $post->getCommunity();
            if (
                $community === null
                || !$community->isPublic()
                || $community->getStatus() !== CommunityStatus::APPROVED
                || $post->getStatus() !== PostStatus::PUBLISHED
            ) {
                return $this->errorResponse('Post introuvable.', JsonResponse::HTTP_NOT_FOUND);
            }
        }

        $commentSnippets = [];
        foreach ($commentRepository->findByPostForFront($post) as $comment) {
            $author = $comment->getCreatedBy()?->getFullName() ?? 'Utilisateur';
            $commentSnippets[] = sprintf('%s: %s', $author, (string) $comment->getContent());
        }

        $result = $forumAiAssistantService->summarizeThread(
            (string) $post->getTitle(),
            (string) $post->getContent(),
            $commentSnippets
        );

        return $this->successResponse($result);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(Request $request): ?array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            return null;
        }
    }

    private function requireAuthenticatedUser(): void
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    }

    private function successResponse(ForumAiResult $result): JsonResponse
    {
        return $this->json([
            'ok' => true,
            'data' => $result->getPayload(),
            'meta' => [
                'usedAi' => $result->usedAi(),
                'fallbackUsed' => $result->isFallbackUsed(),
                'confidence' => $result->getConfidence(),
                'message' => $result->getMessage(),
            ],
        ]);
    }

    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return $this->json([
            'ok' => false,
            'error' => $message,
        ], $statusCode);
    }
}
