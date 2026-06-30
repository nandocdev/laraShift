<?php

namespace Database\Seeders;

use App\Modules\Tenant\Identity\Models\Role;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantDataSeeder extends Seeder
{
    /**
     * Run the database seeds for a single tenant.
     */
    public function run(?string $tenantId = null): void
    {
        // If no tenantId provided, we assume we are running in a context where it's already set
        // but for provisioning, we explicitly pass it.

        // 1. Create Default Roles
        $roles = [
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Owner',
                'guard_name' => 'web',
                'is_system' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Administrator',
                'guard_name' => 'web',
                'is_system' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'name' => 'Member',
                'guard_name' => 'web',
                'is_system' => false,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                [
                    'name' => $roleData['name'],
                    'guard_name' => $roleData['guard_name'],
                    'tenant_id' => $tenantId,
                ],
                $roleData
            );
        }

        // 2. Create Default Settings
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
