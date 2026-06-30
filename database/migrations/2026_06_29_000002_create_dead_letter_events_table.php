<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dead_letter_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('outbox_event_id')->nullable()->index();
            $table->string('event_type');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('tenant_id')->nullable()->index();
            $table->string('correlation_id')->nullable();
            $table->json('payload');
            $table->json('error_log')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(5);
            $table->string('status')->default('failed')->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_letter_events');
    }
};
