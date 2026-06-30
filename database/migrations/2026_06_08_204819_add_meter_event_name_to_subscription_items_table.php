<?php

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
        if (Schema::hasTable('subscription_items') && ! Schema::hasColumn('subscription_items', 'meter_event_name')) {
            Schema::table('subscription_items', function (Blueprint $table) {
                $table->string('meter_event_name')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscription_items') && Schema::hasColumn('subscription_items', 'meter_event_name')) {
            Schema::table('subscription_items', function (Blueprint $table) {
                $table->dropColumn('meter_event_name');
            });
        }
    }
};
