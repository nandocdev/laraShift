<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Support;

use Plinth\MultiTenantBilling\Contracts\BillingProvider;
use Plinth\MultiTenantBilling\Contracts\PaymentProvider;
use Plinth\MultiTenantBilling\Core\Factories\PaymentProviderFactory;

/**
 * Resuelve el PaymentProvider/BillingProvider de Plinth usando
 * credenciales de plataforma (config/plinth.php) en lugar de buscar
 * por tenant_id en plinth_tenant_payment_providers.
 *
 * Uso exclusivo durante el flujo de registro, donde el tenant aún no existe.
 *
 * @see \Plinth\MultiTenantBilling\Core\Factories\PaymentProviderFactory
 *
 * [RIESGOS]
 * - Si PLINTH_SECRET_KEY no está configurada, falla con excepción explícita.
 * - Credenciales viajan en memoria; nunca se persisten en logs.
 */
final readonly class RegisterPaymentGatewayResolver
{
    public function __construct(
        private PaymentProviderFactory $factory
    ) {}

    /**
     * Resuelve el PaymentProvider de plataforma para tokenización/checkout.
     *
     * @return PaymentProvider
     * @throws \RuntimeException Si las credenciales no están configuradas
     */
    public function resolvePaymentProvider(): PaymentProvider
    {
        return $this->factory->buildPaymentProvider(
            $this->getProvider(),
            $this->getCredentials()
        );
    }

    /**
     * Resuelve el BillingProvider de plataforma para crear suscripciones.
     *
     * @return BillingProvider
     * @throws \RuntimeException Si las credenciales no están configuradas
     */
    public function resolveBillingProvider(): BillingProvider
    {
        return $this->factory->buildBillingProvider(
            $this->getProvider(),
            $this->getCredentials()
        );
    }

    /**
     * Devuelve el nombre del proveedor y las credenciales como array,
     * para copiarlas a plinth_tenant_payment_providers del nuevo tenant.
     *
     * @return array{provider: string, credentials: array}
     */
    public function getPlatformConfig(): array
    {
        return [
            'provider'    => $this->getProvider(),
            'credentials' => $this->getCredentials(),
        ];
    }

    private function getProvider(): string
    {
        $provider = config('plinth.platform_provider');

        if (empty($provider)) {
            throw new \RuntimeException(
                'PLINTH_PLATFORM_PROVIDER no está configurado. Revisa config/plinth.php.'
            );
        }

        return $provider;
    }

    /**
     * @return array<string, string|null>
     */
    private function getCredentials(): array
    {
        $credentials = config('plinth.platform_credentials', []);

        if (empty($credentials['secret_key'])) {
            throw new \RuntimeException(
                'PLINTH_SECRET_KEY no está configurada. Revisa tu archivo .env.'
            );
        }

        return $credentials;
    }
}
