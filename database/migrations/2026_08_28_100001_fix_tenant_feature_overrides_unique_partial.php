<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // C004: unique(tenant_id,feature_id) must ignore soft-deleted rows
        // Replace table-level unique with partial unique index WHERE deleted_at IS NULL (PG) or drop/recreate for sqlite
        if (Schema::hasTable('tenant_feature_overrides')) {
            // Drop old unique constraint if exists (MySQL/Postgres name auto-generated)
            try {
                Schema::table('tenant_feature_overrides', function (Blueprint $table): void {
                    $table->dropUnique(['tenant_id', 'feature_id']);
                });
            } catch (Throwable $e) {
                // Constraint may have different name or already dropped
            }

            // Also try dropping by explicit name from previous migration (Laravel generates tenant_feature_overrides_tenant_id_feature_id_unique)
            try {
                DB::statement('DROP INDEX IF EXISTS tenant_feature_overrides_tenant_id_feature_id_unique');
            } catch (Throwable $e) {
            }
            try {
                DB::statement('DROP INDEX IF EXISTS tenant_feature_overrides_tenant_id_feature_id_unique_index');
            } catch (Throwable $e) {
            }

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS tenant_feature_overrides_tenant_feature_unique_partial ON tenant_feature_overrides (tenant_id, feature_id) WHERE deleted_at IS NULL');
            } else {
                // SQLite/MySQL: create normal unique (cannot do partial), but application handles via withTrashed()->restore() (C002)
                // For sqlite tests, keep simple unique to catch duplicates; the code path uses withTrashed so no violation
                try {
                    Schema::table('tenant_feature_overrides', function (Blueprint $table): void {
                        $table->unique(['tenant_id', 'feature_id'], 'tenant_feature_overrides_tenant_feature_unique');
                    });
                } catch (Throwable $e) {
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenant_feature_overrides')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS tenant_feature_overrides_tenant_feature_unique_partial');
            } else {
                try {
                    Schema::table('tenant_feature_overrides', function (Blueprint $table): void {
                        $table->dropUnique('tenant_feature_overrides_tenant_feature_unique');
                    });
                } catch (Throwable $e) {
                }
            }

            try {
                Schema::table('tenant_feature_overrides', function (Blueprint $table): void {
                    $table->unique(['tenant_id', 'feature_id']);
                });
            } catch (Throwable $e) {
            }
        }
    }
};
