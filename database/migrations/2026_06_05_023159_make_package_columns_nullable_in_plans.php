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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->change();
            $table->integer('interval_count')->nullable()->change();
            $table->string('provider_plan_id')->nullable()->change();
            $table->string('currency')->nullable()->change();
            $table->string('interval')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
