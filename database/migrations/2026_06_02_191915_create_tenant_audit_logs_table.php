<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->string('action'); // 'user.created', 'role.updated', etc.
            $table->string('resource')->nullable();
            $table->uuid('resource_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_audit_logs ENABLE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON tenant_audit_logs USING (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_audit_logs');
    }
};
