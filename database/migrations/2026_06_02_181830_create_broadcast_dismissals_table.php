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
        Schema::create('broadcast_dismissals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('broadcast_id')->constrained('broadcasts')->onDelete('cascade');
            $table->string('tenant_id')->index(); // To allow querying by tenant context
            $table->uuid('user_id')->index(); // Specific user who dismissed
            $table->timestamp('dismissed_at');
            $table->timestamps();

            $table->unique(['broadcast_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcast_dismissals');
    }
};
