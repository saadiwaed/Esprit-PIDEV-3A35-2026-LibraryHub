<?php
// tests/doctrine-bootstrap.php

use App\Kernel;
use Doctrine\Persistence\ObjectManager;

require dirname(__DIR__).'/vendor/autoload.php';

// Bootstrap Symfony
$kernel = new Kernel('test', true);
$kernel->boot();

// Retourne l'ObjectManager
$container = $kernel->getContainer();
return $container->get('doctrine.orm.entity_manager');