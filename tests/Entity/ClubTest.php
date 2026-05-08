<?php

namespace App\Tests\Entity;

use App\Entity\Club;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ClubStatus;
use PHPUnit\Framework\TestCase;

class ClubTest extends TestCase
{
    public function testConstructorInitializesDefaults(): void
    {
        $club = new Club();

        self::assertSame('', $club->getTitle());
        self::assertSame('', $club->getDescription());
        self::assertSame('', $club->getCategory());
        self::assertSame('', $club->getMeetingLocation());
        self::assertSame(0, $club->getCapacity());
        self::assertFalse($club->isPrivate());
        self::assertSame(ClubStatus::ACTIVE, $club->getStatus());
        self::assertCount(0, $club->getMembers());
        self::assertCount(0, $club->getOrganizedEvents());
    }

    public function testAddMemberDoesNotDuplicateSameUser(): void
    {
        $club = new Club();
        $user = new User();

        $club->addMember($user);
        $club->addMember($user);

        self::assertCount(1, $club->getMembers());
        self::assertTrue($club->isMember($user));
    }

    public function testRemoveMemberWorks(): void
    {
        $club = new Club();
        $user = new User();

        $club->addMember($user);
        $club->removeMember($user);

        self::assertCount(0, $club->getMembers());
        self::assertFalse($club->isMember($user));
    }

    public function testAvailableSpotsAndIsFull(): void
    {
        $club = (new Club())->setCapacity(2);
        $user1 = new User();
        $user2 = new User();

        self::assertSame(2, $club->getAvailableSpots());
        self::assertFalse($club->isFull());

        $club->addMember($user1);
        self::assertSame(1, $club->getAvailableSpots());
        self::assertFalse($club->isFull());

        $club->addMember($user2);
        self::assertSame(0, $club->getAvailableSpots());
        self::assertTrue($club->isFull());
    }

    public function testCanJoinDependsOnStatusAndCapacity(): void
    {
        $club = (new Club())->setCapacity(1);
        $user = new User();

        self::assertTrue($club->canJoin());

        $club->addMember($user);
        self::assertFalse($club->canJoin());

        $club->removeMember($user);
        $club->setStatus(ClubStatus::PAUSED);
        self::assertFalse($club->canJoin());
    }

    public function testStatusHelpersChangeStatus(): void
    {
        $club = new Club();

        $club->deactivate();
        self::assertSame(ClubStatus::INACTIVE, $club->getStatus());

        $club->pause();
        self::assertSame(ClubStatus::PAUSED, $club->getStatus());

        $club->archive();
        self::assertSame(ClubStatus::ARCHIVED, $club->getStatus());

        $club->activate();
        self::assertSame(ClubStatus::ACTIVE, $club->getStatus());
    }

    public function testAddOrganizedEventSynchronizesBothSides(): void
    {
        $club = new Club();
        $event = new Event();

        $club->addOrganizedEvent($event);

        self::assertTrue($club->isOrganizingEvent($event));
        self::assertSame(1, $club->getEventCount());
        self::assertTrue($event->isOrganizedByClub($club));
    }

    public function testUpcomingAndPastAndNextEvent(): void
    {
        $club = new Club();

        $pastEvent = (new Event())
            ->setStartDateTime(new \DateTimeImmutable('-3 days'))
            ->setEndDateTime(new \DateTimeImmutable('-2 days'));

        $nearUpcoming = (new Event())
            ->setStartDateTime(new \DateTimeImmutable('+1 day'))
            ->setEndDateTime(new \DateTimeImmutable('+1 day +2 hours'));

        $farUpcoming = (new Event())
            ->setStartDateTime(new \DateTimeImmutable('+5 days'))
            ->setEndDateTime(new \DateTimeImmutable('+5 days +2 hours'));

        $club->addOrganizedEvent($pastEvent);
        $club->addOrganizedEvent($farUpcoming);
        $club->addOrganizedEvent($nearUpcoming);

        self::assertCount(2, $club->getUpcomingEvents());
        self::assertCount(1, $club->getPastEvents());
        self::assertSame($nearUpcoming, $club->getNextEvent());
    }

    public function testGetNextEventReturnsNullWhenNoUpcomingEvents(): void
    {
        $club = new Club();
        $pastEvent = (new Event())
            ->setStartDateTime(new \DateTimeImmutable('-2 days'))
            ->setEndDateTime(new \DateTimeImmutable('-1 day'));

        $club->addOrganizedEvent($pastEvent);

        self::assertNull($club->getNextEvent());
    }
}

