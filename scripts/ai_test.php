<?php
// scripts/ai_test.php

use App\Kernel;
use App\Entity\RenewalRequest;

require_once __DIR__ . '/../vendor/autoload.php';
// Load environment (.env) like front controller
// Minimal .env loader fallback (parses KEY=VALUE lines) if bootstrap.php is not available
if (file_exists(__DIR__ . '/../config/bootstrap.php')) {
    require __DIR__ . '/../config/bootstrap.php';
} else {
    $envFile = __DIR__ . '/../.env';
    if (is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // strip quotes
            if ((str_starts_with($v, '"') && str_ends_with($v, '"')) || (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
                $v = substr($v, 1, -1);
            }
            putenv(sprintf('%s=%s', $k, $v));
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

$env = getenv('APP_ENV') ?: 'dev';
$debug = getenv('APP_DEBUG') !== '0';

$kernel = new Kernel($env, $debug);
$kernel->boot();

$container = $kernel->getContainer();
$doctrine = $container->get('doctrine');
$repo = $doctrine->getRepository(RenewalRequest::class);

$id = $argv[1] ?? null;
if ($id) {
    $rr = $repo->find((int) $id);
    if (!$rr) {
        fwrite(STDERR, "RenewalRequest with id $id not found\n");
        exit(2);
    }
} else {
    $rr = $repo->findOneBy(['status' => RenewalRequest::STATUS_PENDING]);
    if (!$rr) {
        fwrite(STDERR, "No pending RenewalRequest found.\n");
        exit(3);
    }
}

// The service might be private/inlined in the compiled container. If so, instantiate manually.
if ($container->has(\App\Service\AIRenewalSuggester::class)) {
    /** @var \App\Service\AIRenewalSuggester $suggester */
    $suggester = $container->get(\App\Service\AIRenewalSuggester::class);
} else {
    $httpClient = null;
    if ($container->has('http_client')) {
        $httpClient = $container->get('http_client');
    } elseif (class_exists(\Symfony\Component\HttpClient\HttpClient::class)) {
        $httpClient = \Symfony\Component\HttpClient\HttpClient::create();
    }
    $cache = $container->has('cache.app') ? $container->get('cache.app') : ($container->has('cache.system') ? $container->get('cache.system') : null);
    $doctrine = $container->get('doctrine');
    $loanRepository = $doctrine->getRepository(\App\Entity\Loan::class);
    $loanRequestRepository = $doctrine->getRepository(\App\Entity\LoanRequest::class);
    $provider = $_ENV['AI_PROVIDER'] ?? ($_SERVER['AI_PROVIDER'] ?? 'auto');
    $openAiApiKey = $_ENV['OPENAI_API_KEY'] ?? ($_SERVER['OPENAI_API_KEY'] ?? '');
    $openAiModel = $_ENV['OPENAI_MODEL'] ?? ($_SERVER['OPENAI_MODEL'] ?? 'gpt-4o-mini');
    $geminiApiKey = $_ENV['GEMINI_API_KEY'] ?? ($_SERVER['GEMINI_API_KEY'] ?? '');
    $geminiModel = $_ENV['GEMINI_MODEL'] ?? ($_SERVER['GEMINI_MODEL'] ?? 'gemini-2.5-flash');
    $renewalDays = (int) ($container->hasParameter('app.loan.renewal_days') ? $container->getParameter('app.loan.renewal_days') : ($container->hasParameter('renewal_days') ? $container->getParameter('renewal_days') : 14));
    $maxRenewals = (int) ($container->hasParameter('app.loan.max_renewals') ? $container->getParameter('app.loan.max_renewals') : ($container->hasParameter('max_renewals') ? $container->getParameter('max_renewals') : 3));
    if ($container->has('logger')) {
        $logger = $container->get('logger');
    } else {
        $logger = new \Psr\Log\NullLogger();
    }

    $suggester = new \App\Service\AIRenewalSuggester(
        $httpClient,
        $cache,
        $loanRepository,
        $loanRequestRepository,
        $provider,
        $openAiApiKey,
        $openAiModel,
        $geminiApiKey,
        $geminiModel,
        $renewalDays,
        $maxRenewals,
        $logger
    );
}

try {
    $result = $suggester->getSuggestion($rr);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Exception: " . $e->getMessage() . "\n");
    exit(1);
}

$kernel->shutdown();
