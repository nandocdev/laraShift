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
     * Order matters: leaves first, then roots.
     */
    private const TENANT_TABLES = [
        'quota_snapshots',
        'usage_events',
        'usage_rollups',
        'tenant_api_keys',
        'tenant_settings',
        'provisioning_logs',
        'payment_webhooks',
        'payment_attempts',
        'payment_references',
        'payments',
        'invoices',
        'subscriptions',
        'subscription_items',
        'activity_log',
        'domains',
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
