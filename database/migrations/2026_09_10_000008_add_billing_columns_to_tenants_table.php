<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * tenants.status stays a free string: pending_payment/past_due need
     * no schema change, only the gateway + plan columns return.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_gateway')->default('clave')->after('status');
            $table->string('plan_id')->default('free')->after('billing_gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_gateway', 'plan_id']);
        });
    }
};
