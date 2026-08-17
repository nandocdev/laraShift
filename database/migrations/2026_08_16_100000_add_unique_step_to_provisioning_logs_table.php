<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique step per tenant enables resumable provisioning: the pipeline
     * skips completed steps instead of restarting from scratch on retry.
     */
    public function up(): void
    {
        Schema::table('provisioning_logs', function (Blueprint $table) {
            $table->unique(['tenant_id', 'step']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provisioning_logs', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'step']);
        });
    }
};
