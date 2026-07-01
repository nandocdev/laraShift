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
        Schema::create('tenant_webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('webhook_id');
            $table->string('event');
            $table->jsonb('payload');
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'webhook_id', 'created_at']);
            $table->index(['tenant_id', 'status', 'next_retry_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('webhook_id')->references('id')->on('tenant_webhooks')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_webhook_deliveries ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE tenant_webhook_deliveries FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON tenant_webhook_deliveries USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_webhook_deliveries');
    }
};
