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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('slug')->unique();
            $table->string('display_id')->index();
            $table->integer('amount_cents');
            $table->char('currency', 3);
            $table->string('status');
            $table->string('gateway');
            $table->string('gateway_reference')->nullable()->index();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->jsonb('provider_metadata')->default('{}');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE payments FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON payments USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
