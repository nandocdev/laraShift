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
        Schema::create('usage_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('meter', 100);
            $table->integer('quantity')->default(1);
            $table->timestamp('occurred_at');
            $table->jsonb('metadata')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->uuid('subscription_item_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'meter', 'occurred_at']);
            $table->unique(['tenant_id', 'dedupe_key']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE usage_events ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE usage_events FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON usage_events USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
