<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\FaceRecognitionService;
use App\Repository\UserRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use App\Entity\User;

class FaceRecognitionServiceTest extends TestCase
{
    public function testThreshold()
    {
        $repo = $this->createMock(UserRepository::class);

        $service = new FaceRecognitionService($repo, 0.5);

        $this->assertEquals(0.5, $service->getThreshold());
    }

    public function testNoMatchingAdmin()
    {
        $repo = $this->createMock(UserRepository::class);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo->method('createQueryBuilder')->willReturn($qb);

        $service = new FaceRecognitionService($repo);

        $result = $service->findMatchingAdmin([0.1, 0.2, 0.3]);

        $this->assertNull($result);
    }
}