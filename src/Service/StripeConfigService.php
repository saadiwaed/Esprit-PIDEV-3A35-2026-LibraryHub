<?php

namespace App\Service;

use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Stripe;

/**
 * Fournit les clés et Price IDs Stripe.
 * Si STRIPE_PRICE_MONTHLY/ANNUAL sont vides, crée les prix via l'API et les met en cache.
 */
final class StripeConfigService
{
    private const CACHE_FILE = 'stripe_prices.json';

    private ?string $lastErrorMessage = null;

    public function __construct(
        private string $stripeSecretKey,
        private string $stripePriceMonthly,
        private string $stripePriceAnnual,
        private string $projectDir,
    ) {
    }

    public function getSecretKey(): string
    {
        return $this->stripeSecretKey;
    }

    public function isConfigured(): bool
    {
        return $this->stripeSecretKey !== '';
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    /**
     * Retourne le Price ID pour le plan (monthly ou annual).
     * Crée le prix via l'API Stripe si nécessaire et met en cache.
     */
    public function getPriceId(string $plan): string
    {
        if ($plan === 'annual') {
            $envId = $this->stripePriceAnnual;
        } else {
            $envId = $this->stripePriceMonthly;
        }

        if ($envId !== '') {
            return $envId;
        }

        $cache = $this->loadCache();
        $key = $plan === 'annual' ? 'annual' : 'monthly';
        if (isset($cache[$key]) && $cache[$key] !== '') {
            return $cache[$key];
        }

        if ($this->stripeSecretKey === '') {
            return '';
        }

        $this->lastErrorMessage = null;
        $priceId = $this->createPriceViaApi($plan);
        if ($priceId !== '') {
            $cache[$key] = $priceId;
            $this->saveCache($cache);
        }

        return $priceId;
    }

    private function getCachePath(): string
    {
        return $this->projectDir . '/var/' . self::CACHE_FILE;
    }

    /** @return array{monthly?: string, annual?: string} */
    private function loadCache(): array
    {
        $path = $this->getCachePath();
        if (!is_file($path)) {
            return [];
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /** @param array{monthly?: string, annual?: string} $cache */
    private function saveCache(array $cache): void
    {
        $dir = $this->projectDir . '/var';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $this->getCachePath();
        file_put_contents($path, json_encode($cache, JSON_PRETTY_PRINT));
    }

    private function createPriceViaApi(string $plan): string
    {
        Stripe::setApiKey($this->stripeSecretKey);

        try {
            if ($plan === 'annual') {
                $price = Price::create([
                    'currency' => 'eur',
                    'unit_amount' => 8999, // 89,99 €
                    'recurring' => ['interval' => 'year'],
                    'product_data' => [
                        'name' => 'LibraryHub – Offre Annuelle',
                    ],
                ]);
            } else {
                $price = Price::create([
                    'currency' => 'eur',
                    'unit_amount' => 999, // 9,99 €
                    'recurring' => ['interval' => 'month'],
                    'product_data' => [
                        'name' => 'LibraryHub – Offre Mensuelle',
                    ],
                ]);
            }
            return $price->id;
        } catch (ApiErrorException $e) {
            $this->lastErrorMessage = $e->getMessage();
            return '';
        }
    }
}
