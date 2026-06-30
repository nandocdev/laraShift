<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_encryption_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('key_identifier', 64);
            $table->text('encrypted_key');
            $table->string('purpose', 50)->default('at_rest');
            $table->boolean('is_active')->default(true);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->uuid('rotated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'purpose', 'is_active']);
            $table->index(['tenant_id', 'expires_at']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_encryption_keys');
    }
};
