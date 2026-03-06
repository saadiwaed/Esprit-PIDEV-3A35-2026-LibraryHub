<?php

namespace App\Tests\Repository;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Repository\UserRepository;
use App\Entity\User;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = static::getContainer()
            ->get(UserRepository::class);
    }

    public function testSearchUsers()
    {
        $results = $this->repository->searchUsers('test');

        $this->assertIsArray($results);
    }

    public function testFindActiveUsers()
    {
        $users = $this->repository->findActiveUsers();

        $this->assertIsArray($users);
    }

    public function testSearchByNameOrEmail()
    {
        $users = $this->repository->searchByNameOrEmail('john');

        $this->assertIsArray($users);
    }
}