<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Central table (no tenant_id): the tenant is resolved from the event
     * itself via provider_customer_id → payment_references. No RLS.
     */
    public function up(): void
    {
        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gateway');
            $table->string('gateway_event_id');
            $table->string('event_type');
            $table->jsonb('payload')->default('{}');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->unique(['gateway', 'gateway_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_events');
    }
};
