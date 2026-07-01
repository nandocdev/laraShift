<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_encryption_keys ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE tenant_encryption_keys FORCE ROW LEVEL SECURITY');

            DB::statement("
                DROP POLICY IF EXISTS tenant_isolation_tenant_encryption_keys ON tenant_encryption_keys
            ");

            DB::statement("
                CREATE POLICY tenant_isolation_tenant_encryption_keys ON tenant_encryption_keys
                USING (tenant_id::text = current_setting('app.tenant_id'))
                WITH CHECK (tenant_id::text = current_setting('app.tenant_id'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_encryption_keys NOFORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE tenant_encryption_keys DISABLE ROW LEVEL SECURITY');
        }
    }
};
