<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services\Gateways;

enum ClaveEnvironment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
    case Dev = 'dev';

    /**
     * Hosted Fields / LinkDeamon base URL.
     */
    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://secure.paguelofacil.com',
            self::Sandbox => 'https://sandbox.paguelofacil.com',
            self::Dev => 'https://middleapidev.pfserver.net',
        };
    }

    /**
     * Management API base URL (Transactions, Customers, etc.)
     */
    public function managementBaseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://admin.paguelofacil.com/PFManagementServices/api/v1',
            self::Sandbox => 'https://sandbox.paguelofacil.com/PFManagementServices/api/v1',
            self::Dev => 'https://middleapidev.pfserver.net/PFManagementServices/api/v1',
        };
    }

    /**
     * Checkout frontend base URL.
     */
    public function checkoutBaseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://checkout.paguelofacil.com',
            self::Sandbox => 'https://sandbox.paguelofacil.com',
            self::Dev => 'https://checkout-demo.paguelofacil.com',
        };
    }

    public static function fromConfig(): self
    {
        return match (config('payments.clave.environment', 'sandbox')) {
            'production' => self::Production,
            'dev' => self::Dev,
            default => self::Sandbox,
        };
    }
}
