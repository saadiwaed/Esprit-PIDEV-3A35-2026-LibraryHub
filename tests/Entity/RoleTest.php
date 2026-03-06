<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\Role;
use App\Entity\User;

class RoleTest extends TestCase
{
    public function testRoleCreation()
    {
        $role = new Role();

        $role->setName('ROLE_ADMIN');
        $role->setDescription('Administrator role');

        $this->assertEquals('ROLE_ADMIN', $role->getName());
        $this->assertEquals('Administrator role', $role->getDescription());
    }

    public function testUserRelation()
    {
        $role = new Role();
        $role->setName('ROLE_MEMBER');

        $user = new User();
        $user->setFirstName('John');
        $user->setLastName('Doe');

        $role->addUser($user);

        $this->assertCount(1, $role->getUsers());
    }

    public function testToString()
    {
        $role = new Role();
        $role->setName('ROLE_LIBRARIAN');

        $this->assertEquals('ROLE_LIBRARIAN', (string) $role);
    }
}