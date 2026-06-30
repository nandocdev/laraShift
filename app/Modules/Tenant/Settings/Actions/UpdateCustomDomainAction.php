<?php

declare(strict_types=1);

namespace App\Modules\Tenant\Settings\Actions;

use App\Modules\Central\Provisioning\Actions\VerifyCustomDomainAction;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\TenantSettingsUpdated;
use Illuminate\Support\Facades\Log;

final readonly class UpdateCustomDomainAction
{
    public function __construct(
        private VerifyCustomDomainAction $verifyDomain,
    ) {}

    /**
     * Set a custom domain for the tenant.
     *
     * @return array{domain: string, verified: bool, dns_check: array}
     */
    public function execute(string $domain): array
    {
        $tenant = tenant();

        $existing = $tenant->domains()->where('domain', $domain)->first();

        if ($existing && $existing->verified_at) {
            return [
                'domain' => $domain,
                'verified' => true,
                'dns_check' => ['verified' => true],
            ];
        }

        $dnsResult = $this->verifyDomain->verify($tenant, $domain);

        if (! $dnsResult['verified']) {
            $tenant->domains()->updateOrCreate(
                ['domain' => $domain],
                ['tenant_id' => $tenant->id],
            );
        }

        event(new TenantSettingsUpdated(tenant('id'), ['custom_domain']));

        return [
            'domain' => $domain,
            'verified' => $dnsResult['verified'],
            'dns_check' => $dnsResult,
        ];
    }

    /**
     * Remove a custom domain from the tenant.
     */
    public function remove(string $domain): void
    {
        $tenant = tenant();

        $tenant->domains()->where('domain', $domain)->delete();

        activity('settings')
            ->performedOn($tenant)
            ->withProperties(['domain' => $domain])
            ->log('custom_domain_removed');

        Log::info("Custom domain removed: {$domain}", ['tenant_id' => tenant('id')]);
    }
}
