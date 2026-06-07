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
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email'); // Tenant owner email
            
            // Lifecycle & Status
            $table->string('status')->default('active'); // active, suspended, archived
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('read_only')->default(false);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();

            // Billing
            $table->string('plan_id')->default('free');
            $table->string('billing_gateway')->default('dlocal'); // default per plan-mode strategy
            
            $table->timestamps();
            $table->softDeletes();
            $table->json('data')->nullable();

            $table->unique(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
