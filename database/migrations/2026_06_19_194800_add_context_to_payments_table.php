<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('context', 30)
                ->default('subscription')
                ->after('tenant_id')
                ->comment('Payment context: subscription, service_order, invoice');

            $table->index(['context', 'status'], 'idx_payments_context_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_context_status');
            $table->dropColumn('context');
        });
    }
};
