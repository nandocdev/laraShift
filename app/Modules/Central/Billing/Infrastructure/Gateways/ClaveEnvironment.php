<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Infrastructure\Gateways;

enum ClaveEnvironment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
    case Dev = 'dev';

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://secure.paguelofacil.com',
            self::Sandbox => 'https://sandbox.paguelofacil.com',
            self::Dev => 'https://middleapidev.pfserver.net',
        };
    }

    public static function fromConfig(): self
    {
        return match (config('clave.environment', 'sandbox')) {
            'production' => self::Production,
            'dev' => self::Dev,
            default => self::Sandbox,
        };
    }
}
