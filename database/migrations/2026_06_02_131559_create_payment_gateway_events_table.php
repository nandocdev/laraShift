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
        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gateway_event_id')->unique(); // For idempotency
            $table->string('gateway'); // stripe, dlocal
            $table->string('event_type');
            $table->jsonb('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
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
