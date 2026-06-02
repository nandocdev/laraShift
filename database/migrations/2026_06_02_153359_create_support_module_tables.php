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
        // 1. Support Sessions (Impersonation)
        Schema::create('support_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('operator_id')->constrained('central_users')->onDelete('cascade');
            $table->text('reason');
            $table->string('token')->unique(); // One-time token for transition
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expires_at'); // started_at + 2h
            $table->timestamps();

            $table->index(['tenant_id', 'started_at']);
        });

        // 2. Support Notes
        Schema::create('support_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->foreignUuid('author_id')->constrained('central_users')->onDelete('cascade');
            $table->text('content');
            $table->timestamps();
        });

        // 3. Broadcasts
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->constrained('central_users')->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->enum('filter_type', ['all', 'plan', 'status']);
            $table->string('filter_value')->nullable();
            $table->jsonb('channels'); // e.g. ['email', 'banner']
            $table->timestamp('sent_at')->nullable();
            $table->integer('recipient_count')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('support_notes');
        Schema::dropIfExists('support_sessions');
    }
};
