<?php

namespace App\EventListener;

use App\Entity\Loan;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class LoanStatusListener
{
    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof Loan) {
            return;
        }

        $entity->refreshStatusFromDates();
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof Loan) {
            return;
        }

        $statusChanged = $entity->refreshStatusFromDates();
        if (!$statusChanged) {
            return;
        }

        $entityManager = $event->getObjectManager();
        $metadata = $entityManager->getClassMetadata(Loan::class);
        $entityManager->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
    }
}
