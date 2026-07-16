<?php

namespace Database\Seeders;

use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantDataSeeder extends Seeder
{
    /**
     * Run the database seeds for a single tenant.
     *
     * Creacion de roles delegada a EnsureTenantRolesExist (invocado
     * via TenantProvisioned event → CreateInitialAdminUser listener)
     * para evitar duplicacion con nombres inconsistentes.
     */
    public function run(?string $tenantId = null): void
    {
        TenantSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Default Settings',
                'primary_color' => '#3b82f6',
                'timezone' => 'UTC',
                'locale' => 'en',
                'currency' => 'USD',
                'mfa_required' => false,
            ]
        );
    }
}
