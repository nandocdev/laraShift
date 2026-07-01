<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('subject');
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('medium');
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_breach_at')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['status', 'sla_breach_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('central_users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('central_users')->onDelete('cascade');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('author_id');
            $table->text('content');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('central_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
