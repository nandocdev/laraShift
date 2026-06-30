<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('tenant_id')->nullable()->index();
            $table->string('correlation_id')->nullable()->index();
            $table->string('causer_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
