<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('sent_at');
            $table->boolean('is_draft')->default(false)->after('scheduled_at');
        });

        Schema::create('broadcast_tenant', function (Blueprint $table) {
            $table->foreignUuid('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->primary(['broadcast_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_tenant');

        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'is_draft']);
        });
    }
};
