<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\User;
use App\Enum\PostModerationDecision;
use App\Enum\PostReportStatus;
use App\Enum\PostStatus;
use App\Repository\PostReportRepository;

class PostModerationService
{
    public function __construct(private readonly PostReportRepository $postReportRepository)
    {
    }

    public function moderate(Post $post, User $moderator, PostModerationDecision $decision, ?string $decisionReason = null): int
    {
        $pendingReports = $this->postReportRepository->findPendingByPost($post);
        if ($pendingReports === []) {
            return 0;
        }

        $reviewedAt = new \DateTime();
        $normalizedReason = $this->normalizeReason($decisionReason);
        $targetStatus = $decision === PostModerationDecision::REJECT_REPORT
            ? PostReportStatus::REJECTED
            : PostReportStatus::RESOLVED;

        foreach ($pendingReports as $report) {
            $report->setStatus($targetStatus);
            $report->setReviewedBy($moderator);
            $report->setReviewedAt($reviewedAt);
            $report->setModeratorDecision($decision);
            $report->setModeratorDecisionReason($normalizedReason);
        }

        if ($decision === PostModerationDecision::HIDE_POST) {
            $post->setStatus(PostStatus::HIDDEN);
        }

        return count($pendingReports);
    }

    private function normalizeReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }
}
