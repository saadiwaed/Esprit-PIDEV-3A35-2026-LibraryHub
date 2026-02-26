<?php

namespace App\EventListener;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Service\SmsReminderService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
final class PenaltySmsListener
{
    /**
     * @var Penalty[]
     */
    private array $queued = [];

    private bool $processing = false;

    public function __construct(
        private readonly SmsReminderService $smsReminderService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Penalty) {
            return;
        }

        $this->queued[] = $entity;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->processing || $this->queued === []) {
            return;
        }

        $this->processing = true;

        $entityManager = $args->getObjectManager();
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);

        try {
            $pending = 0;
            foreach ($this->queued as $penalty) {
                $loan = $penalty->getLoan();
                if (!$loan instanceof Loan) {
                    continue;
                }

                $sent = $this->smsReminderService->sendPenaltyAppliedReminder($penalty, $today, false);
                if ($sent) {
                    $entityManager->persist($loan);
                    $pending++;
                }
            }

            $this->queued = [];

            if ($pending > 0) {
                $entityManager->flush();
            }
        } catch (\Throwable $e) {
            $this->logger->error('Penalty SMS listener failed.', [
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);
            $this->queued = [];
        } finally {
            $this->processing = false;
        }
    }
}

