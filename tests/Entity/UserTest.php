<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;
use App\Entity\User;
use App\Entity\Role;

class UserTest extends TestCase
{
    public function testUserCreation()
    {
        $user = new User();

        $user->setEmail('test@test.com');
        $user->setPassword('hashedpassword');
        $user->setFirstName('John');
        $user->setLastName('Doe');

        $this->assertEquals('test@test.com', $user->getEmail());
        $this->assertEquals('John', $user->getFirstName());
        $this->assertEquals('Doe', $user->getLastName());
        $this->assertEquals('John Doe', $user->getFullName());
    }

    public function testRoles()
    {
        $user = new User();

        $role = new Role();
        $role->setName('ROLE_ADMIN');

        $user->addRole($role);

        $this->assertTrue($user->hasRole('ROLE_ADMIN'));
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testDefaultRole()
    {
        $user = new User();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testFaceDescriptorArray()
    {
        $user = new User();

        $user->setFaceDescriptor([0.1, 0.2, 0.3]);

        $this->assertEquals('0.1,0.2,0.3', $user->getFaceDescriptor());
    }

    public function testPremium()
    {
        $user = new User();

        $user->setIsPremium(true);

        $this->assertTrue($user->isPremium());
    }

    public function testToString()
    {
        $user = new User();

        $user->setFirstName('Alice');
        $user->setLastName('Smith');

        $this->assertEquals('Alice Smith', (string) $user);
    }
}