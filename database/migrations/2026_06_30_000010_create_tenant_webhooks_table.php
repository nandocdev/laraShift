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
        Schema::create('tenant_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('url');
            $table->string('secret');
            $table->jsonb('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('max_retries')->default(5);
            $table->unsignedSmallInteger('timeout_seconds')->default(5);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_webhooks ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE tenant_webhooks FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON tenant_webhooks USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_webhooks');
    }
};
