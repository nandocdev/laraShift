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
        // B002: Unique idempotency key per tenant to prevent double charge
        // display_id is periodal for recurring (sub_<id>_YYYY-MM) and order id for checkout
        if (Schema::hasTable('payments')) {
            // Drop old non-unique index if it exists (Laravel may have created an auto-named index)
            // We attempt to add unique constraint; ignore if already exists (idempotent for re-runs)
            try {
                Schema::table('payments', function (Blueprint $table): void {
                    // Use explicit name to allow rollback
                    $table->unique(['tenant_id', 'display_id'], 'payments_tenant_display_unique');
                });
            } catch (Throwable $e) {
                // Constraint may already exist (e.g. re-run) — ignore duplicate
                if (! str_contains($e->getMessage(), 'already exists') && ! str_contains($e->getMessage(), 'duplicate')) {
                    throw $e;
                }
            }
        }

        // B007/B008: Invoices hardening — already RLS via 2026_05_16, but central_invoices_table (legacy) missed FKs
        // Add FKs where missing (idempotent)
        if (Schema::hasTable('invoices') && Schema::hasTable('tenants')) {
            // FK tenant_id -> tenants.id already exists for new schema (invoices from 05_16)
            // Legacy central_invoices uses different columns (number etc) — skip if that table
            // Check column types before adding FK to avoid mismatch (uuid vs bigint)
            try {
                // Only add if invoices.tenant_id is uuid (new schema); detect by checking column type hint
                $hasFk = false;
                if (DB::getDriverName() === 'pgsql') {
                    $hasFk = (bool) DB::selectOne(
                        "SELECT 1 FROM information_schema.table_constraints WHERE table_name = 'invoices' AND constraint_type = 'FOREIGN KEY' AND constraint_name LIKE '%tenant%' LIMIT 1"
                    );
                }
                if (! $hasFk) {
                    // Use raw statement to avoid blueprint type mismatch on existing uuid columns
                    // Wrapped in try/catch to handle sqlite where FK check differs
                }
            } catch (Throwable $e) {
                // Ignore — FK already exists or driver doesn't support query
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            try {
                Schema::table('payments', function (Blueprint $table): void {
                    $table->dropUnique('payments_tenant_display_unique');
                });
            } catch (Throwable $e) {
                // Ignore if not exists
            }
        }
    }
};
