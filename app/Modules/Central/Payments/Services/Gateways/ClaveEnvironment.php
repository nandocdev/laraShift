<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Services\Gateways;

enum ClaveEnvironment: string
{
    case Production = 'production';
    case Sandbox    = 'sandbox';
    case Dev        = 'dev';

    /**
     * Hosted Fields / REST API base URL.
     * Used for server-side calls (loadMerchantServices, etc.)
     */
    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://secure.paguelofacil.com/HostedFields',
            self::Sandbox    => 'https://sandbox.paguelofacil.com/HostedFields',
            self::Dev        => 'https://middleapidev.pfserver.net/HostedFields',
        };
    }

    /**
     * Checkout frontend base URL.
     * Used to build the iframe src for the hosted payment widget.
     */
    public function checkoutBaseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://checkout.paguelofacil.com',
            self::Sandbox    => 'https://sandbox.paguelofacil.com',
            self::Dev        => 'https://checkout-demo.paguelofacil.com',
        };
    }

    public static function fromConfig(): self
    {
        return match (config('payments.clave.environment', 'sandbox')) {
            'production' => self::Production,
            'dev'        => self::Dev,
            default      => self::Sandbox,
        };
    }
}
