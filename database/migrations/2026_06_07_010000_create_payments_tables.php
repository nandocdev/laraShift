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
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('display_id');        // Your order/invoice ID
            $table->string('slug')->unique();    // Sent to gateway
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('description');
            $table->string('email');
            $table->string('currency', 3)->default('USD');
            $table->string('status');            // PaymentStatus enum value
            $table->string('gateway');           // e.g. 'clave'
            $table->string('gateway_reference')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();

            // RLS-supporting indexes
            $table->index(['tenant_id', 'id']);
            $table->index(['tenant_id', 'display_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('payment_id');
            $table->string('slug');
            $table->string('status');
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'payment_id']);
            $table->index(['tenant_id', 'id']);

            $table->foreign('payment_id')->references('id')->on('payments')->cascadeOnDelete();
        });

        Schema::create('payment_webhooks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('gateway_reference');
            $table->string('display_id');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('gateway_code');
            $table->string('authorization_code')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->text('raw_payload');         // Immutable — preserve verbatim
            $table->timestamp('created_at')->useCurrent();
            // No updated_at intentionally — webhooks are append-only

            // Idempotency unique constraint
            $table->unique(['tenant_id', 'gateway_reference']);

            $table->index(['tenant_id', 'display_id']);
            $table->index(['tenant_id', 'id']);
        });

        // RLS policies — tenant isolation at the DB layer
        // These complement (never replace) the Eloquent TenantScope
        DB::statement("ALTER TABLE payments ENABLE ROW LEVEL SECURITY;");
        DB::statement("ALTER TABLE payment_attempts ENABLE ROW LEVEL SECURITY;");
        DB::statement("ALTER TABLE payment_webhooks ENABLE ROW LEVEL SECURITY;");

        // Policies are applied per-connection in the tenancy middleware via SET app.tenant_id
        DB::statement("CREATE POLICY tenant_isolation ON payments USING (tenant_id = current_setting('app.tenant_id')::uuid);");
        DB::statement("CREATE POLICY tenant_isolation ON payment_attempts USING (tenant_id = current_setting('app.tenant_id')::uuid);");
        DB::statement("CREATE POLICY tenant_isolation ON payment_webhooks USING (tenant_id = current_setting('app.tenant_id')::uuid);");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
