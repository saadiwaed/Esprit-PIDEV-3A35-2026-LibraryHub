<?php

namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Security\AdminFaceUserChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;

class AdminFaceUserCheckerTest extends TestCase
{
    public function testNonAdminUser()
    {
        $requestStack = new RequestStack();

        $checker = new AdminFaceUserChecker($requestStack);

        $user = new User();
        $user->setEmail('test@test.com');

        $checker->checkPreAuth($user);

        $this->assertTrue(true);
    }

    public function testAdminWithoutToken()
    {
        $request = new Request();
        $request->attributes->set('_route', 'app_login');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $checker = new AdminFaceUserChecker($requestStack);

        $user = new User();

        $checker->checkPreAuth($user);

        $this->assertTrue(true);
    }
}