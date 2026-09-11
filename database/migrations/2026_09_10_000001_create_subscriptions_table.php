<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Depends on the plans table (Fase 1b, filename 000000 sorts first,
     * so it is always created before this migration runs).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('status');
            $table->string('gateway');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->string('pm_card_id')->nullable();
            $table->timestamp('renewal_link_sent_at')->nullable();
            $table->timestamp('renewal_reminder_sent_at')->nullable();
            $table->timestamp('renewal_link_expires_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY;');
            DB::statement('ALTER TABLE subscriptions FORCE ROW LEVEL SECURITY;');
            DB::statement("CREATE POLICY tenant_isolation ON subscriptions USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
