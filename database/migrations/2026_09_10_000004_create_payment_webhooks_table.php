<?php

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
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('gateway');
            $table->string('gateway_reference')->unique();
            $table->string('display_id')->nullable()->index();
            $table->string('status');
            $table->integer('amount_cents')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payment_webhooks ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE payment_webhooks FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON payment_webhooks USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
