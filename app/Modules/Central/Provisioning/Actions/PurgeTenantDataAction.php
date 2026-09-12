<?php

declare(strict_types=1);

namespace App\Modules\Central\Provisioning\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final readonly class PurgeTenantDataAction
{
    /**
     * Tables that contain tenant_id and must be purged before tenants row is removed.
     * Order matters: leaves first, then roots. Tables without the column
     * (central-only) are skipped by the hasColumn guard; tables with a
     * DB-level ON DELETE CASCADE are still listed so the purge is
     * deterministic on every driver, not only where FKs fire.
     */
    private const TENANT_TABLES = [
        'tenant_api_keys',
        'tenant_settings',
        'tenant_audit_logs',
        'tenant_notifications',
        'tenant_invitations',
        'tenant_sessions',
        'tenant_sso_settings',
        'user_mfa',
        'passkeys',
        'tenant_user_impersonation_tokens',
        'model_has_permissions',
        'model_has_roles',
        'roles',
        'landings',
        'landing_versions',
        'broadcast_tenant',
        'broadcast_dismissals',
        'support_notes',
        'support_sessions',
        'provisioning_logs',
        'activity_log',
        'domains',
        'subscriptions',
        'payments',
        'payment_attempts',
        'payment_webhooks',
        'payment_references',
        'invoices',
    ];

    public function execute(string $tenantId): void
    {
        Log::info('PurgeTenantDataAction: purging tenant data', ['tenant_id' => $tenantId]);

        DB::transaction(function () use ($tenantId) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT set_config('app.tenant_id', ?, true)", [$tenantId]);
            }

            foreach (self::TENANT_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                // Check if table has tenant_id column to avoid errors on central-only tables
                if (Schema::hasColumn($table, 'tenant_id')) {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                }
            }

            // Purge users (tenant-scoped users table may be named users with tenant_id)
            foreach (['users', 'tenant_users'] as $userTable) {
                if (Schema::hasTable($userTable) && Schema::hasColumn($userTable, 'tenant_id')) {
                    DB::table($userTable)->where('tenant_id', $tenantId)->delete();
                }
            }
        });

        Log::info('PurgeTenantDataAction: completed', ['tenant_id' => $tenantId]);
    }
}
