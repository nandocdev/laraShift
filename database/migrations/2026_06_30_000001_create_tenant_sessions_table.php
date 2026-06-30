<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('user_id');
            $table->string('session_id')->unique()->index();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('refresh_token_hash')->nullable()->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sessions');
    }
};
