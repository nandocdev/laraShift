<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurrence state for the engine-managed billing lifecycle.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('next_payment_at')->nullable()->after('current_period_end');
            $table->string('pm_card_id')->nullable()->after('next_payment_at');
            $table->unsignedSmallInteger('failed_attempts')->default(0)->after('pm_card_id');
            $table->timestamp('cancelled_at')->nullable()->after('failed_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['next_payment_at', 'pm_card_id', 'failed_attempts', 'cancelled_at']);
        });
    }
};
