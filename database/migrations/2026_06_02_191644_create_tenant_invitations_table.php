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
        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('email');
            $table->uuid('role_id')->nullable();
            $table->string('token_hash');
            $table->uuid('invited_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('token_hash');
            $table->unique(['tenant_id', 'email', 'accepted_at']); // One active invite per email per tenant
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
