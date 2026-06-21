<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Consolidating all billing related tables into local migrations 
        // to avoid external package conflicts and ensure UUID/RLS compatibility.

        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('price_monthly')->default(0);
            $table->integer('price_yearly')->default(0);
            
            // Decimal counterparts for easier external integrations
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('interval')->default('month');
            $table->integer('interval_count')->default(1);
            
            $table->string('provider_plan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('features')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id');
            $table->string('provider_subscription_id')->unique();
            $table->string('status'); // active, cancelled, etc.
            $table->string('gateway')->default('stripe');
            $table->string('external_id')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id');
            $table->string('name');
            $table->integer('amount')->default(0);
            $table->integer('quantity')->default(1);
            
            // Metered billing support
            $table->string('meter_id')->nullable();
            $table->string('meter_event_name')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id')->nullable();
            $table->string('provider_invoice_id')->nullable();
            $table->integer('amount');
            $table->string('currency', 3)->default('USD');
            $table->string('status'); // paid, pending, open
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('type'); // CREDIT, DEBIT
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('description');
            $table->nullableUuidMorphs('reference'); // External reference
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Enable RLS
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            foreach (['subscriptions', 'subscription_items', 'invoices', 'ledger_entries'] as $table) {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;");
                \Illuminate\Support\Facades\DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (tenant_id::text = current_setting('app.tenant_id')) WITH CHECK (tenant_id::text = current_setting('app.tenant_id'));");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
