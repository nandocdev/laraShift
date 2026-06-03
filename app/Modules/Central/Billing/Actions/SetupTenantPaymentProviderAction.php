<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Actions;

use App\Modules\Central\Billing\Support\RegisterPaymentGatewayResolver;
use App\Modules\Central\Provisioning\Models\Tenant;
use Plinth\MultiTenantBilling\Core\Models\TenantPaymentProvider;

/**
 * Copia las credenciales de plataforma al registro plinth_tenant_payment_providers
 * del tenant recién creado, permitiéndole operar con Plinth de forma autónoma.
 *
 * [RIESGOS]
 * - Si ya existe un registro para el tenant, se sobrescribe (updateOrCreate).
 * - Las credenciales se almacenan encriptadas en DB vía cast 'array' → json.
 *   Considerar migrar a encrypted cast si la DB no tiene encryption at rest.
 */
final readonly class SetupTenantPaymentProviderAction
{
    public function __construct(
        private RegisterPaymentGatewayResolver $resolver
    ) {}

    /**
     * Persiste la configuración del gateway de plataforma para el tenant.
     *
     * @param Tenant $tenant Tenant recién provisionado
     * @return TenantPaymentProvider
     */
    public function execute(Tenant $tenant): TenantPaymentProvider
    {
        $config = $this->resolver->getPlatformConfig();

        return TenantPaymentProvider::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'provider'    => $config['provider'],
                'credentials' => $config['credentials'],
                'status'      => 'active',
            ]
        );
    }
}
