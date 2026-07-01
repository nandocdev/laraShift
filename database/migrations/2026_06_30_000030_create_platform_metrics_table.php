<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('metric', 50);
            $table->decimal('value', 15, 2)->default(0);
            $table->string('period', 10);
            $table->string('group', 50)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['metric', 'period']);
            $table->index(['metric', 'group', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_metrics');
    }
};
