<?php

declare(strict_types=1);

/**
 * Configuración de plataforma para Plinth Multi-Tenant Billing.
 *
 * Define las credenciales del gateway de pagos a nivel de plataforma,
 * utilizadas durante el registro de tenants (antes de que exista un
 * registro en plinth_tenant_payment_providers).
 */
return [
    'platform_provider' => env('PLINTH_PLATFORM_PROVIDER', 'stripe'),

    'platform_credentials' => [
        'secret_key'      => env('PLINTH_SECRET_KEY'),
        'publishable_key' => env('PLINTH_PUBLISHABLE_KEY'),
        'webhook_secret'  => env('PLINTH_WEBHOOK_SECRET'),
    ],
];
