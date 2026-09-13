<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outgoing partner webhooks per tenant (RF9.2 / UC-T-06).
     */
    public function up(): void
    {
        Schema::create('tenant_webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('url', 2000);
            $table->jsonb('events');
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        Schema::create('tenant_webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('endpoint_id')->constrained('tenant_webhook_endpoints')->onDelete('cascade');
            $table->string('event_type', 100);
            $table->jsonb('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->integer('response_status')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['tenant_webhook_endpoints', 'tenant_webhook_deliveries'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;");
                DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_webhook_deliveries');
        Schema::dropIfExists('tenant_webhook_endpoints');
    }
};
