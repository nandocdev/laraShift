<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_reference')->unique()->comment('dLocal payment_id');
            $table->string('order_id')->index();
            $table->string('context')->default('central')->comment('central|tenant');
            $table->string('tenant_id')->nullable()->index();
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->index(['owner_type', 'owner_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_references');
    }
};
