<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

if (file_exists(__DIR__.'/.env') && method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(__DIR__.'/.env');
}

if (!getenv('APP_ENV')) {
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
    $_SERVER['APP_ENV'] = 'test';
}

if (!getenv('DATABASE_URL')) {
    $databaseUrl = 'sqlite:///:memory:';
    putenv('DATABASE_URL='.$databaseUrl);
    $_ENV['DATABASE_URL'] = $databaseUrl;
    $_SERVER['DATABASE_URL'] = $databaseUrl;
}
