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
        // 1. Landings Table
        Schema::create('landings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('slug')->index();
            $table->string('title')->nullable();
            
            $table->jsonb('theme')->default('{}');
            $table->jsonb('blocks')->default('[]');
            
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // A tenant cannot have two landings with the same slug
            $table->unique(['tenant_id', 'slug']);
        });

        // 2. Landing Versions Table (Snapshots)
        Schema::create('landing_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('landing_id')->constrained('landings')->onDelete('cascade');
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            $table->jsonb('blocks_snapshot');
            $table->jsonb('theme_snapshot');
            
            $table->foreignUuid('published_by')->nullable()->constrained('central_users')->onDelete('set null');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_versions');
        Schema::dropIfExists('landings');
    }
};
