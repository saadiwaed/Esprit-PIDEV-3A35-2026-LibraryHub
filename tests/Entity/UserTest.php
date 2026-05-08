<?php

namespace App\Tests\Entity;

use App\Entity\Club;
use App\Entity\ReadingProfile;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructorInitializesDefaults(): void
    {
        $user = new User();

        self::assertSame('', $user->getEmail());
        self::assertSame('', $user->getPassword());
        self::assertSame('', $user->getFirstName());
        self::assertSame('', $user->getLastName());
        self::assertSame('PENDING', $user->getStatus());
        self::assertFalse($user->isPremium());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertCount(0, $user->getUserRoles());
        self::assertCount(0, $user->getClubs());
        self::assertNull($user->getReadingProfile());
    }

    public function testUserIdentifierFullNameAndToString(): void
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setFirstName('Aziz')
            ->setLastName('Arfaoui');

        self::assertSame('user@example.com', $user->getUserIdentifier());
        self::assertSame('Aziz Arfaoui', $user->getFullName());
        self::assertSame('Aziz Arfaoui', (string) $user);
    }

    public function testRolesManagementAndHasRole(): void
    {
        $user = new User();
        $adminRole = (new Role())->setName('ROLE_ADMIN');

        $user->addRole($adminRole);
        $user->addRole($adminRole);

        self::assertCount(1, $user->getUserRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertTrue($user->hasRole('ROLE_ADMIN'));

        $user->removeRole($adminRole);
        self::assertFalse($user->hasRole('ROLE_ADMIN'));
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testSetUserRolesUsesCollection(): void
    {
        $user = new User();
        $memberRole = (new Role())->setName('ROLE_MEMBER');
        $roles = new ArrayCollection([$memberRole]);

        $user->setUserRoles($roles);

        self::assertCount(1, $user->getUserRoles());
        self::assertContains('ROLE_MEMBER', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testSetReadingProfileSynchronizesOwningSide(): void
    {
        $user = new User();
        $profile = new ReadingProfile();

        $user->setReadingProfile($profile);

        self::assertSame($profile, $user->getReadingProfile());
        self::assertSame($user, $profile->getUser());
    }

    public function testAddAndRemoveClubSynchronizeBothSides(): void
    {
        $user = new User();
        $club = new Club();

        $user->addClub($club);

        self::assertCount(1, $user->getClubs());
        self::assertTrue($club->isMember($user));

        $user->removeClub($club);

        self::assertCount(0, $user->getClubs());
        self::assertFalse($club->isMember($user));
    }

    public function testFaceDescriptorSupportsArrayAndStringAndNull(): void
    {
        $user = new User();

        $user->setFaceDescriptor([0.12, 0.34, '0.56']);
        self::assertSame('0.12,0.34,0.56', $user->getFaceDescriptor());

        $user->setFaceDescriptor('0.1,0.2');
        self::assertSame('0.1,0.2', $user->getFaceDescriptor());

        $user->setFaceDescriptor(null);
        self::assertNull($user->getFaceDescriptor());
    }
}

