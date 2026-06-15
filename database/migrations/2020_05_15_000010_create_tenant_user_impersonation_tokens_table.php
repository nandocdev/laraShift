<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantUserImpersonationTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenant_user_impersonation_tokens', function (Blueprint $table) {
            $table->string('token', 128)->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->string('auth_guard');
            $table->string('redirect_url');
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });

        // Enable RLS
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE tenant_user_impersonation_tokens ENABLE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE tenant_user_impersonation_tokens FORCE ROW LEVEL SECURITY;");
            \Illuminate\Support\Facades\DB::statement("CREATE POLICY tenant_isolation ON tenant_user_impersonation_tokens USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_user_impersonation_tokens');
    }
}
